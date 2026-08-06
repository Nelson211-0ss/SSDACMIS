<?php
/**
 * Recompute published results for every class and period that has marks.
 *
 * Needed once after upgrading to per-stage results: existing installs only
 * hold the end-of-term set, and the mid-term set (each subject out of 30) is
 * written the next time a class's marks are saved. This backfills all of them
 * in one go. Safe to re-run — syncClass() replaces a class+period's rows.
 *
 * Usage: php scripts/resync_results.php
 */
require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;
use App\Services\TermResultsService;

TermResultsService::ensureTables();

$periods = Database::query(
    'SELECT DISTINCT s.class_id, g.academic_year, g.term
     FROM grades g
     JOIN students s ON s.id = g.student_id
     WHERE s.class_id IS NOT NULL
     ORDER BY s.class_id, g.academic_year, g.term'
)->fetchAll();

if (!$periods) {
    echo "No marks found — nothing to resync.\n";
    exit(0);
}

$done = 0;
foreach ($periods as $p) {
    $classId = (int) $p['class_id'];
    $year    = (string) $p['academic_year'];
    $term    = (string) $p['term'];
    try {
        TermResultsService::syncClass($classId, $year, $term);
        $done++;
        echo "  ok  class {$classId} · {$year} · {$term}\n";
    } catch (Throwable $e) {
        echo "  !!  class {$classId} · {$year} · {$term}: " . $e->getMessage() . "\n";
    }
}

echo "\nResynced {$done} of " . count($periods) . " class-periods (mid-term + end-of-term).\n";
