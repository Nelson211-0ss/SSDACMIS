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
            $lifetime = (int) self::config('session.lifetime');
            session_name(self::config('session.name'));
            // Don't let PHP's garbage collector delete a session sooner than
            // our own idle timeout (its default is only 24 minutes).
            ini_set('session.gc_maxlifetime', (string) max($lifetime, 1440));
            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => $isHttps,
            ]);
            session_start();
            self::enforceIdleTimeout($lifetime, $isHttps);
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
     * Idle session timeout. A signed-in session that has seen no request for
     * `session.lifetime` seconds is signed out (every portal slot), and a
     * flag is left so the login page can say why. Activity slides the
     * window: each request refreshes the timestamp and the cookie expiry.
     *
     * The /session/status endpoint (polled by the timeout dialog) reads the
     * timer without resetting it, otherwise the check itself would keep the
     * session alive forever.
     */
    private static function enforceIdleTimeout(int $lifetime, bool $isHttps): void
    {
        if ($lifetime <= 0) return;
        $now = time();
        $signedIn = !empty($_SESSION['users']) || !empty($_SESSION['user']);
        $last = (int) ($_SESSION['_last_activity'] ?? 0);

        if ($signedIn && $last > 0 && ($now - $last) > $lifetime) {
            unset($_SESSION['users'], $_SESSION['user'], $_SESSION['_csrf'], $_SESSION['_regenerated_for']);
            $_SESSION['_timed_out'] = true;
            session_regenerate_id(true);
            $signedIn = false;
        }

        if (!$signedIn) {
            unset($_SESSION['_last_activity']);
            return;
        }
        if (self::isSessionStatusRequest()) return;

        $_SESSION['_last_activity'] = $now;
        // Re-issue the cookie so its expiry slides with activity instead of
        // dying a fixed time after login.
        if (PHP_SAPI !== 'cli' && !headers_sent() && ini_get('session.use_cookies')) {
            setcookie(session_name(), session_id(), [
                'expires'  => $now + $lifetime,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => $isHttps,
            ]);
        }
    }

    public static function isSessionStatusRequest(): bool
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        return str_ends_with(rtrim($uri, '/'), '/session/status');
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
