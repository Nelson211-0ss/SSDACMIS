<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;
use App\Services\MailService;

/**
 * Admin-only management of Head-of-Department login accounts.
 *
 *   GET  /hods               -> list every users.role='hod' account
 *   GET  /hods/create        -> "new HOD" form
 *   POST /hods               -> create the HOD (name, dept, email, password)
 *   GET  /hods/{id}/edit     -> edit form
 *   POST /hods/{id}          -> update name/dept/email/status (+ optional password)
 *   POST /hods/{id}/delete   -> delete the user row
 *
 * HODs created here sign in at /login and land on /hod (HOD portal).
 * They have full Form 1–4 mark-entry access for every subject because
 * users.role='hod' already grants the same shared-HOD privileges that
 * MarksController and ReportController honour.
 *
 * The `department` column is **informational only** — it does NOT scope what
 * the HOD can grade. It just labels which department they head.
 */
class HodAccountController extends Controller
{
    public function index(): string
    {
        $schoolId = Auth::schoolId();
        $sf = $schoolId !== null ? ' AND school_id = ?' : '';
        $sp = $schoolId !== null ? [$schoolId] : [];

        $hods = Database::query(
            "SELECT id, name, email, department, status, created_at
             FROM users WHERE role = 'hod'{$sf}
             ORDER BY status DESC, name",
            $sp
        )->fetchAll();

        return $this->view('hods/index', ['hods' => $hods]);
    }

    public function create(): string
    {
        return $this->view('hods/form', [
            'hod' => null,
        ]);
    }

    public function store(): string
    {
        $this->validateCsrf();
        $d = $this->payload();

        if ($d['name'] === '' || $d['email'] === '') {
            Flash::set('danger', 'Name and email are required.');
            $this->redirect('/hods/create');
            return '';
        }
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            Flash::set('danger', 'That email address looks invalid.');
            $this->redirect('/hods/create');
            return '';
        }

        $exists = Database::query("SELECT 1 FROM users WHERE email = ? LIMIT 1", [$d['email']])->fetch();
        if ($exists) {
            Flash::set('danger', 'That email is already in use.');
            $this->redirect('/hods/create');
            return '';
        }

        $plain    = bin2hex(random_bytes(8));
        $schoolId = Auth::schoolId() ?? 1;

        Database::query(
            "INSERT INTO users (school_id, name, email, password, role, department, status)
             VALUES (?, ?, ?, ?, 'hod', ?, ?)",
            [
                $schoolId,
                $d['name'],
                $d['email'],
                password_hash($plain, PASSWORD_DEFAULT),
                $d['department'] ?: null,
                $d['status'],
            ]
        );

        $appUrl  = rtrim($_ENV['APP_URL'] ?? '', '/');
        $appName = $_ENV['APP_NAME'] ?? 'SSD-ACMIS';
        $html    = self::welcomeEmail($d['name'], $appName, $d['email'], $plain, $appUrl, 'Head of Department');
        $sent    = MailService::send($d['email'], $d['name'], "Your HOD Account — {$appName}", $html);

