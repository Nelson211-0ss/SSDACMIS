<?php
namespace App\Controllers;

use App\Core\AcademicYear;
use App\Core\App;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\SchoolIdentity;
use App\Services\AcademicMarking;
use App\Services\TermResultsService;

/**
 * Published results, per assessment stage:
 *   - Mid-term    — mid-term marks only, each subject out of 30
 *   - End of term — mid + end combined, each subject out of 100
 * Both are computed and stored separately, so picking Mid-term never shows
 * end-of-term marks folded in.
 *
 * GET /results                  — pick year + term + assessment, list classes
 * GET /results/class/{id}       — class leaderboard + optional subject totals
 */
class ResultsController extends Controller
{
    private const TERMS = ['Term 1', 'Term 2', 'Term 3'];

    /** Requested assessment stage, defaulting to the full end-of-term result. */
    private function stage(): string
    {
        return AcademicMarking::normalizeStage((string) $this->input('stage', ''));
    }

    /** Academic years are flat calendar years — see App\Core\AcademicYear. */
    private static function defaultYear(): string
    {
        return AcademicYear::current();
    }

    private static function selectableYears(): array
    {
        return AcademicYear::options();
    }

    private static function isValidYear(string $year): bool
    {
        return AcademicYear::isValid($year);
    }

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
        $row = Database::query(
            'SELECT 1 FROM department_heads WHERE staff_id = ? LIMIT 1',
            [$sid]
        )->fetch();

