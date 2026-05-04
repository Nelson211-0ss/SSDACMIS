<?php
namespace App\Core;

abstract class Controller
{
    protected function view(string $template, array $data = []): string
    {
        return View::render($template, $data);
    }

    protected function redirect(string $path): void
    {
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
        header('Location: ' . $base . $this->portalize($path));
        exit;
    }

    /**
     * If the current request is inside a portal (URL prefix /hod/* or
     * /bursar/*), keep redirects inside that portal too. We only rewrite a
     * small allow-list of shared paths because those are the ones the HOD
     * portal exposes under /hod/* aliases. Other paths (/dashboard, /login,
     * /hod/students, /bursar/dashboard, ...) are left untouched.
     */
    protected function portalize(string $path): string
    {
        $clean = '/' . ltrim($path, '/');
        $portal = Auth::portal();

        if ($portal === 'hod') {
            if ($clean === '/hod' || str_starts_with($clean, '/hod/')) return $clean;
            $aliases = ['/marks', '/reports', '/announcements'];
            foreach ($aliases as $a) {
                if ($clean === $a || str_starts_with($clean, $a . '/') || str_starts_with($clean, $a . '?')) {
                    return '/hod' . $clean;
                }
            }
        }

        // Bursar portal is fully self-contained under /bursar/* — controllers
        // already use /bursar/... paths directly, so no rewrites needed.

        return $clean;
    }

    protected function input(string $key, $default = null)
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    protected function validateCsrf(): void
    {
        $token = $_POST['_csrf'] ?? '';
        if (!hash_equals($_SESSION['_csrf'] ?? '', (string) $token)) {
            // Most often the user's session was reset (e.g. they were logged
            // out, the cookie expired, or they kept a tab open across a
            // server restart). Bounce them back to where they came from with
            // a friendly flash instead of crashing on a white page.
            Flash::set('warning', 'Your session expired. Please try again.');

            $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
            $back = $_SERVER['HTTP_REFERER'] ?? null;
            if (!$back) {
                $back = $base . (Auth::isCurrentHod() ? '/hod' : (Auth::check() ? '/dashboard' : '/login'));
            }
            header('Location: ' . $back, true, 303);
            exit;
        }
    }
}
