<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;
use App\Services\MailService;

/**
 * Super-admin creates and removes school_admin accounts for a specific school.
 *
 *   POST /schools/{id}/admins           -> create school_admin (auto-generates password, sends email)
 *   POST /school-admins/{id}/delete     -> remove school_admin
 */
class SchoolAdminController extends Controller
{
    public function store(string $schoolId): string
    {
        $this->validateCsrf();

        $school = Database::query("SELECT * FROM schools WHERE id = ? LIMIT 1", [(int) $schoolId])->fetch();
        if (!$school) { http_response_code(404); return $this->view('errors/404'); }

        $name  = trim((string) $this->input('name', ''));
        $email = trim((string) $this->input('email', ''));

        if ($name === '' || $email === '') {
            Flash::set('danger', 'Name and email are required.');
            $this->redirect('/schools/' . (int) $schoolId);
            return '';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Flash::set('danger', 'That email address looks invalid.');
            $this->redirect('/schools/' . (int) $schoolId);
            return '';
        }

        $exists = Database::query("SELECT 1 FROM users WHERE email = ? LIMIT 1", [$email])->fetch();
        if ($exists) {
            Flash::set('danger', 'That email is already in use by another account.');
            $this->redirect('/schools/' . (int) $schoolId);
            return '';
        }

        $plain = bin2hex(random_bytes(8)); // 16-character hex password

        Database::query(
            "INSERT INTO users (school_id, name, email, password, role, status)
             VALUES (?, ?, ?, ?, 'school_admin', 'active')",
            [(int) $schoolId, $name, $email, password_hash($plain, PASSWORD_DEFAULT)]
        );

        $appUrl  = rtrim($_ENV['APP_URL'] ?? '', '/');
        $appName = $_ENV['APP_NAME'] ?? 'SSD-ACMIS';
        $html    = self::welcomeEmail($name, $school['name'], $email, $plain, $appUrl, $appName);

        $sent = MailService::send($email, $name, "Your School Admin Account — {$appName}", $html);

        $msg = "School admin account created for {$name}.";
        if (!$sent) {
            $msg .= " (Email delivery failed — share credentials manually: password is {$plain})";
        }
        Flash::set($sent ? 'success' : 'warning', $msg);
        $this->redirect('/schools/' . (int) $schoolId);
        return '';
    }

    public function resend(string $id): string
    {
        $this->validateCsrf();

        $user = Database::query(
            "SELECT u.id, u.name, u.email, u.school_id, s.name AS school_name
             FROM users u
             JOIN schools s ON s.id = u.school_id
             WHERE u.id = ? AND u.role = 'school_admin' LIMIT 1",
            [(int) $id]
        )->fetch();

        if (!$user) { http_response_code(404); return $this->view('errors/404'); }

        $plain = bin2hex(random_bytes(8));
        Database::query(
            "UPDATE users SET password = ? WHERE id = ?",
            [password_hash($plain, PASSWORD_DEFAULT), (int) $id]
        );

        $appUrl  = rtrim($_ENV['APP_URL'] ?? '', '/');
        $appName = $_ENV['APP_NAME'] ?? 'SSD-ACMIS';
        $html    = self::welcomeEmail($user['name'], $user['school_name'], $user['email'], $plain, $appUrl, $appName);

        $sent = MailService::send($user['email'], $user['name'], "Your School Admin Account — {$appName}", $html);

        $msg = "Credentials resent to {$user['name']}.";
        if (!$sent) {
            $msg = "Email delivery failed — new password for {$user['name']}: {$plain} (share this manually)";
        }
        Flash::set($sent ? 'success' : 'warning', $msg);
        $this->redirect('/schools/' . (int) $user['school_id']);
        return '';
    }

    public function destroy(string $id): string
    {
        $this->validateCsrf();
        $user = Database::query(
            "SELECT id, school_id FROM users WHERE id = ? AND role = 'school_admin' LIMIT 1",
            [(int) $id]
        )->fetch();
        if (!$user) { http_response_code(404); return $this->view('errors/404'); }

        $schoolId = $user['school_id'];
        Database::query("DELETE FROM users WHERE id = ?", [(int) $id]);
        Flash::set('success', 'School admin account removed.');
        $this->redirect('/schools/' . (int) $schoolId);
        return '';
    }

    private static function welcomeEmail(
        string $name,
        string $schoolName,
        string $email,
        string $password,
        string $appUrl,
        string $appName
    ): string {
        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:Inter,Arial,sans-serif;color:#212529;max-width:600px;margin:auto;padding:32px 24px;">
  <h2 style="margin-bottom:4px;">{$appName}</h2>
  <p style="color:#6c757d;margin-top:0;">School Management System</p>
  <hr style="border:none;border-top:1px solid #dee2e6;margin:20px 0;">

  <p>Hello <strong>{$name}</strong>,</p>
  <p>An administrator has created a <strong>School Admin</strong> account for you
     at <strong>{$schoolName}</strong>.</p>

  <table style="background:#f8f9fa;border-radius:8px;padding:20px 24px;width:100%;margin:20px 0;border-collapse:collapse;">
    <tr>
      <td style="padding:6px 0;color:#6c757d;font-size:14px;">Login URL</td>
      <td style="padding:6px 0;font-weight:600;">
        <a href="{$appUrl}/login" style="color:#0d6efd;">{$appUrl}/login</a>
      </td>
    </tr>
    <tr>
      <td style="padding:6px 0;color:#6c757d;font-size:14px;">Email</td>
      <td style="padding:6px 0;font-family:monospace;">{$email}</td>
    </tr>
    <tr>
      <td style="padding:6px 0;color:#6c757d;font-size:14px;">Temporary Password</td>
      <td style="padding:6px 0;font-family:monospace;font-size:18px;letter-spacing:2px;color:#dc3545;">
        <strong>{$password}</strong>
      </td>
    </tr>
  </table>

  <p style="color:#dc3545;font-size:14px;">
    ⚠ Please change your password after your first login using <em>Account → Change Password</em>.
  </p>

  <hr style="border:none;border-top:1px solid #dee2e6;margin:20px 0;">
  <p style="font-size:12px;color:#adb5bd;">
    This email was sent by {$appName}. If you were not expecting this, please contact your system administrator.
  </p>
</body>
</html>
HTML;
    }
}
