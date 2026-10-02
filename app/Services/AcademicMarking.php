<?php
namespace App\Services;

use App\Core\Database;
use App\Core\Auth;
use App\Core\Settings;

/**
 * South Sudan–style ACMIS scoring: Mid-term (×/30) + End-of-term (×/70) = 100 max per subject.
 * Reusable validation, totals, letter grades (configurable), averages, competition ranking (1,2,2,4).
 *
 * Results and reports are published at two independent assessment stages:
 *   - STAGE_MID ("Mid-term")   — mid-term marks only, each subject out of 30.
 *   - STAGE_END ("End of term") — mid + end combined, each subject out of 100.
 * Every figure below (totals, denominators, percentages, averages, positions)
 * is derived for ONE stage at a time, so a mid-term result set never changes
 * once end-of-term marks start coming in.
 */
final class AcademicMarking
{
    public const MID_MAX  = 30.0;
    public const END_MAX  = 70.0;
    public const TOTAL_MAX = 100.0;

    /** Mid-term assessment: mid marks only, subject max 30. */
    public const STAGE_MID = 'midterm';
    /** End-of-term assessment: mid + end, subject max 100. */
    public const STAGE_END = 'endterm';

    /**
     * Subjects a student is expected to sit, by level. These set the
     * denominator of the overall average:
     *
     *   Form 1 & 2 — 12 subjects, so an end-of-term average is the sum of
     *                the subject scores out of 12 × 100 = 1200.
     *   Form 3 & 4 — 8 subjects within the student's stream, out of
     *                8 × 100 = 800.
     *
     * The denominator is FIXED by level, not by how many subjects happen to
     * be marked: an unmarked or part-marked subject pulls the average down
     * rather than being quietly left out of the sum.
     *
     * At mid-term each subject is out of 30 instead of 100, so the same
     * counts give 360 and 240 — see expectedTotal().
     */
    public const LOWER_SUBJECT_COUNT = 12;   // Form 1, Form 2
    public const UPPER_SUBJECT_COUNT = 8;    // Form 3, Form 4

    public const ERR_MID_HIGH = 'Mid-term marks cannot exceed 30';
    public const ERR_MID_LOW  = 'Mid-term marks cannot be below 0';
    public const ERR_END_HIGH = 'End-of-term marks cannot exceed 70';
    public const ERR_END_LOW  = 'End-of-term marks cannot be below 0';

    /** @return array<string,string> stage key => human label */
    public static function stages(): array
    {
        return [
            self::STAGE_MID => 'Mid-term',
            self::STAGE_END => 'End of term',
        ];
    }

    /** Anything that isn't an explicit mid-term request means the full end-of-term result. */
    public static function normalizeStage(?string $stage): string
    {
        return ((string) $stage) === self::STAGE_MID ? self::STAGE_MID : self::STAGE_END;
    }

    public static function stageLabel(?string $stage): string
    {
        return self::stages()[self::normalizeStage($stage)];
    }

    /** Per-subject denominator the stage is published out of (30 mid-term, 100 end-of-term). */
    public static function stageSubjectMax(?string $stage): int
    {
        return self::normalizeStage($stage) === self::STAGE_MID
            ? (int) self::MID_MAX
            : (int) self::TOTAL_MAX;
    }

    /** Form 3 and Form 4 are the streamed, 8-subject levels. */
    public static function isUpperLevel(?string $level): bool
    {
        return in_array(trim((string) $level), ['Form 3', 'Form 4'], true);
    }

    /**
     * How many subjects the average is divided across for a level.
     *
     * $fallback is used only when the class has no recognised level (blank,
     * or something outside Form 1–4). Falling back to what the student
     * actually takes keeps an unconfigured class producing a sensible
     * percentage instead of one measured against the wrong denominator.
     */
    public static function expectedSubjectCount(?string $level, ?int $fallback = null): int
    {
        $l = trim((string) $level);
        if ($l === 'Form 1' || $l === 'Form 2') {
            return self::LOWER_SUBJECT_COUNT;
        }
        if (self::isUpperLevel($l)) {
            return self::UPPER_SUBJECT_COUNT;
        }

        return ($fallback !== null && $fallback > 0) ? $fallback : self::LOWER_SUBJECT_COUNT;
    }

    /**
     * The score a student at this level would get for full marks in every
     * subject — 1200 / 800 at end of term, 360 / 240 at mid-term.
     */
    public static function expectedTotal(?string $level, ?string $stage, ?int $fallbackCount = null): float
    {
        return self::expectedSubjectCount($level, $fallbackCount)
             * (float) self::stageSubjectMax($stage);
    }

