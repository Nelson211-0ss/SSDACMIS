<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;

/**
 * Two parallel sign-in surfaces:
 *
 *   /login          - school portal for admins, regular staff and students.
 *                     HODs are not allowed in here; they're nudged toward
 *                     /hod/login.
 *   /hod/login      - dedicated HOD portal. Heads of Department (rows in
 *                     `department_heads`) and the shared `hod` user role
 *                     (one login for all HODs) may sign in here.
 *   /bursar/login   - dedicated Fees Management portal. Only users.role
 *                     'bursar' (created by admins) may sign in here.
 *
 * Logout sends each user back to the portal that fits them (HODs to
 * /hod/login, bursars to /bursar/login, everyone else to /login), so each
 * role only ever sees the branding for their portal.
 */
class AuthController extends Controller
{
    /**
     * Soft brute-force throttle. Keyed per browser session so a single
     * attacker can't hammer either portal indefinitely without rotating
     * cookies (and rotating cookies costs them their CSRF token too).
     */
    private const MAX_ATTEMPTS = 8;
    private const LOCK_SECONDS = 300; // 5 minutes

    private function throttleSlot(string $portal): string
    {
        return '_login_throttle_' . $portal;
    }

    private function checkThrottle(string $portal): ?string
    {
        $slot = $this->throttleSlot($portal);
        $t = $_SESSION[$slot] ?? null;
        if (!$t || empty($t['locked_until'])) return null;
        $left = (int) $t['locked_until'] - time();
        if ($left <= 0) {
            unset($_SESSION[$slot]);
            return null;
        }
        $mins = max(1, (int) ceil($left / 60));
        return 'Too many failed sign-in attempts. Try again in ' . $mins . ' minute' . ($mins === 1 ? '' : 's') . '.';
    }

    private function recordFailure(string $portal): void
    {
        $slot = $this->throttleSlot($portal);
        $t = $_SESSION[$slot] ?? ['attempts' => 0, 'locked_until' => 0];
        $t['attempts'] = (int) $t['attempts'] + 1;
        if ($t['attempts'] >= self::MAX_ATTEMPTS) {
            $t['locked_until'] = time() + self::LOCK_SECONDS;
            $t['attempts']     = 0;
        }
        $_SESSION[$slot] = $t;
    }

    private function clearFailures(string $portal): void
    {
        unset($_SESSION[$this->throttleSlot($portal)]);
    }

    /* -- main school portal (admin / staff / student) -------------------- */

    public function showLogin(): string
    {
        if (Auth::check()) {
            $this->redirect(Auth::isCurrentHod() ? '/hod' : '/dashboard');
        }
        return $this->view('auth/login');
    }

    /**
     * Resolves where a freshly-authenticated user belongs after sign-in:
     * HODs → /hod, bursars → /bursar, everyone else → /dashboard. Used by
     * each portal's login flow so users never land in the wrong place.
     */
    private function homeForRole(string $role): string
    {
        return match ($role) {
            'hod'    => '/hod',
            'bursar' => '/bursar',
            default  => '/dashboard',
        };
    }

    public function login(): string
    {
        $this->validateCsrf();
        $email    = trim((string) $this->input('email'));
        $password = (string) $this->input('password');

        if ($lock = $this->checkThrottle('main')) {
            return $this->view('auth/login', ['error' => $lock, 'old' => compact('email')]);
        }

        if ($email === '' || $password === '') {
            return $this->view('auth/login', ['error' => 'Email and password are required.', 'old' => compact('email')]);
        }

        if (!Auth::attempt($email, $password)) {
            $this->recordFailure('main');
            return $this->view('auth/login', ['error' => 'Invalid credentials.', 'old' => compact('email')]);
        }
        $this->clearFailures('main');

        // HODs have a dedicated portal — they shouldn't enter through the
        // main school login. Drop the auth record and steer them to
        // /hod/login. Use softLogout so the new CSRF token we mint for the
        // re-rendered form lives in a real session.
        if (Auth::isCurrentHod()) {
            Auth::softLogout();
            return $this->view('auth/login', [
                'error' => 'You are a Head of Department. Please use the HOD portal to sign in.',
                'old'   => compact('email'),
                'hodHint' => true,
            ]);
        }

        // Bursars have their own Fees Management portal — refuse them here.
        if (Auth::role() === 'bursar') {
            Auth::softLogout();
            return $this->view('auth/login', [
                'error' => 'This account belongs to the Bursar portal. Please use the Bursar sign-in.',
                'old'   => compact('email'),
                'bursarHint' => true,
            ]);
        }

        Flash::set('success', 'Welcome back!');
        $this->redirect('/dashboard');
        return '';
    }

