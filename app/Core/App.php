<?php
namespace App\Core;

class App
{
    private static array $config = [];

    public static function boot(): void
    {
        self::$config = require dirname(__DIR__, 2) . '/config/config.php';
        date_default_timezone_set(self::config('app.timezone'));

        $debug = (bool) self::config('app.debug');
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('display_startup_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL);

        // Force HTTPS in production when the operator opts in via FORCE_HTTPS=1.
        // We deliberately keep this off-by-default so XAMPP / localhost set-ups
        // still work out of the box.
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        if (!$debug
            && ($_ENV['FORCE_HTTPS'] ?? getenv('FORCE_HTTPS')) === '1'
            && !$isHttps
            && PHP_SAPI !== 'cli'
        ) {
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $uri  = $_SERVER['REQUEST_URI'] ?? '/';
            if ($host !== '') {
                header('Location: https://' . $host . $uri, true, 301);
                exit;
            }
        }

        // Session
        if (session_status() === PHP_SESSION_NONE) {
            session_name(self::config('session.name'));
            session_set_cookie_params([
                'lifetime' => self::config('session.lifetime'),
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => $isHttps,
            ]);
            session_start();
        }

        // Security headers. CSP allows the same Bootstrap/Bootstrap-Icons CDNs
        // and Google Fonts already used by the layout (jsdelivr, gstatic) plus
        // self-hosted assets and inline styles needed by some Bootstrap
        // utilities. Tighten further only after auditing every external host.
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: same-origin');
        header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
        if ($isHttps) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        header(
            "Content-Security-Policy: " .
            "default-src 'self'; " .
            "img-src 'self' data: blob:; " .
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; " .
            "font-src 'self' data: https://cdn.jsdelivr.net https://fonts.gstatic.com; " .
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
            "media-src 'self' blob:; " .
            "frame-ancestors 'self'; " .
            "base-uri 'self'; " .
            "form-action 'self'"
        );
    }

    /**
     * Dot-notation config access: App::config('db.host')
     */
    public static function config(string $key, $default = null)
    {
        $segments = explode('.', $key);
        $value = self::$config;
        foreach ($segments as $s) {
            if (!is_array($value) || !array_key_exists($s, $value)) {
                return $default;
            }
            $value = $value[$s];
        }
        return $value;
    }
}
