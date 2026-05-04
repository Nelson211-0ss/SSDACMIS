<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;

class StaffController extends Controller
{
    public function index(): string
    {
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
             GROUP BY s.id
             ORDER BY s.created_at DESC"
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

        if ($d['email'] === '' || $d['first_name'] === '' || $d['password'] === '') {
            Flash::set('danger', 'Email, first name and password are required.');
            $this->redirect('/staff/create'); return '';
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, 'active')")
                ->execute([
                    $d['first_name'].' '.$d['last_name'],
                    $d['email'],
                    password_hash($d['password'], PASSWORD_DEFAULT),
                    $d['role'],
                ]);
            $userId = (int) $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO staff (user_id, first_name, last_name, phone, position, hire_date)
                           VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([$userId, $d['first_name'], $d['last_name'], $d['phone'], $d['position'], $d['hire_date'] ?: null]);
            $staffId = (int) $pdo->lastInsertId();
            $this->syncSubjects($staffId, $d['subject_ids']);
            $pdo->commit();
            $count = count($d['subject_ids']);
            Flash::set('success', "Staff member created"
                . ($count ? " with $count subject" . ($count === 1 ? '' : 's') : '') . '.');
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
            'role'        => in_array($this->input('role'), ['admin','staff'], true) ? $this->input('role') : 'staff',
            'password'    => (string) $this->input('password', ''),
            'subject_ids' => $subjectIds,
        ];
    }

    /** Return all subjects ordered by category then name (for the form picker). */
    private function allSubjects(): array
    {
        return Database::query(
            "SELECT id, name, code, category FROM subjects ORDER BY category, name"
        )->fetchAll();
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