    /**
     * The overall average: total scored across the student's subjects as a
     * percentage of what full marks would have been.
     *
     *   Form 1 & 2 :  total / 1200 × 100
     *   Form 3 & 4 :  total / 800  × 100
     *
     * Returns null when nothing is marked yet, so a student with no results
     * shows "—" rather than 0%. Capped at 100 so a school whose curriculum
     * is larger than the expected count can never report above full marks.
     */
    public static function averagePercentage(
        ?float $totalScore,
        ?string $level,
        ?string $stage,
        ?int $fallbackCount = null
    ): ?float {
        if ($totalScore === null) {
            return null;
        }
        $expected = self::expectedTotal($level, $stage, $fallbackCount);
        if ($expected <= 0) {
            return null;
        }

        return round(min(100.0, ($totalScore / $expected) * 100), 2);
    }

    /**
     * The mark components that count toward a stage. Mid-term results ignore
     * end-of-term marks completely — that's what keeps the two result sets
     * separate rather than the mid-term one silently turning into the final
     * one as soon as end-of-term marks are entered.
     *
     * @return array{0:?float,1:?float}
     */
    public static function componentsForStage(?float $mid, ?float $end, ?string $stage): array
    {
        return self::normalizeStage($stage) === self::STAGE_MID ? [$mid, null] : [$mid, $end];
    }

    /**
     * @return list<array{label:string,min:float,max:float}>
     */
    public static function defaultGradingTiers(): array
    {
        return [
            ['label' => 'A', 'min' => 80.0, 'max' => 100.0],
            ['label' => 'B', 'min' => 70.0, 'max' => 79.99],
            ['label' => 'C', 'min' => 60.0, 'max' => 69.99],
            ['label' => 'D', 'min' => 50.0, 'max' => 59.99],
            ['label' => 'F', 'min' => 0.0,  'max' => 49.99],
        ];
    }

