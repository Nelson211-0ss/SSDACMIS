<?php
namespace App\Controllers;

use App\Core\AcademicYear;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\AcademicMarking;
use App\Services\TermResultsService;

/**
 * Subject performance analytics.
 *
 *   GET /analytics?year=&term=&stage=&class_id=&stream=
 *
 * Answers three questions for one assessment period:
 *   1. How is each subject performing school-wide, ranked best to worst?
 *   2. How is each subject performing inside one class, ranked?
 *   3. Which class teaches each subject best?
 *
 * Everything reads `term_subject_results`, the table TermResultsService
 * writes whenever marks are saved, so the page is a handful of grouped
 * queries rather than a per-student recomputation.
 */
class AnalyticsController extends Controller
{
    private const TERMS = ['Term 1', 'Term 2', 'Term 3'];
    private const STREAMS = ['science' => 'Science', 'arts' => 'Arts'];

    /** A subject needs at least this many marks before it is ranked. */
    private const MIN_ENTRIES = 1;

    private function isAdmin(): bool
    {
        return in_array(Auth::role(), ['admin', 'school_admin'], true);
    }

    private function staffId(): ?int
    {
        $u = Auth::user();
        if (!$u) {
            return null;
        }
        $r = Database::query('SELECT id FROM staff WHERE user_id = ? LIMIT 1', [(int) $u['id']])->fetch();

        return $r ? (int) $r['id'] : null;
    }

    private function isHod(): bool
    {
        if (Auth::role() === 'hod') {
            return true;
        }
        $sid = $this->staffId();
        if (!$sid) {
            return false;
        }
        return (bool) Database::query(
            'SELECT 1 FROM department_heads WHERE staff_id = ? LIMIT 1',
            [$sid]
        )->fetch();
    }

    /**
     * Classes in scope — the same rule reports and results use, so the
     * analytics never widen what a teacher can already see.
     *
     * @return list<array<string,mixed>>
     */
    private function visibleClasses(): array
    {
        $schoolId = Auth::schoolId();

        if ($this->isAdmin() || $this->isHod()) {
            $sql = 'SELECT id, name, level FROM classes';
            $params = [];
            if ($schoolId !== null) {
                $sql .= ' WHERE school_id = ?';
                $params[] = $schoolId;
            }
            $sql .= ' ORDER BY level, name';

            return Database::query($sql, $params)->fetchAll();
        }

        if (Auth::role() === 'staff') {
            $sid = $this->staffId();
            if (!$sid) {
                return [];
            }
            return Database::query(
                'SELECT DISTINCT c.id, c.name, c.level
                 FROM classes c
                 WHERE c.class_teacher_id = ?
                    OR c.id IN (SELECT class_id FROM teaching_assignments WHERE staff_id = ?)
                 ORDER BY c.level, c.name',
                [$sid, $sid]
            )->fetchAll();
        }

        return [];
    }

