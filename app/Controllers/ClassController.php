<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;

class ClassController extends Controller
{
    public function index(): string
    {
        $classes = Database::query(
            "SELECT c.*,
                    t.first_name AS teacher_first, t.last_name AS teacher_last,
                    (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) AS student_count
             FROM classes c
             LEFT JOIN staff t ON t.id = c.class_teacher_id
             ORDER BY c.name"
        )->fetchAll();
        $staff = Database::query(
            "SELECT id, first_name, last_name FROM staff ORDER BY first_name, last_name"
        )->fetchAll();
        return $this->view('classes/index', compact('classes', 'staff'));
    }

    public function store(): string
    {
        $this->validateCsrf();
        $name   = trim((string) $this->input('name'));
        $level  = trim((string) $this->input('level'));
        $prefix = strtoupper(trim((string) $this->input('admission_prefix')));
        if ($name === '') { Flash::set('danger', 'Class name is required.'); $this->redirect('/classes'); return ''; }
        if ($prefix === '') $prefix = $this->derivePrefix($name);
        if (!preg_match('/^[A-Z0-9]{1,10}$/', $prefix)) {
            Flash::set('danger', 'Admission prefix must be 1–10 letters/digits (uppercase).');
            $this->redirect('/classes'); return '';
        }
        Database::query(
            "INSERT INTO classes (name, level, admission_prefix) VALUES (?, ?, ?)",
            [$name, $level, $prefix]
        );
        Flash::set('success', "Class added (admission prefix: {$prefix}).");
        $this->redirect('/classes'); return '';
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
        Database::query(
            "UPDATE classes SET admission_prefix = ? WHERE id = ?",
            [$prefix, (int) $id]
        );
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
        Database::query(
            "UPDATE classes SET class_teacher_id = ? WHERE id = ?",
            [$teacherId ?: null, (int) $id]
        );
        Flash::set('success', 'Class teacher updated.');
        $this->redirect('/classes'); return '';
    }

    public function destroy(string $id): string
    {
        $this->validateCsrf();
        Database::query("DELETE FROM classes WHERE id = ?", [(int)$id]);
        Flash::set('success', 'Class removed.');
        $this->redirect('/classes'); return '';
    }
}