    /**
     * Load tiers from settings (`grading_scale_json`) or defaults.
     *
     * @return list<array{label:string,min:float,max:float}>
     */
    public static function gradingTiers(): array
    {
        Settings::ensureTable();
        $raw = Settings::get('grading_scale_json', '');
        if ($raw === null || trim((string) $raw) === '') {
            return self::defaultGradingTiers();
        }
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded) || $decoded === []) {
            return self::defaultGradingTiers();
        }
        $out = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $label = isset($row['label']) ? trim((string) $row['label']) : '';
            if ($label === '') {
                continue;
            }
            $min = isset($row['min']) ? (float) $row['min'] : 0.0;
            $max = isset($row['max']) ? (float) $row['max'] : 100.0;
            $out[] = ['label' => $label, 'min' => $min, 'max' => $max];
        }
        return $out !== [] ? $out : self::defaultGradingTiers();
    }

    /** Letter grade for a subject total (0–100). Ungraded returns empty string. */
    public static function letterGrade(float $totalMarks): string
    {
        $tiers = self::gradingTiers();
        usort($tiers, static fn ($a, $b) => ($b['min'] <=> $a['min']));
        foreach ($tiers as $t) {
            if ($totalMarks >= $t['min'] && $totalMarks <= $t['max']) {
                return $t['label'];
            }
        }
        return '';
    }

    /** Optional remark text matching legacy reports (uses same breakpoints as default tiers). */
    public static function remarkForAverage(float $average): string
    {
        if ($average >= 80) {
            return 'Excellent';
        }
        if ($average >= 70) {
            return 'Very Good';
        }
        if ($average >= 60) {
            return 'Good';
        }
        if ($average >= 50) {
            return 'Pass';
        }
        return 'Needs Improvement';
    }

    /** Validate mid-term component when non-empty; returns error message or null. */
    public static function validateMid(float $value): ?string
    {
        if ($value < 0) {
            return self::ERR_MID_LOW;
        }
        if ($value > self::MID_MAX + 1e-9) {
            return self::ERR_MID_HIGH;
        }
        return null;
    }

    /** Validate end-of-term component when non-empty; returns error message or null. */
    public static function validateEnd(float $value): ?string
    {
        if ($value < 0) {
            return self::ERR_END_LOW;
        }
        if ($value > self::END_MAX + 1e-9) {
            return self::ERR_END_HIGH;
        }
        return null;
    }

    /**
     * Raw subject total: Mid + End once both exist (max 100); otherwise the
     * single entered component, taken at face value against ITS OWN max —
     * i.e. out of 30 when only mid-term is in, out of 70 when only end-term
     * is in. Nothing is scaled up here; use subjectMax() to know the
     * denominator and subjectPercentage() for anything that needs a
     * comparable 0–100 figure (grading, remarks, averages, ranking).
     */
    public static function subjectTotal(?float $mid, ?float $end, ?string $stage = self::STAGE_END): ?float
    {
        [$mid, $end] = self::componentsForStage($mid, $end, $stage);
        if ($mid !== null && $end !== null) {
            return round(min(self::TOTAL_MAX, $mid + $end), 2);
        }
        if ($mid !== null) {
            return round($mid, 2);
        }
        if ($end !== null) {
            return round($end, 2);
        }
        return null;
    }

    /** The denominator subjectTotal() is out of, given which components exist. */
    public static function subjectMax(?float $mid, ?float $end, ?string $stage = self::STAGE_END): ?int
    {
        [$mid, $end] = self::componentsForStage($mid, $end, $stage);
        if ($mid !== null && $end !== null) {
            return (int) self::TOTAL_MAX;
        }
        if ($mid !== null) {
            return (int) self::MID_MAX;
        }
        if ($end !== null) {
            return (int) self::END_MAX;
        }
        return null;
    }

    /**
     * subjectTotal() expressed as a 0–100 percentage of subjectMax() — the
     * only figure that's valid to average or rank across subjects that may
     * currently be on different denominators (some /30 mid-only, some /100
     * complete). Grading tiers, remarks, and cross-subject averages must
     * always be computed from THIS, never from the raw total directly.
     */
    public static function subjectPercentage(?float $mid, ?float $end, ?string $stage = self::STAGE_END): ?float
    {
        $total = self::subjectTotal($mid, $end, $stage);
        $max   = self::subjectMax($mid, $end, $stage);
        if ($total === null || $max === null || $max <= 0) {
            return null;
        }
        return round(min(100.0, ($total / $max) * 100), 2);
    }

    /**
     * Curriculum subjects for a student (same rules as report cards).
     *
     * @return list<array{id:int,name:string,code:?string,category:string}>
     */
    public static function offeredSubjectsForStudent(int $studentId): array
    {
        return self::curriculumForStudent($studentId)['subjects'];
    }

    /**
     * The same curriculum lookup, plus the level and stream it was derived
     * from — the average's denominator depends on the level, so callers
     * need both without paying for a second query.
     *
     * @return array{level:string,stream:string,subjects:list<array<string,mixed>>}
     */
    public static function curriculumForStudent(int $studentId): array
    {
        $student = Database::query(
            'SELECT s.stream, c.level, s.school_id
             FROM students s LEFT JOIN classes c ON c.id = s.class_id
             WHERE s.id = ?',
            [$studentId]
        )->fetch();
        $level  = trim((string) ($student['level'] ?? ''));
        $stream = (string) ($student['stream'] ?? 'none');
        $schoolId = isset($student['school_id']) ? (int) $student['school_id'] : null;

        $sql = 'SELECT id, name, code, category FROM subjects WHERE is_offered = 1';
        $params = [];
        if ($schoolId !== null) { $sql .= ' AND school_id = ?'; $params[] = $schoolId; }
        $isUpper = ($level === 'Form 3' || $level === 'Form 4');
        if ($isUpper) {
            if ($stream === 'science') {
                $sql .= " AND category <> 'arts'";
            } elseif ($stream === 'arts') {
                $sql .= " AND category <> 'science'";
            } else {
                $sql .= " AND category NOT IN ('science','arts')";
            }
        }
        $sql .= " ORDER BY FIELD(category, 'core','science','arts','optional'), name";

        return [
            'level'    => $level,
            'stream'   => $stream,
            'subjects' => Database::query($sql, $params)->fetchAll(),
        ];
    }

    /**
     * All subjects the school has ticked as offered (`is_offered`), for class-wide column layouts
     * (e.g. term results table) — same ordering as subject management.
     *
     * @return list<array{id:int,name:string,code:?string,category:string}>
     */
    public static function offeredSubjectsForSchoolReport(): array
    {
        $schoolId = Auth::schoolId();
        $sf = $schoolId !== null ? ' AND school_id = ?' : '';
        $sp = $schoolId !== null ? [$schoolId] : [];
        return Database::query(
            "SELECT id, name, code, category FROM subjects WHERE is_offered = 1{$sf}
             ORDER BY FIELD(category, 'core','science','arts','optional'), name",
            $sp
        )->fetchAll();
    }

    /**
     * Build score sheet: total per subject = mid + end; average = sum(totals) / subjects counted.
     * Lists every subject the school offers for this student (`is_offered`, with stream rules);
     * cells stay empty (—) until marks are entered.
     *
     * @return array{
     *   groups: array<string,array{label:string,rows:list<array<string,mixed>>}>,
     *   totalSum: float,
     *   subjectCount: int,
     *   average: float|null,
     *   grade: string
     * }
     */
    public static function buildScoreSheet(
        int $studentId,
        string $year,
        string $term,
        ?string $stage = self::STAGE_END
    ): array {
        $stage = self::normalizeStage($stage);
        $curriculum = self::curriculumForStudent($studentId);
        $level      = $curriculum['level'];
        $subjects   = $curriculum['subjects'];
        if ($subjects === []) {
            return [
                'groups' => [],
                'total'  => 0.0,
                'count'  => 0,
                'average' => null,
                'grade'  => '—',
                'stage'  => $stage,
            ];
        }

        // Only load grades for subjects returned by offeredSubjectsForStudent()
        $subIds = array_column($subjects, 'id');
        if ($subIds === []) {
            $grades = [];
        } else {
            $sPlace = implode(',', array_fill(0, count($subIds), '?'));
            $params = array_merge([$studentId, $year, $term], $subIds);
            $grades = Database::query(
                "SELECT g.subject_id,
                        MAX(CASE WHEN g.exam_type = 'midterm' THEN g.score END) AS midterm,
                        MAX(CASE WHEN g.exam_type = 'endterm' THEN g.score END) AS endterm
                 FROM grades g
                 WHERE g.student_id = ? AND g.academic_year = ? AND g.term = ?
                   AND g.subject_id IN ($sPlace)
                 GROUP BY g.subject_id",
                $params
            )->fetchAll();
        }

        $byId = [];
        foreach ($grades as $g) {
            $mid = isset($g['midterm']) ? (float) $g['midterm'] : null;
            $end = isset($g['endterm']) ? (float) $g['endterm'] : null;
            // On a mid-term sheet the end-of-term column is dropped here, so
            // nothing downstream can accidentally fold it back in.
            [$mid, $end] = self::componentsForStage($mid, $end, $stage);
            $byId[(int) $g['subject_id']] = [
                'midterm' => $mid,
                'endterm' => $end,
            ];
        }

        $catLabel = [
            'core'     => 'Compulsory Core',
            'science'  => 'Science',
            'arts'     => 'Arts',
            'optional' => 'Optional & Additional',
        ];

        $grouped = [];
        $totalSum = 0.0;
        $maxSum   = 0;
        $subjectCount = 0;

        foreach ($subjects as $sub) {
            $sid = (int) $sub['id'];
            $mid = $byId[$sid]['midterm'] ?? null;
            $end = $byId[$sid]['endterm'] ?? null;
            $total = self::subjectTotal($mid, $end, $stage);
            $max   = self::subjectMax($mid, $end, $stage);
            $pct   = self::subjectPercentage($mid, $end, $stage);

            if ($total !== null && $pct !== null) {
                $totalSum += $total;
                $maxSum   += $max;
                $subjectCount++;
            }

            $cat = $sub['category'] ?: 'optional';
            $grouped[$cat] ??= ['label' => $catLabel[$cat] ?? ucfirst((string) $cat), 'rows' => []];
            $grouped[$cat]['rows'][] = [
                'subject' => $sub['name'],
                'midterm' => $mid,
                'endterm' => $end,
                'total'   => $total,
                'max'     => $max,
                // Legacy column name used by older templates — equals subject total (raw, out of `max`).
                'average' => $total,
                'grade'   => $pct !== null ? self::letterGrade($pct) : '—',
                'remark'  => $pct !== null ? self::remarkForAverage($pct) : '',
            ];
        }

        $order = ['core', 'science', 'arts', 'optional'];
        $sorted = [];
        foreach ($order as $k) {
            if (isset($grouped[$k])) {
                $sorted[$k] = $grouped[$k];
            }
        }
        foreach ($grouped as $k => $v) {
            if (!isset($sorted[$k])) {
                $sorted[$k] = $v;
            }
        }

        // The average is the total scored across every subject as a share of
        // what full marks would have been for this level — 1200 for Form 1/2,
        // 800 for Form 3/4 at end of term (see expectedTotal()).
        //
        // The denominator is fixed by level, not by how many subjects are
        // marked, so an ungraded or half-graded subject pulls the average
        // down rather than being left out of the sum. A student with nothing
        // marked at all stays null ("—") rather than 0%.
        $expectedTotal = self::expectedTotal($level, $stage, count($subjects));
        $average = $subjectCount > 0
            ? self::averagePercentage($totalSum, $level, $stage, count($subjects))
            : null;

        return [
            'groups'   => $sorted,
            'total'    => $totalSum,
            // What the total is out of, so the report card reads "947/1200"
            // against the same figure the average is calculated from.
            'maxTotal' => $expectedTotal,
            // The sum of the maxes of only the subjects that were actually
            // marked — kept for callers that need to tell a part-marked
            // sheet from a complete one.
            'markedMax' => $maxSum,
            'count'    => $subjectCount,
            'average'  => $average,
            'grade'    => $average !== null ? self::letterGrade((float) $average) : '—',
            'stage'    => $stage,
            'level'    => $level,
        ];
    }

    /**
     * Competition ranking (1,2,2,4): rank = 1 + number of cohort members with strictly higher average.
     *
     * @param list<array{average:float|int|string|null}> $members Same cohort
     * @return array<int,int> student_id => rank (0 if no average)
     */
    public static function competitionRanksByAverage(array $members): array
    {
        $ranks = [];

        foreach ($members as $row) {
            $sid = (int) ($row['student_id'] ?? 0);
            if ($sid <= 0) {
                continue;
            }
            $myAvg = isset($row['average']) && $row['average'] !== '' && $row['average'] !== null
                ? (float) $row['average'] : null;
            if ($myAvg === null) {
                $ranks[$sid] = 0;
                continue;
            }
            $higher = 0;
            foreach ($members as $other) {
                $oid = (int) ($other['student_id'] ?? 0);
                if ($oid <= 0) {
                    continue;
                }
                $oAvg = isset($other['average']) && $other['average'] !== '' && $other['average'] !== null
                    ? (float) $other['average'] : null;
                if ($oAvg !== null && $oAvg > $myAvg + 1e-9) {
                    $higher++;
                }
            }
            $ranks[$sid] = $higher + 1;
        }

        return $ranks;
    }

    /**
     * Class rank using competition ranking on overall average % (same cohort rules as reports).
     *
     * @return array{position:int|null,cohort:int,cohort_label:string,stream:string}
     */
    public static function classPositionRow(
        int $studentId,
        int $classId,
        string $year,
        string $term,
        ?string $stage = self::STAGE_END
    ): array {
        $stage = self::normalizeStage($stage);
        $student = Database::query(
            'SELECT s.stream, c.level
             FROM students s LEFT JOIN classes c ON c.id = s.class_id
             WHERE s.id = ?',
            [$studentId]
        )->fetch();
        $level  = trim((string) ($student['level'] ?? ''));
        $stream = (string) ($student['stream'] ?? 'none');
        $isUpper = ($level === 'Form 3' || $level === 'Form 4');

        if ($isUpper && ($stream === 'science' || $stream === 'arts')) {
            $sql = 'SELECT id FROM students WHERE class_id = ? AND stream = ? ORDER BY id';
            $peerRows = Database::query($sql, [$classId, $stream])->fetchAll();
            $cohortLabel = ucfirst($stream) . ' stream';
        } else {
            $peerRows = Database::query(
                'SELECT id FROM students WHERE class_id = ? ORDER BY id',
                [$classId]
            )->fetchAll();
            $cohortLabel = 'class';
        }

        $members = [];
        foreach ($peerRows as $pr) {
            $sid = (int) $pr['id'];
            $sheet = self::buildScoreSheet($sid, $year, $term, $stage);
            $avg = $sheet['average'];
            $members[] = ['student_id' => $sid, 'average' => $avg];
        }

        $ranks = self::competitionRanksByAverage($members);
        $position = $ranks[$studentId] ?? null;
        if ($position === 0) {
            $position = null;
        }

        return [
            'position'      => $position,
            'cohort'        => count($peerRows),
            'cohort_label'  => $cohortLabel,
            'stream'        => $stream,
            'stage'         => $stage,
        ];
    }
}