    public function index(): string
    {
        TermResultsService::ensureTables();

        $year  = AcademicYear::resolve((string) $this->input('year', ''));
        $term  = (string) $this->input('term', 'Term 1');
        if (!in_array($term, self::TERMS, true)) {
            $term = 'Term 1';
        }
        $stage    = AcademicMarking::normalizeStage((string) $this->input('stage', ''));
        $stageMax = (float) AcademicMarking::stageSubjectMax($stage);
        // 50% of whatever the stage is marked out of.
        $passMark = $stageMax / 2;

        $classes   = $this->visibleClasses();
        $classIds  = array_map(static fn ($c) => (int) $c['id'], $classes);
        $classById = [];
        foreach ($classes as $c) {
            $classById[(int) $c['id']] = $c;
        }

        $filterClassId = (int) $this->input('class_id', 0);
        if ($filterClassId > 0 && !in_array($filterClassId, $classIds, true)) {
            $filterClassId = 0;
        }
        $filterStream = strtolower(trim((string) $this->input('stream', '')));
        if (!isset(self::STREAMS[$filterStream])) {
            $filterStream = '';
        }

        $view = [
            'year'          => $year,
            'term'          => $term,
            'terms'         => self::TERMS,
            'years'         => AcademicYear::options(),
            'stage'         => $stage,
            'stages'        => AcademicMarking::stages(),
            'stageLabel'    => AcademicMarking::stageLabel($stage),
            'stageMax'      => $stageMax,
            'passMark'      => $passMark,
            'classes'       => $classes,
            'filterClassId' => $filterClassId,
            'filterStream'  => $filterStream,
            'streams'       => self::STREAMS,
        ];

        if ($classIds === []) {
            return $this->view('analytics/index', $view + [
                'subjects'      => [],
                'classSubjects' => [],
                'classSummary'  => [],
                'totals'        => $this->emptyTotals(),
                'bestClassBySubject' => [],
            ]);
        }

        $scopeIds = $filterClassId > 0 ? [$filterClassId] : $classIds;

        $rows = $this->subjectRows($scopeIds, $year, $term, $stage, $filterStream, $passMark);
        $prev = $this->previousPeriodAverages($scopeIds, $year, $term, $stage, $filterStream);

        // Fold the (class, subject) rows into a school-wide ranking, a
        // per-class ranking and a per-class summary in one pass.
        $subjects       = [];
        $classSubjects  = [];
        $classSummary   = [];

        foreach ($rows as $r) {
            $subjectId = (int) ($r['subject_id'] ?? 0);
            $classId   = (int) ($r['class_id'] ?? 0);
            $entries   = (int) ($r['entries'] ?? 0);
            $passes    = (int) ($r['passes'] ?? 0);
            $sumMarks  = (float) ($r['sum_marks'] ?? 0);

            if (!isset($subjects[$subjectId])) {
                $subjects[$subjectId] = [
                    'subject_id' => $subjectId,
                    'name'       => (string) $r['subject_name'],
                    'code'       => (string) ($r['subject_code'] ?? ''),
                    'category'   => (string) ($r['category'] ?? 'optional'),
                    'entries'    => 0,
                    'passes'     => 0,
                    'sum_marks'  => 0.0,
                    'best_marks' => null,
                    'worst_marks'=> null,
                    'classes'    => [],
                ];
            }
            $subjects[$subjectId]['entries']   += $entries;
            $subjects[$subjectId]['passes']    += $passes;
            $subjects[$subjectId]['sum_marks'] += $sumMarks;

            $best  = $r['best_marks']  !== null ? (float) $r['best_marks']  : null;
            $worst = $r['worst_marks'] !== null ? (float) $r['worst_marks'] : null;
            if ($best !== null) {
                $subjects[$subjectId]['best_marks'] = $subjects[$subjectId]['best_marks'] === null
                    ? $best : max($subjects[$subjectId]['best_marks'], $best);
            }
            if ($worst !== null) {
                $subjects[$subjectId]['worst_marks'] = $subjects[$subjectId]['worst_marks'] === null
                    ? $worst : min($subjects[$subjectId]['worst_marks'], $worst);
            }

            $classAvg = $entries > 0 ? $sumMarks / $entries : null;
            $subjects[$subjectId]['classes'][] = [
                'class_id'   => $classId,
                'class_name' => (string) ($classById[$classId]['name'] ?? 'Class ' . $classId),
                'average'    => $classAvg,
                'entries'    => $entries,
            ];

            $classSubjects[$classId][] = [
                'subject_id' => $subjectId,
                'name'       => (string) $r['subject_name'],
                'code'       => (string) ($r['subject_code'] ?? ''),
                'category'   => (string) ($r['category'] ?? 'optional'),
                'entries'    => $entries,
                'passes'     => $passes,
                'average'    => $classAvg,
                'percent'    => $classAvg !== null && $stageMax > 0 ? ($classAvg / $stageMax) * 100 : null,
                'pass_rate'  => $entries > 0 ? ($passes / $entries) * 100 : null,
                'best_marks' => $best,
                'worst_marks'=> $worst,
            ];

            if (!isset($classSummary[$classId])) {
                $classSummary[$classId] = [
                    'class_id'   => $classId,
                    'class_name' => (string) ($classById[$classId]['name'] ?? 'Class ' . $classId),
                    'level'      => (string) ($classById[$classId]['level'] ?? ''),
                    'entries'    => 0,
                    'passes'     => 0,
                    'sum_marks'  => 0.0,
                    'subjects'   => 0,
                ];
            }
            $classSummary[$classId]['entries']   += $entries;
            $classSummary[$classId]['passes']    += $passes;
            $classSummary[$classId]['sum_marks'] += $sumMarks;
            $classSummary[$classId]['subjects']++;
        }

        // Finish the school-wide subject table: percentages, pass rates,
        // the class that performs best in each subject, and the movement
        // against the previous assessment period.
        $bestClassBySubject = [];
        foreach ($subjects as $sid => &$s) {
            $s['average']   = $s['entries'] > 0 ? $s['sum_marks'] / $s['entries'] : null;
            $s['percent']   = ($s['average'] !== null && $stageMax > 0)
                ? ($s['average'] / $stageMax) * 100 : null;
            $s['pass_rate'] = $s['entries'] > 0 ? ($s['passes'] / $s['entries']) * 100 : null;

            usort(
                $s['classes'],
                static fn ($a, $b) => ($b['average'] ?? -1) <=> ($a['average'] ?? -1)
            );
            $s['best_class']  = $s['classes'][0] ?? null;
            $s['worst_class'] = count($s['classes']) > 1 ? end($s['classes']) : null;
            if ($s['best_class'] !== null) {
                $bestClassBySubject[$sid] = $s['best_class'];
            }

            $prevAvg = $prev[$sid] ?? null;
            $s['previous_average'] = $prevAvg;
            $s['delta'] = ($prevAvg !== null && $s['average'] !== null)
                ? $s['average'] - $prevAvg
                : null;
        }
        unset($s);

        // Best-to-worst, subjects with no marks last.
        uasort(
            $subjects,
            static fn ($a, $b) => ($b['average'] ?? -1) <=> ($a['average'] ?? -1)
        );
        $rank = 0;
        foreach ($subjects as &$s) {
            $s['rank'] = ($s['entries'] >= self::MIN_ENTRIES) ? ++$rank : null;
        }
        unset($s);

        // Per-class subject rankings.
        foreach ($classSubjects as $cid => &$list) {
            usort($list, static fn ($a, $b) => ($b['average'] ?? -1) <=> ($a['average'] ?? -1));
            $r = 0;
            foreach ($list as &$row) {
                $row['rank'] = ($row['entries'] >= self::MIN_ENTRIES) ? ++$r : null;
            }
            unset($row);
        }
        unset($list);

        foreach ($classSummary as &$cs) {
            $cs['average']   = $cs['entries'] > 0 ? $cs['sum_marks'] / $cs['entries'] : null;
            $cs['percent']   = ($cs['average'] !== null && $stageMax > 0)
                ? ($cs['average'] / $stageMax) * 100 : null;
            $cs['pass_rate'] = $cs['entries'] > 0 ? ($cs['passes'] / $cs['entries']) * 100 : null;
            $cs['best_subject']  = $classSubjects[$cs['class_id']][0] ?? null;
            $lastList = $classSubjects[$cs['class_id']] ?? [];
            $cs['worst_subject'] = count($lastList) > 1 ? end($lastList) : null;
        }
        unset($cs);
        uasort($classSummary, static fn ($a, $b) => ($b['average'] ?? -1) <=> ($a['average'] ?? -1));

        return $this->view('analytics/index', $view + [
            'subjects'           => array_values($subjects),
            'classSubjects'      => $classSubjects,
            'classSummary'       => array_values($classSummary),
            'bestClassBySubject' => $bestClassBySubject,
            'totals'             => $this->totals($subjects, $stageMax),
        ]);
    }

