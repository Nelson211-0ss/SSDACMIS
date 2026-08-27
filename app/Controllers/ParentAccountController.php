<?php
namespace App\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;
use App\Services\MailService;

/**
 * Admin-only management of Parent (Parent Portal) login accounts, and of
 * which student(s) each parent may view.
 *
 *   GET  /parents              -> list every users.role='parent' account
 *   GET  /parents/create       -> "new parent" form (+ child picker)
 *   POST /parents              -> create the parent (name, email, linked
 *                                  student_ids[], one marked primary)
 *   GET  /parents/{id}/edit    -> edit form
 *   POST /parents/{id}         -> update name/email/status/links
 *   POST /parents/{id}/delete  -> delete the user row (parent_students rows
 *                                  cascade automatically)
 *
 * Parents sign in at /parent/login with the *primary* linked child's
 * admission number as both username and password (see Auth::attemptParent())
 * — there's no password to set or email here, so this differs from
 * BursarAccountController/HodAccountController in that respect. Everything
 * else (account CRUD, school scoping) mirrors those controllers.
 */
class ParentAccountController extends Controller
{
    public function index(): string
    {
        $schoolId = Auth::schoolId();
        $sf = $schoolId !== null ? ' AND u.school_id = ?' : '';
        $sp = $schoolId !== null ? [$schoolId] : [];

        $parents = Database::query(
            "SELECT u.id, u.name, u.email, u.status, u.created_at,
                    GROUP_CONCAT(CONCAT(s.first_name, ' ', s.last_name) ORDER BY s.first_name SEPARATOR ', ') AS children_names,
                    COUNT(ps.student_id) AS children_count,
                    MAX(CASE WHEN ps.is_primary = 1 THEN s.admission_no END) AS login_admission_no
             FROM users u
             LEFT JOIN parent_students ps ON ps.parent_user_id = u.id
             LEFT JOIN students s ON s.id = ps.student_id
             WHERE u.role = 'parent'{$sf}
             GROUP BY u.id
             ORDER BY u.status DESC, u.name",
            $sp
        )->fetchAll();

        return $this->view('parents/index', ['parents' => $parents]);
    }

    public function create(): string
    {
        $schools  = $this->schoolsForPicker();
        $schoolId = $this->formSchoolId(null);

        return $this->view('parents/form', [
            'parentAccount' => null,
            'schools'       => $schools,
            'schoolId'      => $schoolId,
            'students'      => $schoolId ? $this->studentsForSchool($schoolId) : [],
            'linkedIds'     => [],
            'primaryId'     => 0,
        ]);
    }

    public function store(): string
    {
        $this->validateCsrf();
        $d = $this->payload();

        if ($d['name'] === '' || $d['email'] === '') {
            Flash::set('danger', 'Name and email are required.');
            $this->redirect('/parents/create');
            return '';
        }
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            Flash::set('danger', 'That email address looks invalid.');
            $this->redirect('/parents/create');
            return '';
        }

        $exists = Database::query("SELECT 1 FROM users WHERE email = ? LIMIT 1", [$d['email']])->fetch();
        if ($exists) {
            Flash::set('danger', 'That email is already in use.');
            $this->redirect('/parents/create');
            return '';
        }

        $schoolId = $this->formSchoolId(null);
        if (!$schoolId) {
            Flash::set('danger', 'Choose a school for this parent account.');
            $this->redirect('/parents/create');
            return '';
        }
        if (!$d['student_ids']) {
            Flash::set('danger', 'Link at least one child before saving — a parent signs in with a linked child\'s admission number.');
            $this->redirect('/parents/create?school_id=' . $schoolId);
            return '';
        }
        if (!$d['primary_student_id'] || !in_array($d['primary_student_id'], $d['student_ids'], true)) {
            Flash::set('danger', 'Choose which linked child\'s admission number this parent signs in with.');
            $this->redirect('/parents/create?school_id=' . $schoolId);
            return '';
        }

        // users.password is never checked for role='parent' (sign-in
        // compares live against students.admission_no — see
        // Auth::attemptParent()); this only satisfies the NOT NULL column.
        $unusedPlaceholder = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

        Database::query(
            "INSERT INTO users (school_id, name, email, password, role, status)
             VALUES (?, ?, ?, ?, 'parent', ?)",
            [$schoolId, $d['name'], $d['email'], $unusedPlaceholder, $d['status']]
        );
        $newId = (int) Database::connection()->lastInsertId();
        $this->syncChildren($newId, $schoolId, $d['student_ids'], $d['primary_student_id']);
        ActivityLog::record('create', 'parent_account', $newId, "Created parent account for {$d['name']}");

