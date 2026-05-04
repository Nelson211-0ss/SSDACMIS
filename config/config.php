<?php
/**
 * Application config. Values are pulled from environment variables when set,
 * otherwise sane defaults are used. For local XAMPP, defaults usually work.
 *
 * On shared hosting / cPanel, set the DB_* variables in .env (see .env.example)
 * or hard-code them here for the live environment.
 */

// Load .env if present (very small parser, no Composer required).
$envFile = dirname(__DIR__) . '/.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
        $k = trim($k);
        $v = trim($v, " \t\n\r\0\x0B\"'");
        if ($k !== '' && getenv($k) === false) {
            putenv("$k=$v");
            $_ENV[$k] = $v;
        }
    }
}

function env(string $key, $default = null) {
    $v = getenv($key);
    return $v === false ? $default : $v;
}

return [
    'app' => [
        'name'     => env('APP_NAME', 'SSD-ACMIS — School Management System'),
        'env'      => env('APP_ENV', 'local'),       // local | production
        'debug'    => filter_var(env('APP_DEBUG', 'true'), FILTER_VALIDATE_BOOLEAN),
        // Default assumes the project lives in htdocs/SSDACMIS. The runtime
        // also auto-detects the install path from $_SERVER['SCRIPT_NAME'],
        // so URLs work regardless of the actual folder name.
        'url'      => rtrim(env('APP_URL', 'http://localhost/SSDACMIS/public'), '/'),
        'timezone' => env('APP_TZ', 'Africa/Nairobi'),
        'key'      => env('APP_KEY', 'change-me-in-production'),
    ],
    'db' => [
        'driver'   => env('DB_DRIVER', 'mysql'),
        'host'     => env('DB_HOST', '127.0.0.1'),
        'port'     => env('DB_PORT', '3306'),
        // Default DB name is 'ssdacmis'. If you're upgrading an install that
        // still uses the old name, run database/rename_to_ssdacmis.php once
        // (or set DB_NAME=schoolreg in .env to keep the old database).
        'database' => env('DB_NAME', 'ssdacmis'),
        'username' => env('DB_USER', 'root'),
        'password' => env('DB_PASS', ''),
        'charset'  => 'utf8mb4',
    ],
    'session' => [
        // New session cookie name. Changing this from the old 'schoolreg_sid'
        // simply logs everyone out once; nothing breaks.
        'name'     => env('SESSION_NAME', 'ssdacmis_sid'),
        'lifetime' => (int) env('SESSION_LIFETIME', 7200),
    ],
];
