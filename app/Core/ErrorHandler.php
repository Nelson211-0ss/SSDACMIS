<?php
namespace App\Core;

/**
 * Central error / exception plumbing. Goals:
 *
 *   1. Convert every uncaught error or exception into a single 500 response
 *      so the user never sees a half-rendered page or a raw stack trace
 *      leaking SQL, file paths, secrets, etc.
 *   2. Append a structured, single-line entry to storage/logs/app.log so an
 *      operator can audit "what just went wrong?" without trawling Apache
 *      logs. Logs are intentionally plain text (no rotation library) so the
 *      same code works on every shared-hosting environment.
 *   3. In debug mode, re-throw / re-emit so PHP's normal error display still
 *      kicks in for developers.
 */
final class ErrorHandler
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) return;
        self::$registered = true;

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    /**
     * @return bool false to let PHP's standard handler run (when @-suppressed).
     */
    public static function handleError(int $severity, string $message, string $file = '', int $line = 0): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        // Promote to ErrorException so the exception handler does the heavy
        // lifting (single code path = one place to evolve).
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    public static function handleException(\Throwable $e): void
    {
        $debug = (bool) App::config('app.debug');
        self::log($e);

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, '[' . get_class($e) . '] ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
            exit(1);
        }

        if (!headers_sent()) {
            http_response_code(500);
        }

        if ($debug) {
            // Let PHP render the trace as usual.
            echo '<pre style="background:#fff;color:#900;padding:16px;font:12px/1.5 SFMono-Regular,Menlo,monospace;">';
            echo htmlspecialchars(
                '[' . get_class($e) . '] ' . $e->getMessage() . "\n"
                . $e->getFile() . ':' . $e->getLine() . "\n\n"
                . $e->getTraceAsString(),
                ENT_QUOTES,
                'UTF-8'
            );
            echo '</pre>';
            return;
        }

        // Production: render the friendly 500 page. Keep it dependency-free so
        // we never crash inside the crash handler (e.g. DB also down).
        $viewFile = dirname(__DIR__) . '/Views/errors/500.php';
        if (is_readable($viewFile)) {
            $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
            $title = 'Something went wrong';
            $layout = 'auth';
            ob_start();
            include $viewFile;
            $content = ob_get_clean();
            $layoutFile = dirname(__DIR__) . '/Views/layouts/' . $layout . '.php';
            if (is_readable($layoutFile)) {
                $auth = null;
                include $layoutFile;
            } else {
                echo $content;
            }
            return;
        }
        echo 'Something went wrong. The administrator has been notified.';
    }

    public static function handleShutdown(): void
    {
        $err = error_get_last();
        if (!$err) return;
        $fatal = E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_PARSE;
        if (($err['type'] & $fatal) === 0) return;

        self::handleException(new \ErrorException(
            $err['message'], 0, $err['type'], $err['file'] ?? '', (int) ($err['line'] ?? 0)
        ));
    }

    /**
     * Write a single-line, parseable record to storage/logs/app.log. Falls
     * back to PHP's error_log if the file isn't writable so we never silently
     * lose information.
     */
    public static function log(\Throwable $e): void
    {
        $line = sprintf(
            "[%s] %s: %s in %s:%d | uri=%s ip=%s\n",
            date('Y-m-d H:i:s'),
            get_class($e),
            str_replace(["\r", "\n"], ' ', $e->getMessage()),
            $e->getFile(),
            $e->getLine(),
            $_SERVER['REQUEST_URI'] ?? '-',
            $_SERVER['REMOTE_ADDR'] ?? '-'
        );

        $logDir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $logFile = $logDir . '/app.log';
        if (@file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX) === false) {
            error_log(rtrim($line));
        }
    }
}
