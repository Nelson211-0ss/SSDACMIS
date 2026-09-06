<?php
/**
 * Move every record filed under one academic year to another.
 *
 * Use this when data ended up under the wrong year — most commonly after the
 * flat-year migration, where marks entered between January and August were
 * stored as "2025/2026" under the old September-rollover convention and so
 * landed on 2025 instead of the 2026 school year they belong to.
 *
 * It RELABELS rows in place. Nothing is deleted, and nothing is created.
 *
 *   # See what years currently hold data
 *   php scripts/remap_academic_year.php --list
 *
 *   # Preview the move (default — changes nothing)
 *   php scripts/remap_academic_year.php --from 2025 --to 2026
 *
 *   # Actually do it
 *   php scripts/remap_academic_year.php --from 2025 --to 2026 --apply
 *
 * If the destination year already holds the same record (same student,
 * subject, term and stage), that row is left where it is and reported, so a
 * merge can never overwrite marks that are already there. Take a backup
 * first:  mysqldump -u root -p ssdacmis > backup.sql
 */

spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'App\\')) return;
    $path = __DIR__ . '/../app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_readable($path)) require $path;
});

use App\Core\App;
use App\Core\Database;

$reflection = new ReflectionClass(App::class);
$cfgProp = $reflection->getProperty('config');
$cfgProp->setAccessible(true);
$cfgProp->setValue(null, require __DIR__ . '/../config/config.php');

$cli = (PHP_SAPI === 'cli');
if (!$cli) {
    header('Content-Type: text/plain; charset=utf-8');
}

/** Every table that files rows under an academic year. */
const YEAR_TABLES = [
    'grades'               => 'Marks',
    'term_subject_results' => 'Per-subject results',
    'term_student_results' => 'Per-student results',
    'fees_structure'       => 'Fees structure',
    'student_fees'         => 'Student bills',
];

/* ------------------------------ arguments ------------------------------ */

$args = [];
foreach (array_slice($argv ?? [], 1) as $i => $a) {
    if (preg_match('~^--([a-z-]+)(?:=(.*))?$~', $a, $m)) {
        $args[$m[1]] = $m[2] ?? true;
    } elseif ($i > 0) {
        // Support "--from 2025" as well as "--from=2025".
        $prev = array_key_last($args);
        if ($prev !== null && $args[$prev] === true) {
            $args[$prev] = $a;
        }
    }
}
// Browser fallback: ?from=2025&to=2026&apply=1
foreach (['list', 'from', 'to', 'apply'] as $k) {
    if (!isset($args[$k]) && isset($_GET[$k])) {
        $args[$k] = $_GET[$k] === '' ? true : $_GET[$k];
    }
}

$pdo = Database::connection();

$tableExists = static function (string $t) use ($pdo): bool {
    $st = $pdo->prepare(
        'SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
    );
    $st->execute([$t]);
    return (bool) $st->fetchColumn();
};

/* -------------------------------- --list ------------------------------- */

$showDistribution = static function () use ($pdo, $tableExists): void {
    echo "Academic years currently holding data\n";
    echo str_repeat('-', 62) . "\n";
    foreach (YEAR_TABLES as $tbl => $label) {
        if (!$tableExists($tbl)) {
            printf("  %-22s (table not present)\n", $label);
            continue;
        }
        $rows = $pdo->query(
            "SELECT academic_year, COUNT(*) AS n FROM `$tbl`
             GROUP BY academic_year ORDER BY academic_year"
        )->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) {
            printf("  %-22s (empty)\n", $label);
            continue;
        }
        $parts = [];
        foreach ($rows as $r) {
            $parts[] = $r['academic_year'] . ': ' . number_format((int) $r['n']);
        }
        printf("  %-22s %s\n", $label, implode('   ', $parts));
    }
    echo "\n";
};

if (!empty($args['list']) || (empty($args['from']) && empty($args['to']))) {
    $showDistribution();
    if (empty($args['from'])) {
        echo "Nothing moved. To move a year, run:\n";
        echo "  php scripts/remap_academic_year.php --from 2025 --to 2026\n";
        echo "…then add --apply once the preview looks right.\n";
        exit(0);
    }
}

$from  = trim((string) ($args['from'] ?? ''));
$to    = trim((string) ($args['to'] ?? ''));
$apply = !empty($args['apply']);