        $appUrl  = rtrim($_ENV['APP_URL'] ?? '', '/');
        $appName = $_ENV['APP_NAME'] ?? 'SSD-ACMIS';
        $html    = self::welcomeEmail($d['name'], $appName, $appUrl);
        MailService::send($d['email'], $d['name'], "Your Parent Portal Account — {$appName}", $html);

        Flash::set('success', 'Parent account created.');
        $this->redirect('/parents');
        return '';
    }

    public function edit(string $id): string
    {
        $parentAccount = Database::query(
            "SELECT id, name, email, status, school_id FROM users WHERE id = ? AND role = 'parent' LIMIT 1",
            [(int) $id]
        )->fetch();
        if (!$parentAccount) {
            http_response_code(404);
            return $this->view('errors/404');
        }

        $schoolId  = (int) $parentAccount['school_id'];
        $linkedRows = Database::query(
            "SELECT student_id, is_primary FROM parent_students WHERE parent_user_id = ?",
            [(int) $id]
        )->fetchAll();
        $primaryId = 0;
        foreach ($linkedRows as $r) {
            if ((int) $r['is_primary'] === 1) { $primaryId = (int) $r['student_id']; break; }
        }

        return $this->view('parents/form', [
            'parentAccount' => $parentAccount,
            'schools'       => $this->schoolsForPicker(),
            'schoolId'      => $schoolId,
            'students'      => $this->studentsForSchool($schoolId),
            'linkedIds'     => array_map(static fn ($r) => (int) $r['student_id'], $linkedRows),
            'primaryId'     => $primaryId,
        ]);
    }

    public function update(string $id): string
    {
        $this->validateCsrf();
        $parentAccount = Database::query(
            "SELECT id, email, school_id FROM users WHERE id = ? AND role = 'parent' LIMIT 1",
            [(int) $id]
        )->fetch();
        if (!$parentAccount) {
            http_response_code(404);
            return $this->view('errors/404');
        }

        $d = $this->payload();

        if ($d['name'] === '' || $d['email'] === '') {
            Flash::set('danger', 'Name and email are required.');
            $this->redirect('/parents/' . (int) $id . '/edit');
            return '';
        }
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            Flash::set('danger', 'That email address looks invalid.');
            $this->redirect('/parents/' . (int) $id . '/edit');
            return '';
        }

        $clash = Database::query(
            "SELECT 1 FROM users WHERE email = ? AND id <> ? LIMIT 1",
            [$d['email'], (int) $id]
        )->fetch();
        if ($clash) {
            Flash::set('danger', 'Another user already has that email.');
            $this->redirect('/parents/' . (int) $id . '/edit');
            return '';
        }
        if (!$d['student_ids']) {
            Flash::set('danger', 'Link at least one child before saving — a parent signs in with a linked child\'s admission number.');
            $this->redirect('/parents/' . (int) $id . '/edit');
            return '';
        }
        if (!$d['primary_student_id'] || !in_array($d['primary_student_id'], $d['student_ids'], true)) {
            Flash::set('danger', 'Choose which linked child\'s admission number this parent signs in with.');
            $this->redirect('/parents/' . (int) $id . '/edit');
            return '';
        }

        Database::query(
            "UPDATE users SET name = ?, email = ?, status = ? WHERE id = ?",
            [$d['name'], $d['email'], $d['status'], (int) $id]
        );

        // School assignment isn't editable here (same as a bursar/HOD
        // account) — a parent's linked children always come from the
        // school they were created under.
        $this->syncChildren((int) $id, (int) $parentAccount['school_id'], $d['student_ids'], $d['primary_student_id']);

        ActivityLog::record('update', 'parent_account', (int) $id, "Updated parent account for {$d['name']}");
        Flash::set('success', 'Parent account updated.');
        $this->redirect('/parents');
        return '';
    }

    public function destroy(string $id): string
    {
        $this->validateCsrf();
        $parentAccount = Database::query(
            "SELECT id, name FROM users WHERE id = ? AND role = 'parent' LIMIT 1",
            [(int) $id]
        )->fetch();
        if (!$parentAccount) {
            http_response_code(404);
            return $this->view('errors/404');
        }
        // parent_students rows cascade (fk_ps_parent ON DELETE CASCADE).
        Database::query("DELETE FROM users WHERE id = ?", [(int) $id]);
        ActivityLog::record('delete', 'parent_account', (int) $id, "Deleted parent account {$parentAccount['name']}");
        Flash::set('success', 'Parent account removed.');
        $this->redirect('/parents');
        return '';
    }

    /* -------- helpers --------------------------------------------------- */

    private function payload(): array
    {
        $studentIds = array_map('intval', (array) $this->input('student_ids', []));

        return [
            'name'               => trim((string) $this->input('name', '')),
            'email'              => trim((string) $this->input('email', '')),
            'status'             => in_array($this->input('status'), ['active', 'disabled'], true)
                                         ? (string) $this->input('status')
                                         : 'active',
            'student_ids'        => array_values(array_unique(array_filter($studentIds, static fn ($v) => $v > 0))),
            'primary_student_id' => (int) $this->input('primary_student_id', 0),
        ];
    }

    /**
     * Schools the current admin may assign a parent to. Empty for a
     * school_admin (their own school is implicit, no picker needed).
     */
    private function schoolsForPicker(): array
    {
        if (Auth::schoolId() !== null) {
            return [];
        }
        return Database::query("SELECT id, name FROM schools WHERE status = 'active' ORDER BY name")->fetchAll();
    }

    /**
     * School to scope the create form's student picker to: the
     * school_admin's own school, or (super admin) whatever the ?school_id
     * query param picked, defaulting to the first active school.
     */
    private function formSchoolId(?int $fallback): ?int
    {
        $own = Auth::schoolId();
        if ($own !== null) {
            return $own;
        }
        $picked = (int) $this->input('school_id', 0);
        if ($picked > 0) {
            $row = Database::query("SELECT id FROM schools WHERE id = ? AND status = 'active' LIMIT 1", [$picked])->fetch();
            if ($row) return $picked;
        }
        $first = Database::query("SELECT id FROM schools WHERE status = 'active' ORDER BY name LIMIT 1")->fetch();
        return $first ? (int) $first['id'] : $fallback;
    }

    private function studentsForSchool(int $schoolId): array
    {
        return Database::query(
            "SELECT s.id, s.admission_no, s.first_name, s.last_name, c.name AS class_name
             FROM students s LEFT JOIN classes c ON c.id = s.class_id
             WHERE s.school_id = ?
             ORDER BY c.level, c.name, s.first_name, s.last_name",
            [$schoolId]
        )->fetchAll();
    }

    /**
     * Replace this parent's linked children with $studentIds — filtered
     * down to students that actually belong to $schoolId first, so a
     * tampered request can't link a student from another school. Exactly
     * one row (the caller-validated $primaryStudentId, which must be one
     * of $studentIds) is marked is_primary — that child's admission number
     * becomes this parent's sign-in credential (Auth::attemptParent()).
     */
    private function syncChildren(int $parentUserId, int $schoolId, array $studentIds, int $primaryStudentId): void
    {
        Database::query("DELETE FROM parent_students WHERE parent_user_id = ?", [$parentUserId]);
        if (!$studentIds) return;

        $ph   = implode(',', array_fill(0, count($studentIds), '?'));
        $rows = Database::query(
            "SELECT id FROM students WHERE school_id = ? AND id IN ($ph)",
            array_merge([$schoolId], $studentIds)
        )->fetchAll();

        foreach ($rows as $r) {
            $sid = (int) $r['id'];
            Database::query(
                "INSERT INTO parent_students (school_id, parent_user_id, student_id, is_primary) VALUES (?, ?, ?, ?)",
                [$schoolId, $parentUserId, $sid, $sid === $primaryStudentId ? 1 : 0]
            );
        }
    }

    private static function welcomeEmail(string $name, string $appName, string $appUrl): string
    {
        return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="font-family:Inter,Arial,sans-serif;color:#212529;max-width:600px;margin:auto;padding:32px 24px;">
  <h2 style="margin-bottom:4px;">{$appName}</h2>
  <p style="color:#6c757d;margin-top:0;">School Management System</p>
  <hr style="border:none;border-top:1px solid #dee2e6;margin:20px 0;">
  <p>Hello <strong>{$name}</strong>,</p>
  <p>A <strong>Parent Portal</strong> account has been created for you. Use it to view your child's report cards, fees and attendance.</p>
  <table style="background:#f8f9fa;border-radius:8px;padding:20px 24px;width:100%;margin:20px 0;border-collapse:collapse;">
    <tr><td style="padding:6px 0;color:#6c757d;font-size:14px;">Login URL</td>
        <td style="padding:6px 0;"><a href="{$appUrl}/parent/login">{$appUrl}/parent/login</a></td></tr>
    <tr><td style="padding:6px 0;color:#6c757d;font-size:14px;">Username &amp; Password</td>
        <td style="padding:6px 0;">Your child's admission number, entered in both fields.</td></tr>
  </table>
  <hr style="border:none;border-top:1px solid #dee2e6;margin:20px 0;">
  <p style="font-size:12px;color:#adb5bd;">Sent by {$appName}.</p>
</body></html>
HTML;
    }
}