        return (bool) $row;
    }

    /** Class IDs visible to the current user (aligned with reports). */
    private function visibleClassIds(): array
    {
        if ($this->isAdmin() || $this->isHod()) {
            $schoolId = Auth::schoolId();
            $ssf = $schoolId !== null ? ' WHERE school_id = ?' : '';
            $ssp = $schoolId !== null ? [$schoolId] : [];
            $rows = Database::query("SELECT id FROM classes{$ssf} ORDER BY name", $ssp)->fetchAll();

            return array_map(static fn ($r) => (int) $r['id'], $rows);
        }
        if (Auth::role() === 'staff') {
            $sid = $this->staffId();
            if (!$sid) {
                return [];
            }
            $schoolId = Auth::schoolId();
            $rows = Database::query(
                'SELECT DISTINCT ta.class_id FROM teaching_assignments ta
                 JOIN classes c ON c.id = ta.class_id AND c.school_id = ?
                 WHERE ta.staff_id = ?
                 UNION
                 SELECT id AS class_id FROM classes WHERE class_teacher_id = ? AND school_id = ?',
                [$schoolId, $sid, $sid, $schoolId]
            )->fetchAll();

            return array_map(static fn ($r) => (int) $r['class_id'], $rows);
        }

        return [];
    }

    private function canSeeClass(int $classId): bool
    {
        return in_array($classId, $this->visibleClassIds(), true);
    }

    public function index(): string
    {
        $year  = trim((string) $this->input('year', ''));
        $term  = trim((string) $this->input('term', ''));
        $stage = $this->stage();

        $periodReady = ($year !== '' && $term !== ''
            && self::isValidYear($year)
            && in_array($term, self::TERMS, true));

        if (!$periodReady) {
            return $this->view('results/period', [
                'years'          => self::selectableYears(),
                'defaultYear'    => self::defaultYear(),
                'terms'          => self::TERMS,
                'stages'         => AcademicMarking::stages(),
                'submittedYear'  => $year,
                'submittedTerm'  => $term,
                'submittedStage' => $stage,
                'invalid'        => ($year !== '' || $term !== ''),
            ]);
        }

        $ids = $this->visibleClassIds();
        $classes = [];
        if ($ids !== []) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $classes = Database::query(
                "SELECT id, name, level FROM classes WHERE id IN ($ph) ORDER BY level, name",
                $ids
            )->fetchAll();
        }

        return $this->view('results/index', [
            'year'       => $year,
            'term'       => $term,
            'terms'      => self::TERMS,
            'years'      => self::selectableYears(),
            'stage'      => $stage,
            'stages'     => AcademicMarking::stages(),
            'stageLabel' => AcademicMarking::stageLabel($stage),
            'stageMax'   => AcademicMarking::stageSubjectMax($stage),
            'classes'    => $classes,
            'midMax'     => AcademicMarking::MID_MAX,
            'endMax'     => AcademicMarking::END_MAX,
            'schoolName' => SchoolIdentity::name() ?: App::config('app.name'),
        ]);
    }

    public function classView(string $id): string
    {
        $classId = (int) $id;
        if (!$this->canSeeClass($classId)) {
            http_response_code(403);

            return $this->view('errors/403');
        }

        $year  = AcademicYear::resolve((string) $this->input('year', ''));
        $term  = (string) ($this->input('term') ?: 'Term 1');
        $stage = $this->stage();
        if (!in_array($term, self::TERMS, true)) {
            $term = 'Term 1';
        }

        $class = Database::query(
            'SELECT c.id, c.name, c.level,
                    t.first_name AS teacher_first, t.last_name AS teacher_last
             FROM classes c
             LEFT JOIN staff t ON t.id = c.class_teacher_id
             WHERE c.id = ?',
            [$classId]
        )->fetch();
        if (!$class) {
            http_response_code(404);

            return $this->view('errors/404');
        }

        TermResultsService::ensureTables();

        $rows = Database::query(
            "SELECT tsr.student_id, tsr.average_percentage, tsr.class_position, tsr.rank_cohort,
                    tsr.subjects_with_totals,
                    s.admission_no, s.first_name, s.last_name, s.stream
             FROM term_student_results tsr
             JOIN students s ON s.id = tsr.student_id
             WHERE tsr.class_id = ? AND tsr.academic_year = ? AND tsr.term = ? AND tsr.stage = ?
             ORDER BY
               CASE WHEN tsr.class_position IS NULL THEN 1 ELSE 0 END,
               tsr.class_position ASC,
               s.first_name,
               s.last_name",
            [$classId, $year, $term, $stage]
        )->fetchAll();

        $subjectCols = AcademicMarking::offeredSubjectsForSchoolReport();

        $cells = [];
        if ($subjectCols !== []) {
            $schoolId = Auth::schoolId();
            $srSql = 'SELECT tsr.student_id, tsr.subject_id, tsr.total_marks, tsr.letter_grade,
                             tsr.mid_marks, tsr.end_marks
                 FROM term_subject_results tsr
                 INNER JOIN subjects sub ON sub.id = tsr.subject_id AND sub.is_offered = 1'
                 . ($schoolId !== null ? ' AND sub.school_id = ?' : '')
                 . ' WHERE tsr.class_id = ? AND tsr.academic_year = ? AND tsr.term = ? AND tsr.stage = ?';
            $srParams = $schoolId !== null
                ? [$schoolId, $classId, $year, $term, $stage]
                : [$classId, $year, $term, $stage];
            $sr = Database::query($srSql, $srParams)->fetchAll();
            foreach ($sr as $r) {
                $sid = (int) $r['student_id'];
                $bid = (int) $r['subject_id'];
                $mid = $r['mid_marks'] !== null ? (float) $r['mid_marks'] : null;
                $end = $r['end_marks'] !== null ? (float) $r['end_marks'] : null;
                $cells[$sid][$bid] = [
                    'total' => $r['total_marks'],
                    'max'   => AcademicMarking::subjectMax($mid, $end, $stage),
                    'grade' => $r['letter_grade'],
                ];
            }
        }

        return $this->view('results/class', [
            'class'       => $class,
            'year'        => $year,
            'term'        => $term,
            'terms'       => self::TERMS,
            'years'       => self::selectableYears(),
            'stage'       => $stage,
            'stages'      => AcademicMarking::stages(),
            'stageLabel'  => AcademicMarking::stageLabel($stage),
            'stageMax'    => AcademicMarking::stageSubjectMax($stage),
            'rows'        => $rows,
            'subjectCols' => $subjectCols,
            'cells'       => $cells,
            'midMax'      => AcademicMarking::MID_MAX,
            'endMax'      => AcademicMarking::END_MAX,
            'schoolName'  => SchoolIdentity::name() ?: App::config('app.name'),
        ]);
    }

    /** An average (or subject percentage) at or above this is a pass. */
    private const PASS_MARK = 50.0;

    /** How many boys and how many girls the "best performers" lists show. */
    private const TOP_N = 5;

    /**
     * One class, one period, analysed boys against girls:
     *   - overall summary (sat, average, highest, lowest, pass rate)
     *   - grade distribution of the students' overall averages
     *   - every subject: mean, pass rate and grade spread per gender
     *   - the class's term-by-term trend for the year
     *   - the best boys and the best girls
     *
     * Reads the published tables (term_student_results / term_subject_results),
     * which are stored against the class the student sat in that term, so a
     * past term still analyses correctly after promotions.
     *
     * GET /results/analysis/{id}?year=&term=&stage=
     */
    public function classAnalysis(string $id): string
    {
        $classId = (int) $id;
        if (!$this->canSeeClass($classId)) {
            http_response_code(403);

            return $this->view('errors/403');
        }

        $year  = AcademicYear::resolve((string) $this->input('year', ''));
        $term  = (string) ($this->input('term') ?: 'Term 1');
        $stage = $this->stage();
        if (!in_array($term, self::TERMS, true)) {
            $term = 'Term 1';
        }

        $class = Database::query(
            'SELECT c.id, c.name, c.level,
                    t.first_name AS teacher_first, t.last_name AS teacher_last
             FROM classes c
             LEFT JOIN staff t ON t.id = c.class_teacher_id
             WHERE c.id = ?',
            [$classId]
        )->fetch();
        if (!$class) {
            http_response_code(404);

            return $this->view('errors/404');
        }

        TermResultsService::ensureTables();

        // Grading tiers once, highest first; grading every score through
        // AcademicMarking::letterGrade() would re-read settings per call.
        $tiers = AcademicMarking::gradingTiers();
        usort($tiers, static fn ($a, $b) => $b['min'] <=> $a['min']);
        $gradeLabels = array_values(array_unique(array_map(static fn ($t) => (string) $t['label'], $tiers)));
        $gradeOf = static function (float $pct) use ($tiers): string {
            foreach ($tiers as $t) {
                if ($pct >= $t['min'] && $pct <= $t['max']) {
                    return (string) $t['label'];
                }
            }

            return '';
        };
        $empty = static fn (): array => [
            'male'   => self::emptyStats($gradeLabels),
            'female' => self::emptyStats($gradeLabels),
            'all'    => self::emptyStats($gradeLabels),
        ];

        // ---- Students and their overall averages -------------------------
        $students = Database::query(
            "SELECT tsr.student_id, tsr.average_percentage, tsr.class_position,
                    s.admission_no, s.first_name, s.last_name, s.gender, s.stream
             FROM term_student_results tsr
             JOIN students s ON s.id = tsr.student_id
             WHERE tsr.class_id = ? AND tsr.academic_year = ? AND tsr.term = ? AND tsr.stage = ?
               AND tsr.average_percentage IS NOT NULL
             ORDER BY tsr.average_percentage DESC, s.first_name, s.last_name",
            [$classId, $year, $term, $stage]
        )->fetchAll();

        $summary = $empty();
        $otherCount = 0;
        $topBoys = [];
        $topGirls = [];
        foreach ($students as $st) {
            $avg = (float) $st['average_percentage'];
            $gender = (string) $st['gender'];
            foreach (self::bucketsFor($gender) as $b) {
                self::addScore($summary[$b], $avg, $gradeOf($avg));
            }
            if ($gender === 'male' && count($topBoys) < self::TOP_N) {
                $topBoys[] = $st;
            } elseif ($gender === 'female' && count($topGirls) < self::TOP_N) {
                $topGirls[] = $st;
            } elseif ($gender !== 'male' && $gender !== 'female') {
                $otherCount++;
            }
        }
        $summary = array_map([self::class, 'finishStats'], $summary);

        // ---- Subjects ------------------------------------------------------
        $schoolId = Auth::schoolId();
        $subParams = [$classId, $year, $term, $stage];
        if ($schoolId !== null) {
            $subParams[] = $schoolId;
        }
        $subRows = Database::query(
            "SELECT r.subject_id, r.mid_marks, r.end_marks, s.gender,
                    sub.name, sub.code, sub.category
             FROM term_subject_results r
             JOIN students s   ON s.id = r.student_id
             JOIN subjects sub ON sub.id = r.subject_id
             WHERE r.class_id = ? AND r.academic_year = ? AND r.term = ? AND r.stage = ?"
             . ($schoolId !== null ? ' AND sub.school_id = ?' : '') . "
             ORDER BY FIELD(sub.category, 'core','science','arts','optional'), sub.name",
            $subParams
        )->fetchAll();

        $subjects = [];
        foreach ($subRows as $r) {
            $pct = AcademicMarking::subjectPercentage(
                $r['mid_marks'] !== null ? (float) $r['mid_marks'] : null,
                $r['end_marks'] !== null ? (float) $r['end_marks'] : null,
                $stage
            );
            if ($pct === null) {
                continue;
            }
            $sid = (int) $r['subject_id'];
            if (!isset($subjects[$sid])) {
                $subjects[$sid] = [
                    'name'  => (string) $r['name'],
                    'code'  => (string) ($r['code'] ?? ''),
                    'stats' => $empty(),
                    'rank'  => null,
                ];
            }
            foreach (self::bucketsFor((string) $r['gender']) as $b) {
                self::addScore($subjects[$sid]['stats'][$b], $pct, $gradeOf($pct));
            }
        }
        foreach ($subjects as &$sub) {
            $sub['stats'] = array_map([self::class, 'finishStats'], $sub['stats']);
        }
        unset($sub);

        // Competition rank on the whole-class mean (1, 2, 2, 4 …).
        $means = [];
        foreach ($subjects as $sid => $sub) {
            if ($sub['stats']['all']['avg'] !== null) {
                $means[$sid] = round($sub['stats']['all']['avg'], 2);
            }
        }
        arsort($means);
        $pos = 0;
        $prev = null;
        $rank = 0;
        foreach ($means as $sid => $m) {
            $pos++;
            if ($prev === null || $m < $prev) {
                $rank = $pos;
            }
            $subjects[$sid]['rank'] = $rank;
            $prev = $m;
        }

        // ---- Term-by-term for the same year and assessment ---------------
        $trend = [];
        foreach (self::TERMS as $t) {
            $trend[$t] = $empty();
        }
        $trendRows = Database::query(
            "SELECT tsr.term, s.gender, tsr.average_percentage
             FROM term_student_results tsr
             JOIN students s ON s.id = tsr.student_id
             WHERE tsr.class_id = ? AND tsr.academic_year = ? AND tsr.stage = ?
               AND tsr.average_percentage IS NOT NULL",
            [$classId, $year, $stage]
        )->fetchAll();
        foreach ($trendRows as $r) {
            if (!isset($trend[$r['term']])) {
                continue;
            }
            $avg = (float) $r['average_percentage'];
            foreach (self::bucketsFor((string) $r['gender']) as $b) {
                self::addScore($trend[$r['term']][$b], $avg, $gradeOf($avg));
            }
        }
        foreach ($trend as $t => $buckets) {
            $trend[$t] = array_map([self::class, 'finishStats'], $buckets);
        }

        return $this->view('results/analysis', [
            'class'       => $class,
            'year'        => $year,
            'term'        => $term,
            'terms'       => self::TERMS,
            'years'       => self::selectableYears(),
            'stage'       => $stage,
            'stages'      => AcademicMarking::stages(),
            'stageLabel'  => AcademicMarking::stageLabel($stage),
            'summary'     => $summary,
            'gradeLabels' => $gradeLabels,
            'subjects'    => $subjects,
            'trend'       => $trend,
            'topBoys'     => $topBoys,
            'topGirls'    => $topGirls,
            'otherCount'  => $otherCount,
            'passMark'    => self::PASS_MARK,
            'schoolName'  => SchoolIdentity::name() ?: App::config('app.name'),
        ]);
    }

    /** Which summary buckets a student of this gender counts towards. */
    private static function bucketsFor(string $gender): array
    {
        if ($gender === 'male' || $gender === 'female') {
            return [$gender, 'all'];
        }

        return ['all'];
    }

    /** @param list<string> $gradeLabels */
    private static function emptyStats(array $gradeLabels): array
    {
        return [
            'n'       => 0,
            'sum'     => 0.0,
            'max'     => null,
            'min'     => null,
            'passed'  => 0,
            'grades'  => array_fill_keys($gradeLabels, 0),
            'avg'     => null,
            'passPct' => null,
        ];
    }

    private static function addScore(array &$s, float $pct, string $grade): void
    {
        $s['n']++;
        $s['sum'] += $pct;
        $s['max'] = $s['max'] === null ? $pct : max($s['max'], $pct);
        $s['min'] = $s['min'] === null ? $pct : min($s['min'], $pct);
        if ($pct >= self::PASS_MARK) {
            $s['passed']++;
        }
        if ($grade !== '' && isset($s['grades'][$grade])) {
            $s['grades'][$grade]++;
        }
    }

    private static function finishStats(array $s): array
    {
        if ($s['n'] > 0) {
            $s['avg'] = $s['sum'] / $s['n'];
            $s['passPct'] = ($s['passed'] / $s['n']) * 100;
        }

        return $s;
    }

    /**
     * Boys-vs-girls performance breakdown: student counts, average %, and
     * pass rate (>= 50%), aggregated per class and — for the super admin,
     * who is not scoped to a single school — per school as well.
     */
    public function genderPerformance(): string
    {
        $year  = AcademicYear::resolve((string) $this->input('year', ''));
        $term  = (string) ($this->input('term') ?: 'Term 1');
        $stage = $this->stage();
        if (!in_array($term, self::TERMS, true)) {
            $term = 'Term 1';
        }

        TermResultsService::ensureTables();

        $classIds = $this->visibleClassIds();
        $byClass  = [];
        $totals   = ['male' => self::emptyGenderBucket(), 'female' => self::emptyGenderBucket()];
        $bySchool = [];
        $otherCount = 0;

        if ($classIds !== []) {
            $ph = implode(',', array_fill(0, count($classIds), '?'));

            $classRows = Database::query(
                "SELECT id, name, level FROM classes WHERE id IN ($ph) ORDER BY level, name",
                $classIds
            )->fetchAll();
            foreach ($classRows as $c) {
                $byClass[(int) $c['id']] = [
                    'name'  => $c['name'],
                    'level' => $c['level'],
                    'male'   => self::emptyGenderBucket(),
                    'female' => self::emptyGenderBucket(),
                ];
            }

            $params = array_merge($classIds, [$year, $term, $stage]);

            $classGender = Database::query(
                "SELECT tsr.class_id, s.gender,
                        COUNT(*) AS n,
                        AVG(tsr.average_percentage) AS avg_pct,
                        SUM(CASE WHEN tsr.average_percentage >= 50 THEN 1 ELSE 0 END) AS passed
                 FROM term_student_results tsr
                 JOIN students s ON s.id = tsr.student_id
                 WHERE tsr.class_id IN ($ph) AND tsr.academic_year = ? AND tsr.term = ? AND tsr.stage = ?
                       AND s.gender IN ('male','female')
                 GROUP BY tsr.class_id, s.gender",
                $params
            )->fetchAll();
            foreach ($classGender as $r) {
                $cid = (int) $r['class_id'];
                if (!isset($byClass[$cid])) {
                    continue;
                }
                $byClass[$cid][$r['gender']] = self::genderBucketFromRow($r);
            }

            $overall = Database::query(
                "SELECT s.gender,
                        COUNT(*) AS n,
                        AVG(tsr.average_percentage) AS avg_pct,
                        SUM(CASE WHEN tsr.average_percentage >= 50 THEN 1 ELSE 0 END) AS passed
                 FROM term_student_results tsr
                 JOIN students s ON s.id = tsr.student_id
                 WHERE tsr.class_id IN ($ph) AND tsr.academic_year = ? AND tsr.term = ? AND tsr.stage = ?
                       AND s.gender IN ('male','female')
                 GROUP BY s.gender",
                $params
            )->fetchAll();
            foreach ($overall as $r) {
                $totals[$r['gender']] = self::genderBucketFromRow($r);
            }

            $otherCount = (int) (Database::query(
                "SELECT COUNT(*) AS n
                 FROM term_student_results tsr
                 JOIN students s ON s.id = tsr.student_id
                 WHERE tsr.class_id IN ($ph) AND tsr.academic_year = ? AND tsr.term = ? AND tsr.stage = ?
                       AND s.gender = 'other'",
                $params
            )->fetch()['n'] ?? 0);

            // Per-school breakdown only makes sense for the super admin, who
            // isn't already scoped to one school — everyone else's "totals"
            // above already ARE their one school's numbers.
            if (Auth::schoolId() === null) {
                $schoolRows = Database::query(
                    "SELECT sch.id AS school_id, sch.name AS school_name, s.gender,
                            COUNT(*) AS n,
                            AVG(tsr.average_percentage) AS avg_pct,
                            SUM(CASE WHEN tsr.average_percentage >= 50 THEN 1 ELSE 0 END) AS passed
                     FROM term_student_results tsr
                     JOIN students s ON s.id = tsr.student_id
                     JOIN schools sch ON sch.id = s.school_id
                     WHERE tsr.class_id IN ($ph) AND tsr.academic_year = ? AND tsr.term = ? AND tsr.stage = ?
                           AND s.gender IN ('male','female')
                     GROUP BY sch.id, sch.name, s.gender
                     ORDER BY sch.name",
                    $params
                )->fetchAll();
                foreach ($schoolRows as $r) {
                    $sid = (int) $r['school_id'];
                    if (!isset($bySchool[$sid])) {
                        $bySchool[$sid] = [
                            'name'   => $r['school_name'],
                            'male'   => self::emptyGenderBucket(),
                            'female' => self::emptyGenderBucket(),
                        ];
                    }
                    $bySchool[$sid][$r['gender']] = self::genderBucketFromRow($r);
                }
            }
        }

        return $this->view('results/gender', [
            'year'       => $year,
            'term'       => $term,
            'terms'      => self::TERMS,
            'years'      => self::selectableYears(),
            'stage'      => $stage,
            'stages'     => AcademicMarking::stages(),
            'stageLabel' => AcademicMarking::stageLabel($stage),
            'byClass'    => $byClass,
            'bySchool'   => $bySchool,
            'totals'     => $totals,
            'otherCount' => $otherCount,
            'schoolName' => SchoolIdentity::name() ?: App::config('app.name'),
        ]);
    }

    /** @return array{n:int,avg:?float,passed:int,passPct:?float} */
    private static function emptyGenderBucket(): array
    {
        return ['n' => 0, 'avg' => null, 'passed' => 0, 'passPct' => null];
    }

    /** @return array{n:int,avg:?float,passed:int,passPct:?float} */
    private static function genderBucketFromRow(array $r): array
    {
        $n = (int) $r['n'];
        return [
            'n'       => $n,
            'avg'     => $r['avg_pct'] !== null ? (float) $r['avg_pct'] : null,
            'passed'  => (int) $r['passed'],
            'passPct' => $n > 0 ? ((float) $r['passed'] / $n) * 100 : null,
        ];
    }
}
