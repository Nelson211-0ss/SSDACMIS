<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;

/**
 * Single sign-in at /login for admins, staff, students, Heads of Department,
 * and bursars. After authentication, users are routed to the dashboard that
 * matches their role (/dashboard, /hod, or /bursar).
 *
 * Legacy URLs /hod/login and /bursar/login still resolve (GET redirects to
 * /login; POST is processed the same as /login) so old bookmarks keep working.
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

    private function processLoginForm(): string
    {
        $this->validateCsrf();
        $email    = trim((string) $this->input('email'));
        $password = (string) $this->input('password');

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

    public function logout(): string
    {
        Auth::logout();
        $this->redirect('/login');

        return '';
    }
}
