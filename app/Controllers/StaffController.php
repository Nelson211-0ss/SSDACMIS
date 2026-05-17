<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;
use App\Services\MailService;

class StaffController extends Controller
{
    public function index(): string
    {
        $schoolId = Auth::schoolId();
        $sf = $schoolId !== null ? ' WHERE s.school_id = ?' : '';
        $sp = $schoolId !== null ? [$schoolId] : [];

        $staff = Database::query(
            "SELECT s.*, u.email, u.role,
                    GROUP_CONCAT(DISTINCT CONCAT(sub.id, '|', sub.name, '|', sub.category)
                                 ORDER BY sub.name SEPARATOR ';;') AS subjects_csv,
                    GROUP_CONCAT(DISTINCT dh.category SEPARATOR ',') AS hod_categories
             FROM staff s
             LEFT JOIN users u           ON u.id = s.user_id
             LEFT JOIN staff_subjects ss ON ss.staff_id = s.id
             LEFT JOIN subjects sub      ON sub.id = ss.subject_id
             LEFT JOIN department_heads dh ON dh.staff_id = s.id
             {$sf}
             GROUP BY s.id
             ORDER BY s.created_at DESC",
            $sp
        )->fetchAll();
        return $this->view('staff/index', compact('staff'));
    }

    public function create(): string
    {
        return $this->view('staff/form', [
            'staff'    => null,
            'subjects' => $this->allSubjects(),
            'staffSubjectIds' => [],
        ]);
    }

