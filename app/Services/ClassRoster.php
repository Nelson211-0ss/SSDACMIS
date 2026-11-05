<?php
namespace App\Services;

use App\Core\Database;

/**
 * Who was in a class during a given academic year.
 *
 * Students only carry their CURRENT class (students.class_id). Once a year
 * has been promoted (see PromotionService), last year's Form 1 is now full
 * of this year's intake, so "students WHERE class_id = ?" would give the
 * wrong people for last year's marks, results and report cards.
 *
 * PromotionService records each student's class for the year being closed
 * in student_class_history. For a class + year with history this returns:
 *
 *   - everyone recorded as being in that class that year, plus
 *   - current members of the class with no history row for that year who
 *     DO have marks in it (a late joiner the snapshot missed),
 *
 * and for a class + year without any history it is exactly the old
 * behaviour: the class's current students.
 */
final class ClassRoster
{
    /** @var array<string,bool> */
    private static array $hasHistory = [];
    private static ?bool $tableExists = null;

    /**
     * SQL condition (plus its params) selecting the class's students for the
     * year, meant to replace "class_id = ?" in a query over `students`.
     *
     * @param string $alias table alias of `students` in the query ('' = none)
     * @return array{0:string,1:array}
     */
    public static function where(int $classId, string $year, string $alias = ''): array
    {
        $p = $alias !== '' ? $alias . '.' : '';
        if (!self::hasHistory($classId, $year)) {
            return ["{$p}class_id = ?", [$classId]];
        }
        return [
            "({$p}id IN (SELECT h.student_id FROM student_class_history h WHERE h.class_id = ? AND h.academic_year = ?)"
            . " OR ({$p}class_id = ?"
            . " AND {$p}id NOT IN (SELECT h2.student_id FROM student_class_history h2 WHERE h2.academic_year = ?)"
            . " AND {$p}id IN (SELECT g.student_id FROM grades g WHERE g.academic_year = ?)))",
            [$classId, $year, $classId, $year, $year],
        ];
    }

    /** True when the class's roster for that year has been recorded. */
    public static function hasHistory(int $classId, string $year): bool
    {
        $key = $classId . '|' . $year;
        if (array_key_exists($key, self::$hasHistory)) {
            return self::$hasHistory[$key];
        }
        if (!self::tableExists()) {
            return self::$hasHistory[$key] = false;
        }
        $row = Database::query(
            'SELECT 1 FROM student_class_history WHERE class_id = ? AND academic_year = ? LIMIT 1',
            [$classId, $year]
        )->fetch();
        return self::$hasHistory[$key] = (bool) $row;
    }

    /**
     * The class a student was recorded in for $year, or null when no history
     * exists (then their current class is the right answer).
     */
    public static function classOfStudent(int $studentId, string $year): ?int
    {
        if (!self::tableExists()) return null;
        $row = Database::query(
            'SELECT class_id FROM student_class_history WHERE student_id = ? AND academic_year = ? LIMIT 1',
            [$studentId, $year]
        )->fetch();
        return $row ? (int) $row['class_id'] : null;
    }

    /**
     * Where a student was placed for $year: the class from the history, and
     * the stream they had before that year's promotion moved them (a Form 3
     * student moved down to Form 2 loses their stream today, but last year's
     * report still needs it). Null when there is no history for the year.
     *
     * @return array{class_id:int,stream:?string}|null
     */
    public static function placement(int $studentId, string $year): ?array
    {
        if (!self::tableExists()) return null;
        $row = Database::query(
            "SELECT h.class_id,
                    (SELECT p.from_stream FROM student_promotions p
                     WHERE p.student_id = h.student_id AND p.academic_year = h.academic_year
                       AND p.undone_at IS NULL
                     ORDER BY p.id DESC LIMIT 1) AS from_stream
             FROM student_class_history h
             WHERE h.student_id = ? AND h.academic_year = ?
             LIMIT 1",
            [$studentId, $year]
        )->fetch();
        if (!$row) return null;
        return [
            'class_id' => (int) $row['class_id'],
            'stream'   => $row['from_stream'] !== null ? (string) $row['from_stream'] : null,
        ];
    }

    /** Forget cached lookups (after a promotion writes new history). */
    public static function reset(): void
    {
        self::$hasHistory = [];
        self::$tableExists = null;
    }

    private static function tableExists(): bool
    {
        if (self::$tableExists !== null) return self::$tableExists;
        $row = Database::query(
            "SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_class_history' LIMIT 1"
        )->fetch();
        return self::$tableExists = (bool) $row;
    }
}
