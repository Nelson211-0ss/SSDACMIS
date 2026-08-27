<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;

/**
 * Single sign-in at /login for admins, staff, students, Heads of Department
 * and bursars. After authentication, users are routed to the dashboard that
 * matches their role (/dashboard, /hod, or /bursar).
 *
 * Legacy URLs /hod/login and /bursar/login still resolve (GET redirects to
 * /login; POST is processed the same as /login) so old bookmarks keep working.
 *
 * Parents are a separate flow entirely: /parent/login is its own page where
 * a parent signs in with a student's admission number as both "username"
 * and "password" (see Auth::attemptParent()) rather than an email/password
 * pair, so it can't share processLoginForm()/auth/login.php with the rest.
 */
class AuthController extends Controller
{
    /**
     * Soft brute-force throttle. Keyed per browser session so a single
     * attacker can't hammer sign-in indefinitely without rotating
     * cookies (and rotating cookies costs them their CSRF token too).
     */
    private const MAX_ATTEMPTS = 8;
    private const LOCK_SECONDS = 300; // 5 minutes

    private const THROTTLE_PORTAL = 'unified';

    private function throttleSlot(): string
    {
        return '_login_throttle_' . self::THROTTLE_PORTAL;
    }

    private function checkThrottle(): ?string
    {
        $slot = $this->throttleSlot();
        $t    = $_SESSION[$slot] ?? null;
        if (!$t || empty($t['locked_until'])) {
            return null;
        }
        $left = (int) $t['locked_until'] - time();
        if ($left <= 0) {
            unset($_SESSION[$slot]);

            return null;
        }
        $mins = max(1, (int) ceil($left / 60));

        return 'Too many failed sign-in attempts. Try again in ' . $mins . ' minute' . ($mins === 1 ? '' : 's') . '.';
    }

    private function recordFailure(): void
    {
        $slot = $this->throttleSlot();
        $t    = $_SESSION[$slot] ?? ['attempts' => 0, 'locked_until' => 0];
        $t['attempts'] = (int) $t['attempts'] + 1;
        if ($t['attempts'] >= self::MAX_ATTEMPTS) {
            $t['locked_until'] = time() + self::LOCK_SECONDS;
            $t['attempts']     = 0;
        }
        $_SESSION[$slot] = $t;
    }

    private function clearFailures(): void
    {
        unset($_SESSION[$this->throttleSlot()]);
    }

    /**
     * Landing URL right after a successful sign-in (current portal only).
     */
    private function homeAfterLogin(): string
    {
        if (Auth::isCurrentHod()) {
            return '/hod';
        }

        return match ((string) Auth::role()) {
            'bursar' => '/bursar',
            default  => '/dashboard',
        };
    }

    private function homeAfterUnifiedSlot(string $slot): string
    {
        return match ($slot) {
            'hod'    => '/hod',
            'bursar' => '/bursar',
            default  => '/dashboard',
        };
    }

    private function flashForDestination(string $dest): void
    {
        $msg = match ($dest) {
            '/hod'    => 'Welcome to your department portal.',
            '/bursar' => 'Welcome to the Fees Management portal.',
            default   => 'Welcome back!',
        };
        Flash::set('success', $msg);
    }

    public function showLogin(): string
    {
        if (Auth::check()) {
            $this->redirect($this->homeAfterLogin());
        }

        return $this->view('auth/login');
    }

    public function showBursarLogin(): void
    {
        $this->redirect('/login');
    }

    public function showHodLogin(): void
    {
        $this->redirect('/login');
    }

    public function showParentLogin(): string
    {
        if (Auth::check()) {
            $this->redirect('/parent');
        }

        return $this->view('auth/parent_login');
    }

    public function login(): string
    {
        return $this->processLoginForm();
    }

    public function bursarLogin(): string
    {
        return $this->processLoginForm();
    }

    public function hodLogin(): string
    {
        return $this->processLoginForm();
    }

    public function parentLogin(): string
    {
        return $this->processParentLoginForm();
    }

    private function processLoginForm(): string
    {
        $this->validateCsrf();
        $email    = trim((string) $this->input('email'));
        // Trim stray whitespace/newlines that mail clients or copy-paste can
        // append to a password (e.g. selecting across a table cell in the
        // welcome email). Generated temp passwords are pure hex, so this is
        // always safe and never strips an intentional character.
        $password = trim((string) $this->input('password'));

        if ($lock = $this->checkThrottle()) {
            return $this->view('auth/login', ['error' => $lock, 'old' => compact('email')]);
        }

        if ($email === '' || $password === '') {
            return $this->view('auth/login', ['error' => 'Email and password are required.', 'old' => compact('email')]);
        }

        $slot = Auth::attemptUnified($email, $password);
        if ($slot === null) {
            $this->recordFailure();

            return $this->view('auth/login', ['error' => 'Invalid credentials.', 'old' => compact('email')]);
        }
        $this->clearFailures();

        $dest = $this->homeAfterUnifiedSlot($slot);
        $this->flashForDestination($dest);
        $this->redirect($dest);

        return '';
    }

    /**
     * Parent sign-in: admission number as both fields (see Auth::attemptParent()
     * and the note on parent_students.is_primary). Shares the same soft
     * throttle slot as every other login form — one shared counter across
     * all portals, not a separate budget per form.
     */
    private function processParentLoginForm(): string
    {
        $this->validateCsrf();
        $admissionNo = trim((string) $this->input('admission_no'));
        $password    = trim((string) $this->input('password'));

        if ($lock = $this->checkThrottle()) {
            return $this->view('auth/parent_login', ['error' => $lock, 'old' => compact('admissionNo')]);
        }

        if ($admissionNo === '' || $password === '') {
            return $this->view('auth/parent_login', ['error' => 'Enter the admission number in both fields.', 'old' => compact('admissionNo')]);
        }
        if ($admissionNo !== $password) {
            return $this->view('auth/parent_login', ['error' => 'Admission number and password must match.', 'old' => compact('admissionNo')]);
        }

        if (!Auth::attemptParent($admissionNo)) {
            $this->recordFailure();

            return $this->view('auth/parent_login', ['error' => 'Invalid admission number.', 'old' => compact('admissionNo')]);
        }
        $this->clearFailures();

        Flash::set('success', 'Welcome to the Parent portal.');
        $this->redirect('/parent');

        return '';
    }

    public function logout(): string
    {
        Auth::logout();
        $this->redirect('/login');

        return '';
    }
}
