<?php
// Usage: php scripts/dedupe_subjects.php
require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;

$pdo = Database::connection();
$report = [];

$groups = $pdo->query(
    "SELECT school_id, name, IFNULL(code,'') AS code, IFNULL(category,'') AS category,
            GROUP_CONCAT(id ORDER BY id) AS ids, COUNT(*) AS c
     FROM subjects
     GROUP BY school_id, name, code, category
     HAVING c > 1"
)->fetchAll(PDO::FETCH_ASSOC);

if (!$groups) {
    echo "No duplicate subject groups found.\n";
    exit(0);
}

foreach ($groups as $g) {
    $ids = array_map('intval', explode(',', $g['ids']));
    $keep = array_shift($ids);
    if (!$keep) continue;

    $dupList = $ids;
    if (!$dupList) continue;

    echo "Merging duplicates for school_id={$g['school_id']} name={$g['name']} code={$g['code']} category={$g['category']}\n";
    echo "  keep id={$keep}; remove ids=" . implode(',', $dupList) . "\n";

    try {
        $pdo->beginTransaction();

        // Reassign grades
        $stmt = $pdo->prepare('UPDATE grades SET subject_id = ? WHERE subject_id = ?');
        foreach ($dupList as $d) {
            $stmt->execute([$keep, $d]);
        }

        // Reassign staff_subjects
        $stmt = $pdo->prepare('UPDATE staff_subjects SET subject_id = ? WHERE subject_id = ?');
        foreach ($dupList as $d) {
            $stmt->execute([$keep, $d]);
        }

        // Reassign teaching_assignments
        $stmt = $pdo->prepare('UPDATE teaching_assignments SET subject_id = ? WHERE subject_id = ?');
        foreach ($dupList as $d) {
            $stmt->execute([$keep, $d]);
        }

        // Reassign term_subject_results
        $stmt = $pdo->prepare('UPDATE term_subject_results SET subject_id = ? WHERE subject_id = ?');
        foreach ($dupList as $d) {
            $stmt->execute([$keep, $d]);
        }

        // Optionally remove duplicate subject rows
        $del = $pdo->prepare('DELETE FROM subjects WHERE id = ?');
        foreach ($dupList as $d) {
            $del->execute([$d]);
        }

        $pdo->commit();
        echo "  merged and removed duplicates successfully.\n";
    } catch (Throwable $e) {
        $pdo->rollBack();
        echo "  failed: " . $e->getMessage() . "\n";
    }
}

echo "Done. Review changes and re-run if needed.\n";