    /* ------------------------------------------------------------------ */

    /**
     * One row per (class, subject) for the period, already aggregated by the
     * database. Everything the page shows is folded out of these rows.
     *
     * @param list<int> $classIds
     * @return list<array<string,mixed>>
     */
    private function subjectRows(
        array $classIds,
        string $year,
        string $term,
        string $stage,
        string $stream,
        float $passMark
    ): array {
        $ph     = implode(',', array_fill(0, count($classIds), '?'));
        $params = [$passMark];

        $streamJoin = '';
        if ($stream !== '') {
            // Streams live on the student, and only Form 3 / Form 4 use them.
            $streamJoin = ' JOIN students st ON st.id = tsr.student_id AND st.stream = ?';
        }

        $sql = "SELECT tsr.class_id,
                       sub.id   AS subject_id,
                       sub.name AS subject_name,
                       sub.code AS subject_code,
                       sub.category,
                       COUNT(*)               AS entries,
                       SUM(tsr.total_marks)   AS sum_marks,
                       MAX(tsr.total_marks)   AS best_marks,
                       MIN(tsr.total_marks)   AS worst_marks,
                       SUM(CASE WHEN tsr.total_marks >= ? THEN 1 ELSE 0 END) AS passes
                FROM term_subject_results tsr
                JOIN subjects sub ON sub.id = tsr.subject_id AND sub.is_offered = 1
                {$streamJoin}
                WHERE tsr.academic_year = ? AND tsr.term = ? AND tsr.stage = ?
                  AND tsr.total_marks IS NOT NULL
                  AND tsr.class_id IN ($ph)
                GROUP BY tsr.class_id, sub.id, sub.name, sub.code, sub.category";

        if ($stream !== '') {
            $params[] = $stream;
        }
        $params[] = $year;
        $params[] = $term;
        $params[] = $stage;
        foreach ($classIds as $cid) {
            $params[] = $cid;
        }

        return Database::query($sql, $params)->fetchAll();
    }

