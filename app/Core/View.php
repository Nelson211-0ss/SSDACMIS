<?php
namespace App\Core;

class View
{
    public static function render(string $template, array $data = []): string
    {
        $file = dirname(__DIR__) . '/Views/' . str_replace('.', '/', $template) . '.php';
        if (!is_readable($file)) {
            http_response_code(500);
            return "View not found: $template";
        }

        extract($data, EXTR_SKIP);
        $auth         = Auth::user();
        $base         = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
        $portal       = Auth::portal();        // 'main' or 'hod'
        $portalPrefix = Auth::portalPrefix();  // '' or '/hod' — prepend to in-portal URLs
        $csrf   = self::csrfToken();
        $layout = null;     // child view may set this
        $title  = $data['title'] ?? null;

        ob_start();
        include $file;
        $content = ob_get_clean();

        if ($layout) {
            // Re-read the signed-in user so a child view cannot override $auth
            // before the layout (session is the only source of truth for role).
            $auth         = Auth::user();
            $portal       = Auth::portal();
            $portalPrefix = Auth::portalPrefix();
            $layoutFile = dirname(__DIR__) . '/Views/layouts/' . $layout . '.php';
            if (is_readable($layoutFile)) {
                ob_start();
                include $layoutFile;
                return ob_get_clean();
            }
        }
        return $content;
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Display student ENUM columns in uppercase (MySQL stores lowercase ENUM values).
     * Stream "none" or empty is shown as N/A.
     */
    public static function studentEnumUpper(string $field, ?string $value): string
    {
        $v = strtolower(trim((string) $value));
        if ($field === 'stream' && ($v === '' || $v === 'none')) {
            return self::e('N/A');
        }
        if ($v === '') {
            return '';
        }

        return self::e(mb_strtoupper($v, 'UTF-8'));
    }

    /** Uppercase plain student text already stored mixed — optional display normalization. */
    public static function upper(?string $value): string
    {
        return self::e(mb_strtoupper(trim((string) $value), 'UTF-8'));
    }

    /**
     * Deterministic WhatsApp-style avatar color index (0–11) from a name.
     */
    public static function studentAvatarColorIndex(string $first, string $last): int
    {
        $name = trim($first . ' ' . $last);
        if ($name === '') {
            return 0;
        }
        $sum = 0;
        $len = mb_strlen($name, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $sum += mb_ord(mb_substr($name, $i, 1, 'UTF-8'), 'UTF-8');
        }

        return $sum % 12;
    }

    /** CSS class suffix for a student's avatar background color. */
    public static function studentAvatarColorClass(string $first, string $last): string
    {
        return 'stu-avatar--c' . self::studentAvatarColorIndex($first, $last);
    }

    /**
     * Append a ?v=<filemtime> cache-buster to a /public-relative asset path.
     * Used in layouts so browsers fetch a fresh copy whenever the file changes,
     * even though .htaccess sets long Expires headers.
     */
    public static function asset(string $base, string $relPath): string
    {
        $absolute = dirname(__DIR__, 2) . '/public/' . ltrim($relPath, '/');
        $version  = is_readable($absolute) ? filemtime($absolute) : time();
        return $base . '/' . ltrim($relPath, '/') . '?v=' . $version;
    }
}
