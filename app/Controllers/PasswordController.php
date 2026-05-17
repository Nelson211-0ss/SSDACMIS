<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;
use App\Services\MailService;

/**
 * Handles all password-related flows:
 *
 *   GET  /forgot-password   -> show email input form (public)
 *   POST /forgot-password   -> generate + email reset link
 *   GET  /reset-password    -> show new-password form (token in query string)
 *   POST /reset-password    -> validate token, update password
 *   GET  /account/password  -> change-password form (authenticated)
 *   POST /account/password  -> verify current, update to new
 */
class PasswordController extends Controller
{
    /* ------------------------------------------------------------------ */
    /* Forgot password                                                     */
    /* ------------------------------------------------------------------ */

    public function forgotForm(): string
    {
        if (Auth::check()) {
            $this->redirect('/dashboard');
            return '';
        }
        return $this->view('auth/forgot-password');
    }

    public function forgotSubmit(): string
    {
        $this->validateCsrf();
        $email = trim((string) $this->input('email', ''));

        // Always show the same message to prevent user enumeration.
        $genericMsg = 'If that email exists in our system, a reset link has been sent.';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Flash::set('info', $genericMsg);
            $this->redirect('/forgot-password');
            return '';
        }

        $user = Database::query(
            "SELECT id, name, email FROM users WHERE email = ? AND status = 'active' LIMIT 1",
            [$email]
        )->fetch();

        if ($user) {
            // Delete any previous tokens for this email.
            Database::query("DELETE FROM password_resets WHERE email = ?", [$email]);

            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            Database::query(
                "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)",
                [$email, $token, $expires]
            );

            $appUrl  = rtrim($_ENV['APP_URL'] ?? '', '/');
            $appName = $_ENV['APP_NAME'] ?? 'SSD-ACMIS';
            $link    = $appUrl . '/reset-password?token=' . urlencode($token);
            $html    = self::resetEmail($user['name'], $appName, $link);
            MailService::send($email, $user['name'], "Reset your {$appName} password", $html);
        }

        Flash::set('info', $genericMsg);
        $this->redirect('/forgot-password');
        return '';
    }

    /* ------------------------------------------------------------------ */
    /* Reset password (via emailed token)                                  */
    /* ------------------------------------------------------------------ */

    public function resetForm(): string
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        if ($token === '' || !$this->tokenValid($token)) {
            Flash::set('danger', 'This password reset link is invalid or has expired. Please request a new one.');
            $this->redirect('/forgot-password');
            return '';
        }
        return $this->view('auth/reset-password', compact('token'));
    }

    public function resetSubmit(): string
    {
        $this->validateCsrf();
        $token    = trim((string) $this->input('token', ''));
        $password = (string) $this->input('password', '');
        $confirm  = (string) $this->input('password_confirmation', '');

        $row = $this->tokenValid($token);
        if (!$row) {
            Flash::set('danger', 'This reset link is invalid or has expired.');
            $this->redirect('/forgot-password');
            return '';
        }
        if (strlen($password) < 8) {
            Flash::set('danger', 'Password must be at least 8 characters.');
            $this->redirect('/reset-password?token=' . urlencode($token));
            return '';
        }
        if ($password !== $confirm) {
            Flash::set('danger', 'Passwords do not match.');
            $this->redirect('/reset-password?token=' . urlencode($token));
            return '';
        }

        Database::query(
            "UPDATE users SET password = ? WHERE email = ?",
            [password_hash($password, PASSWORD_DEFAULT), $row['email']]
        );
        Database::query("DELETE FROM password_resets WHERE token = ?", [$token]);

        Flash::set('success', 'Password updated. You can now sign in with your new password.');
        $this->redirect('/login');
        return '';
    }

    /* ------------------------------------------------------------------ */
    /* Change password (authenticated user)                                */
    /* ------------------------------------------------------------------ */

    public function changeForm(): string
    {
        return $this->view('account/change-password');
    }

    public function changeSubmit(): string
    {
        $this->validateCsrf();
        $current = (string) $this->input('current_password', '');
        $new     = (string) $this->input('password', '');
        $confirm = (string) $this->input('password_confirmation', '');

        $user = Database::query(
            "SELECT id, password FROM users WHERE id = ? LIMIT 1",
            [(int) Auth::user()['id']]
        )->fetch();

        if (!$user || !password_verify($current, $user['password'])) {
            Flash::set('danger', 'Current password is incorrect.');
            $this->redirect('/account/password');
            return '';
        }
        if (strlen($new) < 8) {
            Flash::set('danger', 'New password must be at least 8 characters.');
            $this->redirect('/account/password');
            return '';
        }
        if ($new !== $confirm) {
            Flash::set('danger', 'New passwords do not match.');
            $this->redirect('/account/password');
            return '';
        }

        Database::query(
            "UPDATE users SET password = ? WHERE id = ?",
            [password_hash($new, PASSWORD_DEFAULT), (int) $user['id']]
        );

        Flash::set('success', 'Password changed successfully.');
        $this->redirect('/account/password');
        return '';
    }

    /* ------------------------------------------------------------------ */

    /** Returns the reset row if the token exists and is not expired, else false. */
    private function tokenValid(string $token): array|false
    {
        if ($token === '') return false;
        return Database::query(
            "SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1",
            [$token]
        )->fetch() ?: false;
    }

    private static function resetEmail(string $name, string $appName, string $link): string
    {
        return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="font-family:Inter,Arial,sans-serif;color:#212529;max-width:600px;margin:auto;padding:32px 24px;">
  <h2 style="margin-bottom:4px;">{$appName}</h2>
  <p style="color:#6c757d;margin-top:0;">Password Reset Request</p>
  <hr style="border:none;border-top:1px solid #dee2e6;margin:20px 0;">
  <p>Hello <strong>{$name}</strong>,</p>
  <p>We received a request to reset your password. Click the button below to set a new password.
     This link expires in <strong>1 hour</strong>.</p>
  <p style="text-align:center;margin:32px 0;">
    <a href="{$link}" style="background:#0d6efd;color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-weight:600;font-size:15px;">
      Reset My Password
    </a>
  </p>
  <p style="font-size:13px;color:#6c757d;">If the button doesn't work, copy and paste this link:<br>
    <a href="{$link}" style="word-break:break-all;">{$link}</a>
  </p>
  <p style="font-size:13px;color:#6c757d;">If you didn't request a password reset, you can ignore this email.</p>
  <hr style="border:none;border-top:1px solid #dee2e6;margin:20px 0;">
  <p style="font-size:12px;color:#adb5bd;">Sent by {$appName}.</p>
</body></html>
HTML;
    }
}