        $msg = 'HOD account created.';
        if (!$sent) $msg .= " (Email failed — temporary password: {$plain})";
        Flash::set($sent ? 'success' : 'warning', $msg);
        $this->redirect('/hods');
        return '';
    }

    public function edit(string $id): string
    {
        $hod = Database::query(
            "SELECT id, name, email, department, status FROM users WHERE id = ? AND role = 'hod' LIMIT 1",
            [(int) $id]
        )->fetch();
        if (!$hod) {
            http_response_code(404);
            return $this->view('errors/404');
        }
        return $this->view('hods/form', [
            'hod' => $hod,
        ]);
    }

    public function update(string $id): string
    {
        $this->validateCsrf();
        $hod = Database::query(
            "SELECT id, email FROM users WHERE id = ? AND role = 'hod' LIMIT 1",
            [(int) $id]
        )->fetch();
        if (!$hod) {
            http_response_code(404);
            return $this->view('errors/404');
        }

        $d = $this->payload();

        if ($d['name'] === '' || $d['email'] === '') {
            Flash::set('danger', 'Name and email are required.');
            $this->redirect('/hods/' . (int) $id . '/edit');
            return '';
        }
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            Flash::set('danger', 'That email address looks invalid.');
            $this->redirect('/hods/' . (int) $id . '/edit');
            return '';
        }

        // Email-uniqueness check (allow keeping the same email).
        $clash = Database::query(
            "SELECT 1 FROM users WHERE email = ? AND id <> ? LIMIT 1",
            [$d['email'], (int) $id]
        )->fetch();
        if ($clash) {
            Flash::set('danger', 'Another user already has that email.');
            $this->redirect('/hods/' . (int) $id . '/edit');
            return '';
        }

        if ($d['password'] !== '') {
            if (strlen($d['password']) < 6) {
                Flash::set('danger', 'Password must be at least 6 characters.');
                $this->redirect('/hods/' . (int) $id . '/edit');
                return '';
            }
            Database::query(
                "UPDATE users SET name = ?, email = ?, department = ?, status = ?, password = ?
                 WHERE id = ?",
                [
                    $d['name'],
                    $d['email'],
                    $d['department'] ?: null,
                    $d['status'],
                    password_hash($d['password'], PASSWORD_DEFAULT),
                    (int) $id,
                ]
            );
        } else {
            Database::query(
                "UPDATE users SET name = ?, email = ?, department = ?, status = ?
                 WHERE id = ?",
                [
                    $d['name'],
                    $d['email'],
                    $d['department'] ?: null,
                    $d['status'],
                    (int) $id,
                ]
            );
        }

        Flash::set('success', 'HOD account updated.');
        $this->redirect('/hods');
        return '';
    }

    public function destroy(string $id): string
    {
        $this->validateCsrf();
        $hod = Database::query(
            "SELECT id FROM users WHERE id = ? AND role = 'hod' LIMIT 1",
            [(int) $id]
        )->fetch();
        if (!$hod) {
            http_response_code(404);
            return $this->view('errors/404');
        }
        Database::query("DELETE FROM users WHERE id = ?", [(int) $id]);
        Flash::set('success', 'HOD account removed.');
        $this->redirect('/hods');
        return '';
    }

    /* -------- helpers --------------------------------------------------- */

    private function payload(): array
    {
        return [
            'name'       => trim((string) $this->input('name', '')),
            'email'      => trim((string) $this->input('email', '')),
            'department' => trim((string) $this->input('department', '')),
            'password'   => (string) $this->input('password', ''),
            'status'     => in_array($this->input('status'), ['active', 'disabled'], true)
                                ? (string) $this->input('status')
                                : 'active',
        ];
    }

    private static function welcomeEmail(
        string $name, string $appName, string $email,
        string $password, string $appUrl, string $roleLabel
    ): string {
        return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="font-family:Inter,Arial,sans-serif;color:#212529;max-width:600px;margin:auto;padding:32px 24px;">
  <h2 style="margin-bottom:4px;">{$appName}</h2>
  <p style="color:#6c757d;margin-top:0;">School Management System</p>
  <hr style="border:none;border-top:1px solid #dee2e6;margin:20px 0;">
  <p>Hello <strong>{$name}</strong>,</p>
  <p>A <strong>{$roleLabel}</strong> account has been created for you.</p>
  <table style="background:#f8f9fa;border-radius:8px;padding:20px 24px;width:100%;margin:20px 0;border-collapse:collapse;">
    <tr><td style="padding:6px 0;color:#6c757d;font-size:14px;">Login URL</td>
        <td style="padding:6px 0;"><a href="{$appUrl}/login">{$appUrl}/login</a></td></tr>
    <tr><td style="padding:6px 0;color:#6c757d;font-size:14px;">Email</td>
        <td style="padding:6px 0;font-family:monospace;">{$email}</td></tr>
    <tr><td style="padding:6px 0;color:#6c757d;font-size:14px;">Temporary Password</td>
        <td style="padding:6px 0;font-family:monospace;font-size:18px;letter-spacing:2px;color:#dc3545;"><strong>{$password}</strong></td></tr>
  </table>
  <p style="color:#dc3545;font-size:14px;">⚠ Change your password after first login via <em>Account → Change Password</em>.</p>
  <hr style="border:none;border-top:1px solid #dee2e6;margin:20px 0;">
  <p style="font-size:12px;color:#adb5bd;">Sent by {$appName}.</p>
</body></html>
HTML;
    }
}
