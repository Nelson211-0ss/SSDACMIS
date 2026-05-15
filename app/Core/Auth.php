<?php
namespace App\Core;

/**
 * Portal-aware authentication.
 *
 * Everyone signs in at /login. The app still tracks three portal slots in the
 * same browser session so roles can use separate areas without leaking state:
 *
 *   - "main"   portal: /login, /dashboard, /students, /staff, ... (admins,
 *              regular staff and students).
 *   - "hod"    portal: /hod/* (Heads of Department — same sign-in page).
 *   - "bursar" portal: /bursar/* (Fees Management — same sign-in page).
 *
 * All three portals share a single PHP session cookie, but their user records
 * live in DIFFERENT slots inside that session ($_SESSION['users']['main'],
 * $_SESSION['users']['hod'], $_SESSION['users']['bursar']). The "active" slot
 * for a given request is derived from the URL prefix, so an admin can stay
 * logged in in tab A while a bursar logs in in tab B — clicking around in
 * either tab no longer leaks state across portals.
 */
class Auth
{
    /* ---------------------------------------------------------------- */
    /* Portal selection (which session slot is active for this request) */
    /* ---------------------------------------------------------------- */

    /**
     * Returns:
     *   'hod'    for any request under /hod/*    (HOD portal)
     *   'bursar' for any request under /bursar/* (Fees Management portal)
     *   'main'   for everything else
     */
    public static function portal(): string
    {
        static $cache = null;
        if ($cache !== null) return $cache;

        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
        $uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $rel  = ($base !== '' && str_starts_with($uri, $base))
            ? substr($uri, strlen($base))
            : $uri;
        $rel = '/' . ltrim($rel, '/');

        if ($rel === '/hod'    || str_starts_with($rel, '/hod/'))    return $cache = 'hod';
        if ($rel === '/bursar' || str_starts_with($rel, '/bursar/')) return $cache = 'bursar';
        return $cache = 'main';
    }

    /**
     * URL prefix to use when redirecting/linking inside the *current*
     * portal — '/hod' / '/bursar' for those portals, '' for the main portal.
     */
    public static function portalPrefix(): string
    {
        return match (self::portal()) {
            'hod'    => '/hod',
            'bursar' => '/bursar',
            default  => '',
        };
    }

    /* ---------------------------------------------------------------- */
    /* Login / logout                                                   */
    /* ---------------------------------------------------------------- */

    public static function attempt(string $email, string $password): bool
    {
        $stmt = Database::query(
            'SELECT id, name, email, password, role, status FROM users WHERE email = ? LIMIT 1',
            [$email]
        );
        $user = $stmt->fetch();

        if (!$user || $user['status'] !== 'active') return false;
        if (!password_verify($password, $user['password'])) return false;

        unset($user['password']);

        // Regenerate the session id only once per request to defeat fixation.
        // Note: we DO NOT wipe other portal slots — the other tab keeps its
        // signed-in user.
        if (empty($_SESSION['_regenerated_for'])
            || $_SESSION['_regenerated_for'] !== self::portal()) {
            session_regenerate_id(true);
            $_SESSION['_regenerated_for'] = self::portal();
            // Rotate the CSRF secret too: any form a guest had open before
            // signing in can no longer be replayed against the new identity.
            unset($_SESSION['_csrf']);
        }

        $_SESSION['users'][self::portal()] = $user;
        return true;
    }

    /**
     * Sign-in for the shared /login page: credentials are checked, then the
     * session user is stored in the portal slot that matches the account —
     * main, hod, or bursar — so /hod/* and /bursar/* routes see a logged-in
     * user even though the form was posted from /login.
     *
     * @return string|null Portal slot ('main'|'hod'|'bursar') on success, null on failure
     */
    public static function attemptUnified(string $email, string $password): ?string
    {
        $stmt = Database::query(
            'SELECT id, name, email, password, role, status FROM users WHERE email = ? LIMIT 1',
            [$email]
        );
        $user = $stmt->fetch();

        if (!$user || $user['status'] !== 'active') {
            return null;
        }
        if (!password_verify($password, $user['password'])) {
            return null;
        }

        unset($user['password']);

        $slot = self::portalSlotForUserRow($user);

        if (empty($_SESSION['_regenerated_for'])
            || $_SESSION['_regenerated_for'] !== $slot) {
            session_regenerate_id(true);
            $_SESSION['_regenerated_for'] = $slot;
            unset($_SESSION['_csrf']);
        }

        $_SESSION['users'][$slot] = $user;

        return $slot;
    }

    /** @param array{id:int,role:string} $user */
    private static function portalSlotForUserRow(array $user): string
    {
        $role = (string) ($user['role'] ?? '');
        if ($role === 'hod') {
            return 'hod';
        }
        if ($role === 'bursar') {
            return 'bursar';
        }
        if ($role === 'staff') {
            $row = Database::query(
                'SELECT 1 FROM department_heads dh
                 JOIN staff s ON s.id = dh.staff_id
                 WHERE s.user_id = ? LIMIT 1',
                [(int) $user['id']]
            )->fetch();
            if ($row) {
                return 'hod';
            }
        }

        return 'main';
    }

