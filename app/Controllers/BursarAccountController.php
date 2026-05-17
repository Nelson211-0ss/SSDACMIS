<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;
use App\Services\MailService;

/**
 * Admin-only management of Bursar (Fees Management) login accounts.
 *
 *   GET  /bursars              -> list every users.role='bursar' account
 *   GET  /bursars/create       -> "new bursar" form
 *   POST /bursars              -> create the bursar (name, email, password)
 *   GET  /bursars/{id}/edit    -> edit form
 *   POST /bursars/{id}         -> update name/email/status (+ optional password)
 *   POST /bursars/{id}/delete  -> delete the user row
 *
 * Bursars created here sign in at /login and land on /bursar (the
 * Fees Management portal). The Bursar portal is fully isolated from the
 * main school nav and HOD nav — bursars only see Fees module pages.
 *
 * Mirrors HodAccountController so admins manage both portals the same way.
 */
class BursarAccountController extends Controller
{
    public function index(): string
    {
        $schoolId = Auth::schoolId();
        $sf = $schoolId !== null ? ' AND school_id = ?' : '';
        $sp = $schoolId !== null ? [$schoolId] : [];

        $bursars = Database::query(
            "SELECT id, name, email, status, created_at
             FROM users WHERE role = 'bursar'{$sf}
             ORDER BY status DESC, name",
            $sp
        )->fetchAll();

        return $this->view('bursars/index', ['bursars' => $bursars]);
    }

    public function create(): string
    {
        return $this->view('bursars/form', ['bursar' => null]);
    }

    public function store(): string
    {
        $this->validateCsrf();
        $d = $this->payload();

        if ($d['name'] === '' || $d['email'] === '') {
            Flash::set('danger', 'Name and email are required.');
            $this->redirect('/bursars/create');
            return '';
        }
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            Flash::set('danger', 'That email address looks invalid.');
            $this->redirect('/bursars/create');
            return '';
        }

        $exists = Database::query("SELECT 1 FROM users WHERE email = ? LIMIT 1", [$d['email']])->fetch();
        if ($exists) {
            Flash::set('danger', 'That email is already in use.');
            $this->redirect('/bursars/create');
            return '';
        }

        $plain    = bin2hex(random_bytes(8));
        $schoolId = Auth::schoolId() ?? 1;

        Database::query(
            "INSERT INTO users (school_id, name, email, password, role, status)
             VALUES (?, ?, ?, ?, 'bursar', ?)",
            [$schoolId, $d['name'], $d['email'], password_hash($plain, PASSWORD_DEFAULT), $d['status']]
        );

        $appUrl  = rtrim($_ENV['APP_URL'] ?? '', '/');
        $appName = $_ENV['APP_NAME'] ?? 'SSD-ACMIS';
        $html    = self::welcomeEmail($d['name'], $appName, $d['email'], $plain, $appUrl, 'Bursar');
        $sent    = MailService::send($d['email'], $d['name'], "Your Bursar Account — {$appName}", $html);

        $msg = 'Bursar account created.';
        if (!$sent) $msg .= " (Email failed — temporary password: {$plain})";
        Flash::set($sent ? 'success' : 'warning', $msg);
        $this->redirect('/bursars');
        return '';
    }

    public function edit(string $id): string
    {
        $bursar = Database::query(
            "SELECT id, name, email, status FROM users WHERE id = ? AND role = 'bursar' LIMIT 1",
            [(int) $id]
        )->fetch();
        if (!$bursar) {
            http_response_code(404);
            return $this->view('errors/404');
        }
        return $this->view('bursars/form', ['bursar' => $bursar]);
    }

    public function update(string $id): string
    {
        $this->validateCsrf();
        $bursar = Database::query(
            "SELECT id, email FROM users WHERE id = ? AND role = 'bursar' LIMIT 1",
            [(int) $id]
        )->fetch();
        if (!$bursar) {
            http_response_code(404);
            return $this->view('errors/404');
        }

        $d = $this->payload();

        if ($d['name'] === '' || $d['email'] === '') {
            Flash::set('danger', 'Name and email are required.');
            $this->redirect('/bursars/' . (int) $id . '/edit');
            return '';
        }
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            Flash::set('danger', 'That email address looks invalid.');
            $this->redirect('/bursars/' . (int) $id . '/edit');
            return '';
        }

        $clash = Database::query(
            "SELECT 1 FROM users WHERE email = ? AND id <> ? LIMIT 1",
            [$d['email'], (int) $id]
        )->fetch();
        if ($clash) {
            Flash::set('danger', 'Another user already has that email.');
            $this->redirect('/bursars/' . (int) $id . '/edit');
            return '';
        }

        if ($d['password'] !== '') {
            if (strlen($d['password']) < 6) {
                Flash::set('danger', 'Password must be at least 6 characters.');
                $this->redirect('/bursars/' . (int) $id . '/edit');
                return '';
            }
            Database::query(
                "UPDATE users SET name = ?, email = ?, status = ?, password = ?
                 WHERE id = ?",
                [
                    $d['name'],
                    $d['email'],
                    $d['status'],
                    password_hash($d['password'], PASSWORD_DEFAULT),
                    (int) $id,
                ]
            );
        } else {
            Database::query(
                "UPDATE users SET name = ?, email = ?, status = ?
                 WHERE id = ?",
                [$d['name'], $d['email'], $d['status'], (int) $id]
            );
        }

        Flash::set('success', 'Bursar account updated.');
        $this->redirect('/bursars');
        return '';
    }

    public function destroy(string $id): string
    {
        $this->validateCsrf();
        $bursar = Database::query(
            "SELECT id FROM users WHERE id = ? AND role = 'bursar' LIMIT 1",
            [(int) $id]
        )->fetch();
        if (!$bursar) {
            http_response_code(404);
            return $this->view('errors/404');
        }
        // Protect transaction history: payments.recorded_by FK is ON DELETE
        // SET NULL so deleting a bursar simply orphans the "entered by"
        // column on past payments — receipts remain intact.
        Database::query("DELETE FROM users WHERE id = ?", [(int) $id]);
        Flash::set('success', 'Bursar account removed.');
        $this->redirect('/bursars');
        return '';
    }

    /* -------- helpers --------------------------------------------------- */

    private function payload(): array
    {
        return [
            'name'     => trim((string) $this->input('name', '')),
            'email'    => trim((string) $this->input('email', '')),
            'password' => (string) $this->input('password', ''),
            'status'   => in_array($this->input('status'), ['active', 'disabled'], true)
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