if (!preg_match('~^\d{4}$~', $from) || !preg_match('~^\d{4}$~', $to)) {
    echo "Both --from and --to must be four-digit years, e.g. --from 2025 --to 2026\n";
    exit(1);
}
if ($from === $to) {
    echo "--from and --to are the same year; nothing to do.\n";
    exit(0);
}

/* ------------------------------- the move ------------------------------ */

$showDistribution();

echo ($apply ? "APPLYING" : "PREVIEW (nothing will change — add --apply to run it)")
   . ": {$from} -> {$to}\n";
echo str_repeat('-', 62) . "\n";

$totalMoved  = 0;
$totalStuck  = 0;
$totalBefore = 0;
$totalAfter  = 0;

foreach (YEAR_TABLES as $tbl => $label) {
    if (!$tableExists($tbl)) {
        continue;
    }

    $before = (int) $pdo->query("SELECT COUNT(*) FROM `$tbl`")->fetchColumn();
    $totalBefore += $before;

    $st = $pdo->prepare("SELECT COUNT(*) FROM `$tbl` WHERE academic_year = ?");
    $st->execute([$from]);
    $n = (int) $st->fetchColumn();

    if ($n === 0) {
        printf("  %-22s nothing filed under %s\n", $label, $from);
        $totalAfter += $before;
        continue;
    }

    if (!$apply) {
        // Report how many would collide, without touching anything.
        $st = $pdo->prepare("SELECT COUNT(*) FROM `$tbl` WHERE academic_year = ?");
        $st->execute([$to]);
        $existing = (int) $st->fetchColumn();
        printf(
            "  %-22s would move %s row(s)%s\n",
            $label,
            number_format($n),
            $existing > 0
                ? sprintf(' — note: %s already holds %s row(s), any exact duplicates stay put', $to, number_format($existing))
                : ''
        );
        $totalMoved += $n;
        $totalAfter += $before;
        continue;
    }

    $moved = 0;
    $stuck = 0;
    try {
        $bulk = $pdo->prepare("UPDATE `$tbl` SET academic_year = ? WHERE academic_year = ?");
        $bulk->execute([$to, $from]);
        $moved = $bulk->rowCount();
    } catch (PDOException $e) {
        if ($e->getCode() !== '23000') {
            throw $e;
        }
        // Destination already holds some of these records — move the rest
        // one by one and leave the genuine duplicates alone.
        $ids = $pdo->prepare("SELECT id FROM `$tbl` WHERE academic_year = ?");
        $ids->execute([$from]);
        $one = $pdo->prepare("UPDATE `$tbl` SET academic_year = ? WHERE id = ?");
        foreach ($ids->fetchAll(PDO::FETCH_COLUMN) as $id) {
            try {
                $one->execute([$to, (int) $id]);
                $moved++;
            } catch (PDOException $inner) {
                if ($inner->getCode() !== '23000') {
                    throw $inner;
                }
                $stuck++;
            }
        }
    }

    $after = (int) $pdo->query("SELECT COUNT(*) FROM `$tbl`")->fetchColumn();
    $totalAfter += $after;
    $totalMoved += $moved;
    $totalStuck += $stuck;

    printf(
        "  %-22s moved %s row(s)%s%s\n",
        $label,
        number_format($moved),
        $stuck > 0 ? sprintf('; %s left under %s (already present in %s)', number_format($stuck), $from, $to) : '',
        $after === $before ? '' : sprintf('  !! row count changed %d -> %d', $before, $after)
    );
}

echo str_repeat('-', 62) . "\n";

if (!$apply) {
    printf("Preview only: %s row(s) would move. Re-run with --apply to do it.\n", number_format($totalMoved));
    exit(0);
}

printf("Moved %s row(s) from %s to %s.\n", number_format($totalMoved), $from, $to);
if ($totalStuck > 0) {
    printf(
        "%s row(s) stayed under %s because %s already held the same record.\n"
        . "Nothing was deleted — review those, then re-run if you still want them moved.\n",
        number_format($totalStuck),
        $from,
        $to
    );
}
echo $totalAfter === $totalBefore
    ? "Total row count unchanged ({$totalAfter}) — data relabelled only.\n"
    : "!! Total row count changed: {$totalBefore} -> {$totalAfter}\n";

echo "\n";
$showDistribution();
