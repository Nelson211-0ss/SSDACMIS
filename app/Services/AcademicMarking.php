<?php
namespace App\Services;

use App\Core\Database;
use App\Core\Settings;

/**
 * South Sudan–style ACMIS scoring: Mid-term (×/30) + End-of-term (×/70) = 100 max per subject.
 * Reusable validation, totals, letter grades (configurable), averages, competition ranking (1,2,2,4).
 */
final class AcademicMarking
{
    public const MID_MAX  = 30.0;
    public const END_MAX  = 70.0;
    public const TOTAL_MAX = 100.0;

    public const ERR_MID_HIGH = 'Mid-term marks cannot exceed 30';
    public const ERR_MID_LOW  = 'Mid-term marks cannot be below 0';
    public const ERR_END_HIGH = 'End-of-term marks cannot exceed 70';
    public const ERR_END_LOW  = 'End-of-term marks cannot be below 0';

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
     * Subject total only when BOTH components exist (South Sudan composite).
     */
    public static function subjectTotal(?float $mid, ?float $end): ?float
    {
        if ($mid === null || $end === null) {
            return null;
        }
        return round(min(self::TOTAL_MAX, $mid + $end), 2);
    }

    /**
     * Curriculum subjects for a student (same rules as report cards).
     *
     * @return list<array{id:int,name:string,code:?string,category:string}>
     */
    public static function offeredSubjectsForStudent(int $studentId): array
    {
        $student = Database::query(
            'SELECT s.stream, c.level
             FROM students s LEFT JOIN classes c ON c.id = s.class_id
             WHERE s.id = ?',
            [$studentId]
        )->fetch();
        $level  = trim((string) ($student['level'] ?? ''));
        $stream = (string) ($student['stream'] ?? 'none');

        $sql = 'SELECT id, name, code, category FROM subjects WHERE is_offered = 1';
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

        return Database::query($sql)->fetchAll();
    }

    /**
     * Build score sheet: total per subject = mid + end; average = sum(totals) / subjects counted.
     * Only includes subjects the school offers (`is_offered`) and where at least one component
     * (mid and/or end) has been recorded for this year and term — unmarked subjects are omitted.
     *
     * @return array{
     *   groups: array<string,array{label:string,rows:list<array<string,mixed>>}>,
     *   totalSum: float,
     *   subjectCount: int,
     *   average: float|null,
     *   grade: string
     * }
     */
    public static function buildScoreSheet(int $studentId, string $year, string $term): array
    {
        $subjects = self::offeredSubjectsForStudent($studentId);
        if ($subjects === []) {
            return [
                'groups' => [],
                'total'  => 0.0,
                'count'  => 0,
                'average' => null,
                'grade'  => '—',
            ];
        }

        $grades = Database::query(
            "SELECT g.subject_id,
                    MAX(CASE WHEN g.exam_type = 'midterm' THEN g.score END) AS midterm,
                    MAX(CASE WHEN g.exam_type = 'endterm' THEN g.score END) AS endterm
             FROM grades g
             INNER JOIN subjects sub ON sub.id = g.subject_id AND sub.is_offered = 1
             WHERE g.student_id = ? AND g.academic_year = ? AND g.term = ?
             GROUP BY g.subject_id",
            [$studentId, $year, $term]
        )->fetchAll();

        $byId = [];
        foreach ($grades as $g) {
            $mid = isset($g['midterm']) ? (float) $g['midterm'] : null;
            $end = isset($g['endterm']) ? (float) $g['endterm'] : null;
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
        $subjectCount = 0;

        foreach ($subjects as $sub) {
            $sid = (int) $sub['id'];
            if (!isset($byId[$sid])) {
                continue;
            }
            $mid = $byId[$sid]['midterm'] ?? null;
            $end = $byId[$sid]['endterm'] ?? null;
            if ($mid === null && $end === null) {
                continue;
            }
            $total = self::subjectTotal($mid, $end);

            if ($total !== null) {
                $totalSum += $total;
                $subjectCount++;
            }

            $cat = $sub['category'] ?: 'optional';
            $grouped[$cat] ??= ['label' => $catLabel[$cat] ?? ucfirst((string) $cat), 'rows' => []];
            $grouped[$cat]['rows'][] = [
                'subject' => $sub['name'],
                'midterm' => $mid,
                'endterm' => $end,
                'total'   => $total,
                // Legacy column name used by older templates — equals subject total (mid+end).
                'average' => $total,
                'grade'   => $total !== null ? self::letterGrade($total) : '—',
                'remark'  => $total !== null ? self::remarkForAverage($total) : '',
            ];
        }

        $order = ['core', 'science', 'arts', 'optional'];
        $sorted = [];
        foreach ($order as $k) {
            if (isset($grouped[$k]) && ($grouped[$k]['rows'] ?? []) !== []) {
                $sorted[$k] = $grouped[$k];
            }
        }
        foreach ($grouped as $k => $v) {
            if (isset($sorted[$k]) || ($v['rows'] ?? []) === []) {
                continue;
            }
            $sorted[$k] = $v;
        }

        $average = $subjectCount > 0 ? round($totalSum / $subjectCount, 2) : null;

        return [
            'groups'  => $sorted,
            'total'   => $totalSum,
            'count'   => $subjectCount,
            'average' => $average,
            'grade'   => $average !== null ? self::letterGrade((float) $average) : '—',
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
    public static function classPositionRow(int $studentId, int $classId, string $year, string $term): array
    {
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
            $sheet = self::buildScoreSheet($sid, $year, $term);
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
        ];
    }
}
