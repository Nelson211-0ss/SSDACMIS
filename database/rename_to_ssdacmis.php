<?php
/**
 * One-shot, idempotent helper that renames the legacy `schoolreg` database
 * to `ssdacmis`.
 *
 * MariaDB/MySQL no longer supports `RENAME DATABASE`, so this script:
 *   1. Connects to the server (without selecting a database).
 *   2. Creates the target DB if it doesn't exist.
 *   3. Walks every table in the source DB and runs `RENAME TABLE
 *      schoolreg.foo TO ssdacmis.foo` (this moves the table cheaply, keeping
 *      data, indexes, foreign keys, triggers and AUTO_INCREMENT values).
 *   4. Drops the (now empty) source DB.
 *
 * Re-running is safe — every step checks state first.
 *
 * Run:
 *   php database/rename_to_ssdacmis.php
 * or in a browser:
 *   http://localhost/SSDACMIS/database/rename_to_ssdacmis.php
 *
 * After it succeeds, the app will use the new DB automatically (the default
 * DB_NAME in config/config.php is now 'ssdacmis'). If you have a custom .env,
 * remember to update DB_NAME there as well.
 */

declare(strict_types=1);

const SOURCE_DB = 'schoolreg';
const TARGET_DB = 'ssdacmis';

/* --- Bootstrap just enough to read config (no session, no autoloader). --- */
$configPath = __DIR__ . '/../config/config.php';
if (!is_readable($configPath)) {
    fail('config/config.php is missing — run from the project root.');
}
/** @var array{db: array<string,string>} $config */
$config = require $configPath;
$dbCfg  = $config['db'] ?? [];

foreach (['driver', 'host', 'port', 'username', 'charset'] as $required) {
    if (!isset($dbCfg[$required])) {
        fail("Missing config['db'][$required] in config/config.php");
    }
}

$cli  = (PHP_SAPI === 'cli');
$logs = [];
$log  = function (string $msg, string $level = 'info') use (&$logs): void {
    $logs[] = ['level' => $level, 'msg' => $msg];
};

/* --- Connect WITHOUT a database selected (so we can move between DBs). --- */
$dsn = sprintf(
    '%s:host=%s;port=%s;charset=%s',
    $dbCfg['driver'],
    $dbCfg['host'],
    $dbCfg['port'],
    $dbCfg['charset']
);