    public static function user(): ?array
    {
        // Migrate legacy single-slot sessions ($_SESSION['user']) so users
        // who were already signed in before the upgrade don't get bounced
        // back to /login on the next click.
        if (isset($_SESSION['user']) && empty($_SESSION['users'])) {
            $u = $_SESSION['user'];
            $role = $u['role'] ?? '';
            $slot = match ($role) {
                'hod'    => 'hod',
                'bursar' => 'bursar',
                default  => 'main',
            };
            $_SESSION['users'][$slot] = $u;
            unset($_SESSION['user']);
        }
        return $_SESSION['users'][self::portal()] ?? null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function role(): ?string
    {
        $u = self::user();
        return $u['role'] ?? null;
    }

    public static function logout(): void
    {
        unset($_SESSION['users'][self::portal()]);
        // Drop the legacy slot too if it's still around from an old session.
        unset($_SESSION['user']);

        // If no portal still has a signed-in user, fully tear the session
        // down so we don't leak a stale CSRF token. Otherwise keep the
        // session alive for the *other* portal.
        if (empty($_SESSION['users'])) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
            }
            session_destroy();
        } else {
            // Refresh the CSRF token so the just-logged-out portal can't
            // submit forms with the previous session's token.
            unset($_SESSION['_csrf']);
            session_regenerate_id(true);
        }
    }

    /**
     * "Soft" sign-out used when we want to drop the user record but keep
     * rendering a form on the same response. A full logout destroys the
     * session and clears the cookie, which would orphan any new CSRF token
     * we mint for the form (causing "Invalid CSRF token." on the next
     * submit). Soft sign-out keeps the session alive, just without the
     * user in the *current* portal slot.
     */
    public static function softLogout(): void
    {
        unset($_SESSION['users'][self::portal()]);
        unset($_SESSION['user']); // legacy slot
        session_regenerate_id(true);
    }

    /* ---------------------------------------------------------------- */
    /* Authorization                                                    */
    /* ---------------------------------------------------------------- */

    public static function require(?array $roles = null): void
    {
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
        if (!self::check()) {
            $hint = self::loginHintFromRequest();
            header('Location: ' . $base . $hint);
            exit;
        }
        // HODs are confined to the HOD portal — admin/staff areas are off-limits.
        // Run the scope guards BEFORE the role check so users get a friendly
        // redirect to their home portal rather than a raw 403 when they hit
        // out-of-scope URLs.
        self::enforceHodScope();
        self::enforceBursarScope();
        if ($roles !== null && !in_array(self::role(), $roles, true)) {
            http_response_code(403);
            echo View::render('errors/403');
            exit;
        }
    }

    /**
     * True when the signed-in user is a Head of Department (i.e. has at least
     * one row in `department_heads`). Cached per request *and* per portal.
     */
    public static function isCurrentHod(): bool
    {
        static $cache = [];
        $portal = self::portal();
        if (array_key_exists($portal, $cache)) return $cache[$portal];

        if (self::role() === 'hod') return $cache[$portal] = true;
        if (self::role() !== 'staff') return $cache[$portal] = false;
        $u = self::user();
        if (!$u) return $cache[$portal] = false;
        $row = Database::query(
            "SELECT 1 FROM department_heads dh
             JOIN staff s ON s.id = dh.staff_id
             WHERE s.user_id = ? LIMIT 1",
            [(int) $u['id']]
        )->fetch();
        return $cache[$portal] = (bool) $row;
    }

    /**
     * Whether the app shell should use the HOD lockdown (minimal) sidebar.
     * System admins always keep the full admin menu — even when previewing
     * /hod or /marks. Only `staff` and `hod` logins that pass isCurrentHod()
     * get the HOD nav (never the admin role, even if the DB is inconsistent).
     */
    public static function usesHodPortalNav(): bool
    {
        $r = self::role();
        if (!in_array($r, ['staff', 'hod'], true)) {
            return false;
        }
        return self::isCurrentHod();
    }

    /**
     * If the current user is an HOD and the requested URL is outside the HOD
     * portal allow-list, redirect them to /hod. With portal-scoped sessions
     * this is a belt-and-braces guard: HODs only have a session inside the
     * /hod portal, so they can't actually arrive here unless they manually
     * type a non-/hod URL after signing in.
     */
    public static function enforceHodScope(): void
    {
        if (!self::isCurrentHod()) return;

        // A true HOD's session is always in the 'hod' portal slot, so the
        // current request URI MUST be under /hod/* for them to be
        // authenticated at all. Bail out early — no extra work needed.
        if (self::portal() === 'hod') return;

        // Defensive fallback (should never trigger because /hod/* is the
        // only place a HOD can be signed in): nudge any odd request to /hod.
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
        Flash::set('warning', 'That section is restricted to administrators. Use the HOD portal instead.');
        header('Location: ' . $base . '/hod');
        exit;
    }

    /**
     * Bursars live exclusively inside the /bursar portal. If somebody with a
     * bursar session in another slot lands on /bursar (shouldn't happen with
     * portal-scoped sessions), nudge them to the bursar dashboard. If a
     * bursar's session is genuine but they typed a non-/bursar URL, they
     * appear unauthenticated and get sent to /login by the portal hint.
     */
    public static function enforceBursarScope(): void
    {
        if (self::role() !== 'bursar') return;

        // A bursar's session is always in the 'bursar' slot, so being
        // authenticated outside /bursar/* should never happen — but if it
        // does, redirect them to /bursar.
        if (self::portal() === 'bursar') return;

        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
        Flash::set('warning', 'That section is restricted. Use the Bursar portal instead.');
        header('Location: ' . $base . '/bursar');
        exit;
    }

    /**
     * Login URL for unauthenticated guests (single page for every role).
     */
    private static function loginHintFromRequest(): string
    {
        return '/login';
    }
}