    /* -- Bursar (Fees Management) portal -------------------------------- */

    public function showBursarLogin(): string
    {
        if (Auth::check()) {
            $this->redirect($this->homeForRole((string) Auth::role()));
        }
        return $this->view('auth/bursar_login');
    }

    public function bursarLogin(): string
    {
        $this->validateCsrf();
        $email    = trim((string) $this->input('email'));
        $password = (string) $this->input('password');

        if ($lock = $this->checkThrottle('bursar')) {
            return $this->view('auth/bursar_login', ['error' => $lock, 'old' => compact('email')]);
        }
        if ($email === '' || $password === '') {
            return $this->view('auth/bursar_login', ['error' => 'Email and password are required.', 'old' => compact('email')]);
        }

        if (!Auth::attempt($email, $password)) {
            $this->recordFailure('bursar');
            return $this->view('auth/bursar_login', ['error' => 'Invalid credentials.', 'old' => compact('email')]);
        }
        $this->clearFailures('bursar');

        // Only bursars may use this portal. Anyone else is dropped from the
        // session and shown a friendly hint pointing to the right portal.
        if (Auth::role() !== 'bursar') {
            $wrongRole = (string) Auth::role();
            Auth::softLogout();
            return $this->view('auth/bursar_login', [
                'error' => 'This portal is for Bursars only. Please use the correct sign-in for your account.',
                'old'   => compact('email'),
                'mainHint' => $wrongRole !== 'hod',
                'hodHint'  => $wrongRole === 'hod',
            ]);
        }

        Flash::set('success', 'Welcome to the Fees Management portal.');
        $this->redirect('/bursar');
        return '';
    }

    /* -- HOD portal ------------------------------------------------------- */

    public function showHodLogin(): string
    {
        if (Auth::check()) {
            $this->redirect(Auth::isCurrentHod() ? '/hod' : '/dashboard');
        }
        return $this->view('auth/hod_login');
    }

    public function hodLogin(): string
    {
        $this->validateCsrf();
        $email    = trim((string) $this->input('email'));
        $password = (string) $this->input('password');

        if ($lock = $this->checkThrottle('hod')) {
            return $this->view('auth/hod_login', ['error' => $lock, 'old' => compact('email')]);
        }

        if ($email === '' || $password === '') {
            return $this->view('auth/hod_login', ['error' => 'Email and password are required.', 'old' => compact('email')]);
        }

        if (!Auth::attempt($email, $password)) {
            $this->recordFailure('hod');
            return $this->view('auth/hod_login', ['error' => 'Invalid credentials.', 'old' => compact('email')]);
        }
        $this->clearFailures('hod');

        // Only HODs may use this portal. Anyone else is dropped from the
        // session and shown a friendly hint. Use softLogout so the new CSRF
        // token for the re-rendered form lives in a real session (otherwise
        // the user's next submit would fail with "Invalid CSRF token.").
        if (!Auth::isCurrentHod()) {
            Auth::softLogout();
            return $this->view('auth/hod_login', [
                'error' => 'This portal is for Heads of Department only. Use the main school sign-in instead.',
                'old'   => compact('email'),
                'mainHint' => true,
            ]);
        }

        Flash::set('success', 'Welcome to your department portal.');
        $this->redirect('/hod');
        return '';
    }

    /* -- shared logout: portal-aware redirect ---------------------------- */

    public function logout(): string
    {
        $portal = Auth::portal();
        $wasHod = Auth::isCurrentHod();
        Auth::logout();
        $target = match (true) {
            $portal === 'bursar' => '/bursar/login',
            $wasHod              => '/hod/login',
            default              => '/login',
        };
        $this->redirect($target);
        return '';
    }
}
