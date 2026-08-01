<?php
namespace App\Services;

use App\Core\Database;
use App\Core\Settings;
use PDO;

/**
 * Persists per-subject totals/grades and per-student averages/class positions after marks change.
 */
final class TermResultsService
{
    public static function ensureTables(): void
    {
        try {
            Database::connection()->exec(
                'CREATE TABLE IF NOT EXISTS term_subject_results (
                    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    student_id     INT UNSIGNED NOT NULL,
                    class_id       INT UNSIGNED NOT NULL,
                    subject_id     INT UNSIGNED NOT NULL,
                    academic_year  VARCHAR(9)   NOT NULL,
                    term           VARCHAR(20)  NOT NULL,
                    mid_marks      DECIMAL(5,2) NULL,
                    end_marks      DECIMAL(5,2) NULL,
                    total_marks    DECIMAL(5,2) NULL,
                    letter_grade   VARCHAR(10)  NULL,
                    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_term_subject_student (student_id, subject_id, academic_year, term),
                    KEY idx_term_class_period (class_id, academic_year, term),
                    CONSTRAINT fk_tsr_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
                    CONSTRAINT fk_tsr_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
                    CONSTRAINT fk_tsr_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
                ) ENGINE=InnoDB'
            );
            Database::connection()->exec(
                'CREATE TABLE IF NOT EXISTS term_student_results (
                    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    student_id           INT UNSIGNED NOT NULL,
                    class_id             INT UNSIGNED NOT NULL,
                    academic_year        VARCHAR(9)   NOT NULL,
                    term                 VARCHAR(20)  NOT NULL,
                    subjects_with_totals INT UNSIGNED NOT NULL DEFAULT 0,
                    average_percentage   DECIMAL(6,2) NULL,
                    class_position       INT UNSIGNED NULL,
                    rank_cohort          VARCHAR(20) NOT NULL DEFAULT \'class\',
                    updated_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_term_student (student_id, academic_year, term),
                    KEY idx_tst_class_period (class_id, academic_year, term),
                    CONSTRAINT fk_tsi_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
                    CONSTRAINT fk_tsi_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
                ) ENGINE=InnoDB'
            );
        } catch (\Throwable $e) {
            // migrations handle installs
        }
    }

    /** Recompute aggregates for everyone in the class + period (positions within stream for Form 3/4). */
    public static function syncClass(int $classId, string $year, string $term): void
    {
        self::ensureTables();

        $class = Database::query('SELECT level FROM classes WHERE id = ?', [$classId])->fetch();
        $level = trim((string) ($class['level'] ?? ''));
        $isUpper = ($level === 'Form 3' || $level === 'Form 4');

        $students = Database::query(
            'SELECT id, stream FROM students WHERE class_id = ? ORDER BY id',
            [$classId]
        )->fetchAll();

        if (!$students) {
            self::purgeClassPeriod($classId, $year, $term);
            return;
        }

        // Settings/grading may run CREATE TABLE (DDL); MySQL implicitly commits any
        // open transaction — must happen before beginTransaction().
        Settings::ensureTable();
        AcademicMarking::gradingTiers();

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            self::purgeClassPeriodScoped($pdo, $classId, $year, $term);

            $stmtInsSub = $pdo->prepare(
                'INSERT INTO term_subject_results
                    (student_id, class_id, subject_id, academic_year, term, mid_marks, end_marks, total_marks, letter_grade)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $membersByGroup = [];
            $curriculumCounts = [];
            foreach ($students as $st) {
                $studentId = (int) $st['id'];
                $stream = (string) ($st['stream'] ?? 'none');

                $cohort = 'class';
                if ($isUpper && ($stream === 'science' || $stream === 'arts')) {
                    $cohort = $stream;
                }
                $membersByGroup[$cohort][] = ['student_id' => $studentId, 'stream' => $stream];

                $subs = AcademicMarking::offeredSubjectsForStudent($studentId);
                // The full curriculum for this student (core + their stream +
                // optional) — the average below is divided by THIS count, not
                // by how many subjects happen to have marks so far, so a
                // student with 2 of 8 subjects graded isn't ranked only on
                // their best 2.
                $curriculumCounts[$studentId] = count($subs);
                $subIds = array_map(static fn ($s) => (int) $s['id'], $subs);
                if ($subIds === []) {
                    continue;
                }

                $place = implode(',', array_fill(0, count($subIds), '?'));
                $grades = Database::query(
                    "SELECT subject_id,
                            MAX(CASE WHEN exam_type = 'midterm' THEN score END) AS midterm,
                            MAX(CASE WHEN exam_type = 'endterm' THEN score END) AS endterm
                     FROM grades
                     WHERE student_id = ? AND academic_year = ? AND term = ?
                       AND subject_id IN ($place)
                     GROUP BY subject_id",
                    array_merge([$studentId, $year, $term], $subIds)
                )->fetchAll();

                $bySub = [];
                foreach ($grades as $g) {
                    $bySub[(int) $g['subject_id']] = $g;
                }

                foreach ($subs as $sub) {
                    $bid = (int) $sub['id'];
                    $mid = isset($bySub[$bid]['midterm']) ? (float) $bySub[$bid]['midterm'] : null;
                    $end = isset($bySub[$bid]['endterm']) ? (float) $bySub[$bid]['endterm'] : null;

                    if ($mid === null && $end === null) {
                        continue;
                    }

                    $midSql = $mid !== null ? round($mid, 2) : null;
                    $endSql = $end !== null ? round($end, 2) : null;
                    $total = AcademicMarking::subjectTotal($mid, $end);
                    // Grade off the percentage (not the raw total) so a mid-only
                    // subject — stored and shown out of 30 — is graded exactly as
                    // if it were graded out of 30, not misread against a /100 scale.
                    $pct = AcademicMarking::subjectPercentage($mid, $end);
                    $letter = $pct !== null ? AcademicMarking::letterGrade($pct) : null;

                    $stmtInsSub->execute([
                        $studentId,
                        $classId,
                        $bid,
                        $year,
                        $term,
                        $midSql,
                        $endSql,
                        $total !== null ? round($total, 2) : null,
                        $letter,
                    ]);
                }
            }

            $stmtInsSt = $pdo->prepare(
                'INSERT INTO term_student_results
                    (student_id, class_id, academic_year, term, subjects_with_totals, average_percentage, class_position, rank_cohort)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );

            foreach ($membersByGroup as $cohortKey => $memberRows) {
                $averages = [];
                foreach ($memberRows as $meta) {
                    $sid = (int) $meta['student_id'];
                    // Sum the PERCENTAGE of each graded subject (not the raw
                    // total_marks) — a mid-only subject is stored out of 30, a
                    // complete one out of 100, so summing raw totals directly
                    // would silently corrupt the average the moment a student
                    // has a mix of the two. mid_marks/end_marks (already
                    // stored per row) tell us which denominator applied.
                    $sumRow = Database::query(
                        'SELECT COUNT(*) AS n, COALESCE(SUM(
                            CASE
                                WHEN mid_marks IS NOT NULL AND end_marks IS NOT NULL THEN total_marks
                                WHEN mid_marks IS NOT NULL THEN mid_marks / ' . AcademicMarking::MID_MAX . ' * 100
                                WHEN end_marks IS NOT NULL THEN end_marks / ' . AcademicMarking::END_MAX . ' * 100
                                ELSE NULL
                            END
                         ), 0) AS s
                         FROM term_subject_results
                         WHERE student_id = ? AND academic_year = ? AND term = ?
                           AND total_marks IS NOT NULL',
                        [$sid, $year, $term]
                    )->fetch();
                    $gradedCount = (int) ($sumRow['n'] ?? 0);
                    $pctSum = (float) ($sumRow['s'] ?? 0);
                    // Divide by the FULL curriculum count, not by how many
                    // subjects are graded — an ungraded subject counts as 0
                    // rather than being skipped, so the average reflects
                    // every subject the student is meant to take. A student
                    // with no marks in anything yet stays null ("—"), not 0%.
                    $curriculumCount = $curriculumCounts[$sid] ?? 0;
                    $avg = ($gradedCount > 0 && $curriculumCount > 0)
                        ? round($pctSum / $curriculumCount, 2)
                        : null;
                    $averages[$sid] = $avg;
                }

                $rankInput = [];
                foreach ($memberRows as $meta) {
                    $sid = (int) $meta['student_id'];
                    $rankInput[] = [
                        'student_id' => $sid,
                        'average'  => $averages[$sid],
                    ];
                }
                $ranks = AcademicMarking::competitionRanksByAverage($rankInput);

                foreach ($memberRows as $meta) {
                    $sid = (int) $meta['student_id'];
                    $avg = $averages[$sid];
                    $n = Database::query(
                        'SELECT COUNT(*) FROM term_subject_results
                         WHERE student_id = ? AND academic_year = ? AND term = ? AND total_marks IS NOT NULL',
                        [$sid, $year, $term]
                    )->fetchColumn();
                    $cnt = (int) $n;
                    $pos = $ranks[$sid] ?? null;
                    if ($avg === null || $cnt === 0) {
                        $pos = null;
                    }

                    $stmtInsSt->execute([
                        $sid,
                        $classId,
                        $year,
                        $term,
                        $cnt,
                        $avg,
                        $pos,
                        $cohortKey,
                    ]);
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function purgeClassPeriodScoped(PDO $pdo, int $classId, string $year, string $term): void
    {
        $pdo->prepare(
            'DELETE FROM term_subject_results WHERE class_id = ? AND academic_year = ? AND term = ?'
        )->execute([$classId, $year, $term]);
        $pdo->prepare(
            'DELETE FROM term_student_results WHERE class_id = ? AND academic_year = ? AND term = ?'
        )->execute([$classId, $year, $term]);
    }

    private static function purgeClassPeriod(int $classId, string $year, string $term): void
    {
        $pdo = Database::connection();
        self::purgeClassPeriodScoped($pdo, $classId, $year, $term);
    }
}