    public function store(): string
    {
        $this->validateCsrf();
        $d = $this->payload();

        if ($d['email'] === '' || $d['first_name'] === '') {
            Flash::set('danger', 'Email and first name are required.');
            $this->redirect('/staff/create'); return '';
        }

        $exists = Database::query("SELECT 1 FROM users WHERE email = ? LIMIT 1", [$d['email']])->fetch();
        if ($exists) {
            Flash::set('danger', 'That email is already in use.');
            $this->redirect('/staff/create'); return '';
        }

        $plain    = bin2hex(random_bytes(8));
        $schoolId = Auth::schoolId() ?? 1;

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare("INSERT INTO users (school_id, name, email, password, role, status) VALUES (?, ?, ?, ?, ?, 'active')")
                ->execute([
                    $schoolId,
                    $d['first_name'] . ' ' . $d['last_name'],
                    $d['email'],
                    password_hash($plain, PASSWORD_DEFAULT),
                    $d['role'],
                ]);
            $userId = (int) $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO staff (school_id, user_id, first_name, last_name, phone, position, hire_date)
                           VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$schoolId, $userId, $d['first_name'], $d['last_name'], $d['phone'], $d['position'], $d['hire_date'] ?: null]);
            $staffId = (int) $pdo->lastInsertId();
            $this->syncSubjects($staffId, $d['subject_ids']);
            $pdo->commit();

            $fullName = $d['first_name'] . ' ' . $d['last_name'];
            $appUrl   = rtrim($_ENV['APP_URL'] ?? '', '/');
            $appName  = $_ENV['APP_NAME'] ?? 'SSD-ACMIS';
            $html     = self::welcomeEmail($fullName, $appName, $d['email'], $plain, $appUrl, 'Staff');
            $sent     = MailService::send($d['email'], $fullName, "Your Staff Account — {$appName}", $html);

            $count = count($d['subject_ids']);
            $msg   = "Staff member created" . ($count ? " with $count subject" . ($count === 1 ? '' : 's') : '') . '.';
            if (!$sent) $msg .= " (Email failed — temporary password: {$plain})";
            Flash::set($sent ? 'success' : 'warning', $msg);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Flash::set('danger', 'Could not create staff: ' . $e->getMessage());
        }
        $this->redirect('/staff'); return '';
    }

    public function edit(string $id): string
    {
        $staff = Database::query(
            "SELECT s.*, u.email, u.role FROM staff s LEFT JOIN users u ON u.id = s.user_id WHERE s.id = ?",
            [(int)$id]
        )->fetch();
        if (!$staff) { http_response_code(404); return $this->view('errors/404'); }
        $rows = Database::query(
            "SELECT subject_id FROM staff_subjects WHERE staff_id = ?",
            [(int) $id]
        )->fetchAll();
        $staffSubjectIds = array_map(fn ($r) => (int) $r['subject_id'], $rows);
        return $this->view('staff/form', [
            'staff'           => $staff,
            'subjects'        => $this->allSubjects(),
            'staffSubjectIds' => $staffSubjectIds,
        ]);
    }

    public function update(string $id): string
    {
        $this->validateCsrf();
        $d = $this->payload();
        $row = Database::query("SELECT user_id FROM staff WHERE id = ?", [(int)$id])->fetch();
        if (!$row) { http_response_code(404); return $this->view('errors/404'); }

        Database::query(
            "UPDATE staff SET first_name=?, last_name=?, phone=?, position=?, hire_date=? WHERE id=?",
            [$d['first_name'], $d['last_name'], $d['phone'], $d['position'], $d['hire_date'] ?: null, (int)$id]
        );

        if ($row['user_id']) {
            if ($d['password']) {
                Database::query("UPDATE users SET name=?, email=?, role=?, password=? WHERE id=?",
                    [$d['first_name'].' '.$d['last_name'], $d['email'], $d['role'], password_hash($d['password'], PASSWORD_DEFAULT), $row['user_id']]);
            } else {
                Database::query("UPDATE users SET name=?, email=?, role=? WHERE id=?",
                    [$d['first_name'].' '.$d['last_name'], $d['email'], $d['role'], $row['user_id']]);
            }
        }

        $this->syncSubjects((int) $id, $d['subject_ids']);

        Flash::set('success', 'Staff member updated.');
        $this->redirect('/staff'); return '';
    }

    public function destroy(string $id): string
    {
        $this->validateCsrf();
        $row = Database::query("SELECT user_id FROM staff WHERE id = ?", [(int)$id])->fetch();
        if ($row) {
            Database::query("DELETE FROM staff WHERE id = ?", [(int)$id]);
            if ($row['user_id']) Database::query("DELETE FROM users WHERE id = ?", [$row['user_id']]);
        }
        Flash::set('success', 'Staff removed.');
        $this->redirect('/staff'); return '';
    }

    private function payload(): array
    {
        $raw = $_POST['subject_ids'] ?? [];
        $subjectIds = array_values(array_unique(array_filter(
            array_map('intval', is_array($raw) ? $raw : []),
            static fn ($id) => $id > 0
        )));
        return [
            'first_name'  => trim((string)$this->input('first_name')),
            'last_name'   => trim((string)$this->input('last_name')),
            'email'       => trim((string)$this->input('email')),
            'phone'       => trim((string)$this->input('phone')),
            'position'    => trim((string)$this->input('position')),
            'hire_date'   => $this->input('hire_date'),
            'role'        => in_array($this->input('role'), ['admin','school_admin','staff'], true) ? $this->input('role') : 'staff',
            'subject_ids' => $subjectIds,
        ];
    }

    /** Return subjects scoped to the current user's school. */
    private function allSubjects(): array
    {
        $schoolId = Auth::schoolId();
        $sf = $schoolId !== null ? ' WHERE school_id = ?' : '';
        $sp = $schoolId !== null ? [$schoolId] : [];
        return Database::query(
            "SELECT id, name, code, category FROM subjects{$sf} ORDER BY category, name",
            $sp
        )->fetchAll();
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
        <td style="padding:6px 0;font-weight:600;"><a href="{$appUrl}/login">{$appUrl}/login</a></td></tr>
    <tr><td style="padding:6px 0;color:#6c757d;font-size:14px;">Email</td>
        <td style="padding:6px 0;font-family:monospace;">{$email}</td></tr>
    <tr><td style="padding:6px 0;color:#6c757d;font-size:14px;">Temporary Password</td>
        <td style="padding:6px 0;font-family:monospace;font-size:18px;letter-spacing:2px;color:#dc3545;"><strong>{$password}</strong></td></tr>
  </table>
  <p style="color:#dc3545;font-size:14px;">⚠ Change your password after first login via <em>Account → Change Password</em>.</p>
  <hr style="border:none;border-top:1px solid #dee2e6;margin:20px 0;">
  <p style="font-size:12px;color:#adb5bd;">Sent by {$appName}. Contact your administrator if unexpected.</p>
</body></html>
HTML;
    }

    /**
     * Replace the staff member's subject assignments with the given IDs.
     * Done atomically (delete-then-insert) inside the surrounding transaction.
     */
    private function syncSubjects(int $staffId, array $subjectIds): void
    {
        $pdo = Database::connection();
        $pdo->prepare("DELETE FROM staff_subjects WHERE staff_id = ?")->execute([$staffId]);
        if (!$subjectIds) return;
        $ins = $pdo->prepare("INSERT IGNORE INTO staff_subjects (staff_id, subject_id) VALUES (?, ?)");
        foreach ($subjectIds as $sid) {
            $ins->execute([$staffId, (int) $sid]);
        }
    }
}