try {
    $pdo = new PDO($dsn, $dbCfg['username'] ?? '', $dbCfg['password'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fail('Cannot connect to MySQL/MariaDB: ' . $e->getMessage());
}

$log("Connected to {$dbCfg['host']}:{$dbCfg['port']} as {$dbCfg['username']}");

/* --- Discover what we're working with. ----------------------------------- */
$dbExists = function (string $name) use ($pdo): bool {
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ? LIMIT 1'
    );
    $stmt->execute([$name]);
    return (bool) $stmt->fetchColumn();
};

$tablesIn = function (string $db) use ($pdo): array {
    $stmt = $pdo->prepare(
        "SELECT TABLE_NAME
           FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = ?
            AND TABLE_TYPE   = 'BASE TABLE'
          ORDER BY TABLE_NAME"
    );
    $stmt->execute([$db]);
    return array_column($stmt->fetchAll(), 'TABLE_NAME');
};

$sourceExists = $dbExists(SOURCE_DB);
$targetExists = $dbExists(TARGET_DB);

$log('Source DB `' . SOURCE_DB . '`: ' . ($sourceExists ? 'present' : 'absent'));
$log('Target DB `' . TARGET_DB . '`: ' . ($targetExists ? 'present' : 'absent'));

/* --- Short-circuit cases. ------------------------------------------------- */
if (!$sourceExists && $targetExists) {
    $log('Nothing to do — `' . SOURCE_DB . '` is gone and `' . TARGET_DB . '` already exists.', 'ok');
    finish($logs, $cli, true);
}

if (!$sourceExists && !$targetExists) {
    $log('Neither `' . SOURCE_DB . '` nor `' . TARGET_DB . '` exists. Use install.php to create a fresh database, then re-run this script if needed.', 'warn');
    finish($logs, $cli, true);
}

/* --- Make sure the target exists. ---------------------------------------- */
if (!$targetExists) {
    $pdo->exec(
        'CREATE DATABASE `' . TARGET_DB . '`
         DEFAULT CHARACTER SET utf8mb4
         DEFAULT COLLATE utf8mb4_unicode_ci'
    );
    $log('Created `' . TARGET_DB . '`.', 'ok');
} else {
    $log('Reusing existing `' . TARGET_DB . '`.');
}

/* --- Move every base table from source to target. ------------------------ */
$srcTables = $tablesIn(SOURCE_DB);
$dstTables = array_flip($tablesIn(TARGET_DB));

if (!$srcTables) {
    $log('Source `' . SOURCE_DB . '` has no tables left — moving on to drop it.');
} else {
    $log('Moving ' . count($srcTables) . ' table(s) from `' . SOURCE_DB . '` to `' . TARGET_DB . '`...');
}

foreach ($srcTables as $t) {
    if (isset($dstTables[$t])) {
        $log("  --  `$t` already exists in `" . TARGET_DB . "`, skipping.");
        continue;
    }
    try {
        $pdo->exec("RENAME TABLE `" . SOURCE_DB . "`.`$t` TO `" . TARGET_DB . "`.`$t`");
        $log("  ok  moved `$t`");
    } catch (PDOException $e) {
        $log("  !!  failed to move `$t`: " . $e->getMessage(), 'err');
        finish($logs, $cli, false);
    }
}

/* --- Drop the now-empty source DB if it has nothing left. ---------------- */
$leftover = $tablesIn(SOURCE_DB);
if ($leftover) {
    $log('`' . SOURCE_DB . '` still contains: ' . implode(', ', $leftover) .
         '. Resolve manually and re-run.', 'warn');
    finish($logs, $cli, false);
}

if ($dbExists(SOURCE_DB)) {
    $pdo->exec('DROP DATABASE `' . SOURCE_DB . '`');
    $log('Dropped empty `' . SOURCE_DB . '`.', 'ok');
}

$log('Done. The app will now use `' . TARGET_DB . '`. Remember to update DB_NAME in any custom .env file.', 'ok');
finish($logs, $cli, true);

/* ------------------------------------------------------------------ helpers */

function fail(string $msg): never
{
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "ERROR: $msg\n");
    } else {
        http_response_code(500);
        echo "<pre style='font:14px/1.4 monospace;color:#b91c1c'>ERROR: " .
            htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . "</pre>";
    }
    exit(1);
}

function finish(array $logs, bool $cli, bool $ok): never
{
    if ($cli) {
        foreach ($logs as $row) {
            $prefix = match ($row['level']) {
                'ok'   => '[OK]   ',
                'warn' => '[WARN] ',
                'err'  => '[ERR]  ',
                default => '       ',
            };
            echo $prefix . $row['msg'] . "\n";
        }
        exit($ok ? 0 : 1);
    }

    $color = [
        'ok'   => '#15803d',
        'warn' => '#b45309',
        'err'  => '#b91c1c',
        'info' => '#374151',
    ];
    echo "<!doctype html><meta charset='utf-8'><title>Rename schoolreg → ssdacmis</title>";
    echo "<style>body{font:14px/1.55 ui-monospace,Menlo,Consolas,monospace;background:#f8fafc;color:#0f172a;padding:2rem;max-width:780px;margin:auto}h1{font:600 1.4rem system-ui;margin:0 0 1.2rem}div.row{padding:.2rem 0}.tag{display:inline-block;width:4.5rem;font-weight:600}</style>";
    echo "<h1>Rename <code>schoolreg</code> → <code>ssdacmis</code></h1>";
    foreach ($logs as $row) {
        $c   = $color[$row['level']] ?? $color['info'];
        $tag = strtoupper($row['level']);
        echo "<div class='row' style='color:$c'>" .
             "<span class='tag'>[$tag]</span>" .
             htmlspecialchars($row['msg'], ENT_QUOTES, 'UTF-8') .
             "</div>";
    }
    if ($ok) {
        echo "<p style='margin-top:1.5rem;color:#15803d;font-weight:600'>All good.</p>";
    } else {
        echo "<p style='margin-top:1.5rem;color:#b91c1c;font-weight:600'>Aborted with errors above.</p>";
    }
    exit($ok ? 0 : 1);
}
