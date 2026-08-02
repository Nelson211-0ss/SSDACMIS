<?php
namespace App\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;

class ClassController extends Controller
{
    public function index(): string
    {
        $schoolId = Auth::schoolId();
        $isAdmin = Auth::role() === 'admin';
        $selectedSchool = null;
        if ($isAdmin) {
            $sel = (int) $this->input('school_id', 0) ?: null;
            if ($sel !== null) { $schoolId = $sel; $selectedSchool = $sel; }
        }
        $sf  = $schoolId !== null ? ' AND c.school_id = ?' : '';
        $sp  = $schoolId !== null ? [$schoolId] : [];

        $classes = Database::query(
            "SELECT c.*,
                    t.first_name AS teacher_first, t.last_name AS teacher_last,
                    (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) AS student_count
             FROM classes c
             LEFT JOIN staff t ON t.id = c.class_teacher_id
             WHERE 1=1{$sf}
             ORDER BY c.name",
            $sp
        )->fetchAll();
        $staff = Database::query(
            "SELECT id, first_name, last_name FROM staff" .
            ($schoolId !== null ? " WHERE school_id = ?" : "") .
            " ORDER BY first_name, last_name",
            $sp
        )->fetchAll();
        $schools = $isAdmin ? Database::query("SELECT id, name FROM schools WHERE status='active' ORDER BY name")->fetchAll() : [];
        return $this->view('classes/index', compact('classes', 'staff', 'schools', 'selectedSchool'));
    }

    public function store(): string
    {
        $this->validateCsrf();
        $name   = trim((string) $this->input('name'));
        $allowed = ['Form 1', 'Form 2', 'Form 3', 'Form 4'];
        $level  = trim((string) $this->input('level'));
        if (!in_array($level, $allowed, true)) $level = '';
        $prefix = strtoupper(trim((string) $this->input('admission_prefix')));
        if ($name === '') { Flash::set('danger', 'Class name is required.'); $this->redirect('/classes'); return ''; }
        if ($prefix === '') $prefix = $this->derivePrefix($name);
        if (!preg_match('/^[A-Z0-9]{1,10}$/', $prefix)) {
            Flash::set('danger', 'Admission prefix must be 1–10 letters/digits (uppercase).');
            $this->redirect('/classes'); return '';
        }
        $schoolId = Auth::schoolId() ?? (int) $this->input('school_id', 0) ?: 1;
        Database::query(
            "INSERT INTO classes (school_id, name, level, admission_prefix) VALUES (?, ?, ?, ?)",
            [$schoolId, $name, $level, $prefix]
        );
        $newId = (int) Database::connection()->lastInsertId();
        ActivityLog::record('create', 'class', $newId, "Created class {$name}");
        Flash::set('success', "Class added (admission prefix: {$prefix}).");
        $this->redirect('/classes'); return '';
    }

    /**
     * Admin renames a class. Level is deliberately not editable here (or
     * anywhere) — once a class is created its level is fixed, only the
     * name can change (e.g. "Form 1A" -> "Form 1 Blue").
     */
    public function rename(string $id): string
    {
        $this->validateCsrf();
        $name = trim((string) $this->input('name'));
        if ($name === '') {
            Flash::set('danger', 'Class name is required.');
            $this->redirect('/classes');
            return '';
        }

        $schoolId = Auth::schoolId();
        $findSql = "SELECT id, school_id FROM classes WHERE id = ?";
        $findParams = [(int) $id];
        if ($schoolId !== null) { $findSql .= ' AND school_id = ?'; $findParams[] = $schoolId; }
        $class = Database::query($findSql, $findParams)->fetch();
        if (!$class) {
            Flash::set('danger', 'That class does not exist or is not in your school.');
            $this->redirect('/classes');
            return '';
        }

        $dupe = Database::query(
            "SELECT 1 FROM classes WHERE school_id = ? AND name = ? AND id != ? LIMIT 1",
            [(int) $class['school_id'], $name, (int) $id]
        )->fetch();
        if ($dupe) {
            Flash::set('danger', "A class named \"{$name}\" already exists in this school.");
            $this->redirect('/classes');
            return '';
        }

        Database::query("UPDATE classes SET name = ? WHERE id = ?", [$name, (int) $id]);
        ActivityLog::record('update', 'class', (int) $id, "Renamed class #{$id} to '{$name}'");
        Flash::set('success', 'Class name updated.');
        $this->redirect('/classes');
        return '';
    }

    /** Admin updates the admission prefix for a class. */
    public function setPrefix(string $id): string
    {
        $this->validateCsrf();
        $prefix = strtoupper(trim((string) $this->input('admission_prefix')));
        if (!preg_match('/^[A-Z0-9]{1,10}$/', $prefix)) {
            Flash::set('danger', 'Admission prefix must be 1–10 letters/digits.');
            $this->redirect('/classes'); return '';
        }
        $schoolId = Auth::schoolId();
        $sql = "UPDATE classes SET admission_prefix = ? WHERE id = ?";
        $params = [$prefix, (int) $id];
        if ($schoolId !== null) { $sql .= ' AND school_id = ?'; $params[] = $schoolId; }
        Database::query($sql, $params);
        ActivityLog::record('update', 'class', (int) $id, "Set admission prefix for class #{$id} to '{$prefix}'");
        Flash::set('success', 'Admission prefix updated.');
        $this->redirect('/classes'); return '';
    }

    /** Derive an admission prefix from a class name (e.g. 'Form 1A' -> 'F1A'). */
    private function derivePrefix(string $name): string
    {
        preg_match_all('/([A-Z])|(\d+)/', $name, $m, PREG_SET_ORDER);
        $parts = [];
        foreach ($m as $tok) $parts[] = $tok[0];
        $p = strtoupper(implode('', $parts));
        if ($p === '') $p = strtoupper(preg_replace('/[^a-z0-9]/i', '', $name) ?? '');
        return substr($p, 0, 10);
    }

    /** Admin assigns / clears the class teacher (homeroom). */
    public function setTeacher(string $id): string
    {
        $this->validateCsrf();
        $teacherId = (int) $this->input('class_teacher_id');
        $schoolId = Auth::schoolId();
        $sql = "UPDATE classes SET class_teacher_id = ? WHERE id = ?";
        $params = [$teacherId ?: null, (int) $id];
        if ($schoolId !== null) { $sql .= ' AND school_id = ?'; $params[] = $schoolId; }
        Database::query($sql, $params);
        ActivityLog::record('update', 'class', (int) $id, $teacherId
            ? "Set class teacher for class #{$id} to staff #{$teacherId}"
            : "Cleared class teacher for class #{$id}");
        Flash::set('success', 'Class teacher updated.');
        $this->redirect('/classes'); return '';
    }

    public function destroy(string $id): string
    {
        $this->validateCsrf();
        $schoolId = Auth::schoolId();

        $findSql = "SELECT name FROM classes WHERE id = ?";
        $findParams = [(int) $id];
        if ($schoolId !== null) { $findSql .= ' AND school_id = ?'; $findParams[] = $schoolId; }
        $row = Database::query($findSql, $findParams)->fetch();
        $name = $row['name'] ?? null;

        $sql = "DELETE FROM classes WHERE id = ?";
        $params = [(int)$id];
        if ($schoolId !== null) { $sql .= ' AND school_id = ?'; $params[] = $schoolId; }
        Database::query($sql, $params);
        ActivityLog::record('delete', 'class', (int) $id, $name !== null ? "Deleted class {$name}" : "Deleted class #{$id}");
        Flash::set('success', 'Class removed.');
        $this->redirect('/classes'); return '';
    }
}