    /**
     * Per-subject average for the period immediately before this one, used
     * for the "vs last term" movement column. Term 1's predecessor is Term 3
     * of the previous year.
     *
     * @param list<int> $classIds
     * @return array<int,float> subject_id => average
     */
    private function previousPeriodAverages(
        array $classIds,
        string $year,
        string $term,
        string $stage,
        string $stream
    ): array {
        $idx = array_search($term, self::TERMS, true);
        if ($idx === false) {
            return [];
        }
        if ($idx === 0) {
            $prevTerm = self::TERMS[count(self::TERMS) - 1];
            $prevYear = (string) (((int) $year) - 1);
        } else {
            $prevTerm = self::TERMS[$idx - 1];
            $prevYear = $year;
        }

        $ph     = implode(',', array_fill(0, count($classIds), '?'));
        $params = [];
        $streamJoin = '';
        if ($stream !== '') {
            $streamJoin = ' JOIN students st ON st.id = tsr.student_id AND st.stream = ?';
            $params[] = $stream;
        }
        $params[] = $prevYear;
        $params[] = $prevTerm;
        $params[] = $stage;
        foreach ($classIds as $cid) {
            $params[] = $cid;
        }

        $rows = Database::query(
            "SELECT tsr.subject_id, AVG(tsr.total_marks) AS avg_marks
             FROM term_subject_results tsr
             {$streamJoin}
             WHERE tsr.academic_year = ? AND tsr.term = ? AND tsr.stage = ?
               AND tsr.total_marks IS NOT NULL
               AND tsr.class_id IN ($ph)
             GROUP BY tsr.subject_id",
            $params
        )->fetchAll();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['subject_id']] = (float) $r['avg_marks'];
        }

        return $out;
    }

    /** @param array<int,array<string,mixed>> $subjects */
    private function totals(array $subjects, float $stageMax): array
    {
        $entries = 0;
        $passes  = 0;
        $sum     = 0.0;
        $ranked  = 0;
        foreach ($subjects as $s) {
            $entries += (int) $s['entries'];
            $passes  += (int) $s['passes'];
            $sum     += (float) $s['sum_marks'];
            if ((int) $s['entries'] > 0) {
                $ranked++;
            }
        }
        $avg = $entries > 0 ? $sum / $entries : null;

        $ordered = array_values($subjects);
        $best  = $ordered[0] ?? null;
        $worst = null;
        for ($i = count($ordered) - 1; $i >= 0; $i--) {
            if ((int) $ordered[$i]['entries'] > 0) {
                $worst = $ordered[$i];
                break;
            }
        }
        if ($best !== null && $worst !== null && $best['subject_id'] === $worst['subject_id']) {
            $worst = null;
        }

        return [
            'subjects'  => $ranked,
            'entries'   => $entries,
            'average'   => $avg,
            'percent'   => ($avg !== null && $stageMax > 0) ? ($avg / $stageMax) * 100 : null,
            'pass_rate' => $entries > 0 ? ($passes / $entries) * 100 : null,
            'best'      => $best,
            'worst'     => $worst,
        ];
    }

    private function emptyTotals(): array
    {
        return [
            'subjects' => 0, 'entries' => 0, 'average' => null,
            'percent' => null, 'pass_rate' => null, 'best' => null, 'worst' => null,
        ];
    }
}
