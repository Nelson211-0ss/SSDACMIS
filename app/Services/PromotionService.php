<?php
namespace App\Services;

use App\Core\Database;

/**
 * End-of-year class movement: promote, repeat, demote, graduate.
 *
 * Tables (created on first use, also in database/schema.sql):
 *
 *   student_class_history  one row per student per academic year: the class
 *                          they were in that year. Written before anyone is
 *                          moved, so last year's marks, results and report
 *                          cards keep their real roster (see ClassRoster).
 *   student_promotions     audit trail of every decision, grouped by batch
 *                          so a whole run can be undone.
 *
 * students.status ('active' | 'graduated') marks students who finished the
 * final class; graduates keep all their records but leave every class list.
 *
 * Each student can be moved at most once per academic year. That stops a
 * class promoted from Form 1 into Form 2 from being swept straight on into
 * Form 3 when Form 2 is promoted afterwards.
 */
final class PromotionService
{
    public const ACTIONS = ['promote', 'repeat', 'demote', 'graduate', 'skip'];
    public const FINAL_LEVEL = 4;

    private static bool $schemaReady = false;

    /**
     * Create the tables/column if missing. DDL commits implicitly in MySQL,
     * so this must run before any transaction is opened.
     */
    public static function ensureSchema(): void
    {
        if (self::$schemaReady) return;
        $pdo = Database::connection();

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS student_class_history (
                id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
                school_id     INT UNSIGNED NOT NULL DEFAULT 1,
                student_id    INT UNSIGNED NOT NULL,
                academic_year VARCHAR(9)   NOT NULL,
                class_id      INT UNSIGNED NOT NULL,
                created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_history_student_year (student_id, academic_year),
                KEY idx_history_class_year (class_id, academic_year),
                KEY idx_history_school_year (school_id, academic_year),
                CONSTRAINT fk_history_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
                CONSTRAINT fk_history_class   FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE CASCADE
            ) ENGINE=InnoDB"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS student_promotions (
                id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
                school_id     INT UNSIGNED NOT NULL DEFAULT 1,
                batch_id      VARCHAR(32)  NOT NULL,
                student_id    INT UNSIGNED NOT NULL,
                academic_year VARCHAR(9)   NOT NULL,
                action        VARCHAR(12)  NOT NULL,
                from_class_id INT UNSIGNED NULL,
                to_class_id   INT UNSIGNED NULL,
                from_stream   VARCHAR(10)  NOT NULL DEFAULT 'none',
                performed_by  INT UNSIGNED NULL,
                created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                undone_at     TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_promo_student_year (student_id, academic_year),
                KEY idx_promo_batch (batch_id),
                KEY idx_promo_school_year (school_id, academic_year),
                CONSTRAINT fk_promo_student FOREIGN KEY (student_id)    REFERENCES students(id) ON DELETE CASCADE,
                CONSTRAINT fk_promo_from    FOREIGN KEY (from_class_id) REFERENCES classes(id)  ON DELETE SET NULL,
                CONSTRAINT fk_promo_to      FOREIGN KEY (to_class_id)   REFERENCES classes(id)  ON DELETE SET NULL
            ) ENGINE=InnoDB"
        );

        if (!self::hasStatusColumn()) {
            $pdo->exec(
                "ALTER TABLE students
                 ADD COLUMN status VARCHAR(12) NOT NULL DEFAULT 'active' AFTER stream"
            );
        }

        self::$schemaReady = true;
        ClassRoster::reset();
    }

    public static function hasStatusColumn(): bool
    {
        static $has = null;
        if ($has === true) return true;
        $row = Database::query(
            "SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND COLUMN_NAME = 'status' LIMIT 1"
        )->fetch();
        return $has = (bool) $row;
    }

    /** 'Form 3' -> 3; null when the class has no recognisable level. */
    public static function levelNumber(?string $level): ?int
    {
        if (preg_match('~(\d+)~', (string) $level, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    public static function isUpperLevel(?string $level): bool
    {
        $n = self::levelNumber($level);
        return $n !== null && $n >= 3;
    }

    /**
     * Best guess for the class one level up (or down) from $class: same
     * school, level +/- 1, preferring the one whose name keeps the same
     * stream suffix ("Form 1A" -> "Form 2A"; "S1 Blue" -> "S2 Blue").
     *
     * @param array<int,array> $classes all classes of the school
     */
    public static function guessNeighbour(array $class, array $classes, int $step): ?array
    {
        $n = self::levelNumber($class['level'] ?? null);
        if ($n === null) return null;
        $want = $n + $step;
        $suffix = self::nameSuffix((string) $class['name']);
        $candidates = array_values(array_filter(
            $classes,
            static fn ($c) => self::levelNumber($c['level'] ?? null) === $want
        ));
        if (!$candidates) return null;
        foreach ($candidates as $c) {
            if (self::nameSuffix((string) $c['name']) === $suffix) return $c;
        }
        return $candidates[0];
    }

    /** Whatever follows the level digit in a class name, normalised. */
    private static function nameSuffix(string $name): string
    {
        if (preg_match('~\d+(.*)$~', $name, $m)) {
            return strtolower(trim($m[1]));
        }
        return '';
    }

    /**
     * Students already moved for $year (and not undone), keyed by student id,
     * with where they came from.
     *
     * @param int[] $studentIds
     * @return array<int,array>
     */
    public static function processedFor(array $studentIds, string $year): array
    {
        if (!$studentIds) return [];
        $ph = implode(',', array_fill(0, count($studentIds), '?'));
        $rows = Database::query(
            "SELECT p.student_id, p.action, p.from_class_id, p.to_class_id, fc.name AS from_class_name
             FROM student_promotions p
             LEFT JOIN classes fc ON fc.id = p.from_class_id
             WHERE p.academic_year = ? AND p.undone_at IS NULL AND p.student_id IN ($ph)",
            array_merge([$year], array_map('intval', $studentIds))
        )->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['student_id']] = $r;
        }
        return $out;
    }

    /**
     * Apply one class's decisions for the academic year being closed.
     *
     * @param array $class      the source class row (id, school_id, level, name)
     * @param array $decisions  student_id => ['action' => ..., 'stream' => ...]
     * @param array|null $promoteTo target class row for 'promote'
     * @param array|null $demoteTo  target class row for 'demote'
     * @return array{counts:array<string,int>,errors:string[],needStream:int,batch:string}
     */
    public static function process(
        array $class,
        string $year,
        array $decisions,
        ?array $promoteTo,
        ?array $demoteTo,
        ?int $userId
    ): array {
        self::ensureSchema();

        $classId  = (int) $class['id'];
        $schoolId = (int) $class['school_id'];
        $isFinal  = self::levelNumber($class['level'] ?? null) === self::FINAL_LEVEL;

        $counts = array_fill_keys(self::ACTIONS, 0);
        $errors = [];
        $needStream = 0;

        // Only students who are in this class right now can be moved from it.
        $current = Database::query(
            "SELECT id, stream, first_name, last_name FROM students WHERE class_id = ? AND school_id = ?",
            [$classId, $schoolId]
        )->fetchAll();
        $byId = [];
        foreach ($current as $st) {
            $byId[(int) $st['id']] = $st;
        }
        $already = self::processedFor(array_keys($byId), $year);

        $plan = [];
        foreach ($decisions as $sid => $d) {
            $sid = (int) $sid;
            $action = (string) ($d['action'] ?? 'skip');
            if (!isset($byId[$sid]) || !in_array($action, self::ACTIONS, true) || $action === 'skip') {
                continue;
            }
            if (isset($already[$sid])) {
                continue; // locked in the form too; ignore tampered posts
            }
            if ($action === 'promote' && !$promoteTo) {
                $errors[] = 'Choose the class to promote into.';
                continue;
            }
            if ($action === 'demote' && !$demoteTo) {
                $errors[] = 'Choose the class to demote into.';
                continue;
            }
            if ($action === 'graduate' && !$isFinal) {
                continue;
            }
            $plan[$sid] = ['action' => $action, 'stream' => (string) ($d['stream'] ?? '')];
        }
        $errors = array_values(array_unique($errors));
        if (!$plan) {
            return ['counts' => $counts, 'errors' => $errors, 'needStream' => 0, 'batch' => ''];
        }

        // Freeze who was in which class this year BEFORE anybody moves, for
        // every student in the school with marks this year. INSERT IGNORE
        // keeps rows written by an earlier class's run (one row per student
        // per year), so promoting Form 1 first doesn't relabel them as Form 2.
        $pdo = Database::connection();
        $pdo->prepare(
            "INSERT IGNORE INTO student_class_history (school_id, student_id, academic_year, class_id)
             SELECT s.school_id, s.id, ?, s.class_id
             FROM students s
             WHERE s.school_id = ? AND s.class_id IS NOT NULL
               AND s.id IN (SELECT g.student_id FROM grades g WHERE g.academic_year = ?)"
        )->execute([$year, $schoolId, $year]);

        $batch = bin2hex(random_bytes(8));

        $pdo->beginTransaction();
        try {
            $hist = $pdo->prepare(
                "INSERT IGNORE INTO student_class_history (school_id, student_id, academic_year, class_id)
                 VALUES (?, ?, ?, ?)"
            );
            $move = $pdo->prepare("UPDATE students SET class_id = ?, stream = ? WHERE id = ?");
            $grad = $pdo->prepare("UPDATE students SET class_id = NULL, status = 'graduated' WHERE id = ?");
            $log  = $pdo->prepare(
                "INSERT INTO student_promotions
                    (school_id, batch_id, student_id, academic_year, action, from_class_id, to_class_id, from_stream, performed_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            foreach ($plan as $sid => $p) {
                $action = $p['action'];
                $fromStream = (string) ($byId[$sid]['stream'] ?? 'none');
                $toClassId = null;

                $hist->execute([$schoolId, $sid, $year, $classId]);

                if ($action === 'promote' || $action === 'demote') {
                    $target = $action === 'promote' ? $promoteTo : $demoteTo;
                    $toClassId = (int) $target['id'];
                    if (self::isUpperLevel($target['level'] ?? null)) {
                        $stream = in_array($p['stream'], ['science', 'arts'], true)
                            ? $p['stream']
                            : ($fromStream === 'science' || $fromStream === 'arts' ? $fromStream : 'none');
                        if ($stream === 'none') $needStream++;
                    } else {
                        $stream = 'none'; // Form 1/2 are not streamed
                    }
                    $move->execute([$toClassId, $stream, $sid]);
                } elseif ($action === 'graduate') {
                    $grad->execute([$sid]);
                } elseif ($action === 'repeat') {
                    $toClassId = $classId;
                }

                $log->execute([$schoolId, $batch, $sid, $year, $action, $classId, $toClassId, $fromStream, $userId]);
                $counts[$action]++;
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        ClassRoster::reset();
        return ['counts' => $counts, 'errors' => $errors, 'needStream' => $needStream, 'batch' => $batch];
    }

    /**
     * Reverse one batch. A student is only put back if nothing else has
     * moved them since (still in the class they were sent to).
     *
     * @return array{restored:int,skipped:int,year:string}
     */
    public static function undo(string $batch, ?int $schoolId): array
    {
        self::ensureSchema();
        $sf = $schoolId !== null ? ' AND p.school_id = ?' : '';
        $rows = Database::query(
            "SELECT p.*, s.class_id AS current_class_id, s.status AS current_status
             FROM student_promotions p
             JOIN students s ON s.id = p.student_id
             WHERE p.batch_id = ? AND p.undone_at IS NULL{$sf}",
            $schoolId !== null ? [$batch, $schoolId] : [$batch]
        )->fetchAll();

        $restored = 0;
        $skipped = 0;
        $year = '';
        $rowSchool = null;
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $back  = $pdo->prepare("UPDATE students SET class_id = ?, stream = ?, status = 'active' WHERE id = ?");
            $done  = $pdo->prepare("UPDATE student_promotions SET undone_at = NOW() WHERE id = ?");
            $unhist = $pdo->prepare("DELETE FROM student_class_history WHERE student_id = ? AND academic_year = ?");

            foreach ($rows as $r) {
                $year = (string) $r['academic_year'];
                $rowSchool = (int) $r['school_id'];
                $sid = (int) $r['student_id'];
                $from = $r['from_class_id'] !== null ? (int) $r['from_class_id'] : null;
                $ok = false;

                switch ($r['action']) {
                    case 'promote':
                    case 'demote':
                        $ok = $from !== null && (int) $r['current_class_id'] === (int) $r['to_class_id'];
                        break;
                    case 'graduate':
                        $ok = $from !== null && $r['current_class_id'] === null
                            && (string) $r['current_status'] === 'graduated';
                        break;
                    case 'repeat':
                        $ok = true;
                        break;
                }
                if (!$ok) {
                    $skipped++;
                    continue;
                }
                if ($r['action'] !== 'repeat') {
                    $back->execute([$from, (string) $r['from_stream'], $sid]);
                }
                $done->execute([(int) $r['id']]);
                $unhist->execute([$sid, $year]);
                $restored++;
            }

            // Nothing left promoted for that year in this school: drop the
            // year's snapshot entirely so rosters go back to exactly how they
            // were before any promotion was run.
            if ($restored > 0 && $rowSchool !== null) {
                $left = $pdo->prepare(
                    "SELECT 1 FROM student_promotions
                     WHERE school_id = ? AND academic_year = ? AND undone_at IS NULL LIMIT 1"
                );
                $left->execute([$rowSchool, $year]);
                if (!$left->fetchColumn()) {
                    $pdo->prepare("DELETE FROM student_class_history WHERE school_id = ? AND academic_year = ?")
                        ->execute([$rowSchool, $year]);
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        ClassRoster::reset();
        return ['restored' => $restored, 'skipped' => $skipped, 'year' => $year];
    }

    /**
     * Recent runs for the school, newest first, with per-action counts.
     */
    public static function recentBatches(?int $schoolId, int $limit = 15): array
    {
        self::ensureSchema();
        $sf = $schoolId !== null ? ' WHERE p.school_id = ?' : '';
        return Database::query(
            "SELECT p.batch_id, p.academic_year, MIN(p.created_at) AS created_at,
                    MAX(fc.name) AS from_class_name,
                    MAX(u.name) AS performed_by_name,
                    SUM(p.action = 'promote')  AS n_promote,
                    SUM(p.action = 'repeat')   AS n_repeat,
                    SUM(p.action = 'demote')   AS n_demote,
                    SUM(p.action = 'graduate') AS n_graduate,
                    SUM(p.undone_at IS NULL)   AS n_active
             FROM student_promotions p
             LEFT JOIN classes fc ON fc.id = p.from_class_id
             LEFT JOIN users u ON u.id = p.performed_by
             {$sf}
             GROUP BY p.batch_id, p.academic_year
             ORDER BY created_at DESC
             LIMIT " . max(1, min(100, $limit)),
            $schoolId !== null ? [$schoolId] : []
        )->fetchAll();
    }
}
