<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;

/**
 * Admin manages which staff member teaches which subject in which class.
 * Each row in teaching_assignments grants a teacher permission to enter
 * marks for one (class, subject) pair.
 */
class TeachingController extends Controller
{
    public function index(): string
    {
        $assignments = Database::query(
            "SELECT ta.id,
                    ta.staff_id, ta.class_id, ta.subject_id,
                    s.first_name, s.last_name,
                    c.name AS class_name,
                    sub.name AS subject_name, sub.category
             FROM teaching_assignments ta
             JOIN staff    s   ON s.id   = ta.staff_id
             JOIN classes  c   ON c.id   = ta.class_id
             JOIN subjects sub ON sub.id = ta.subject_id
             ORDER BY c.name, sub.name, s.first_name"
        )->fetchAll();

        $heads = Database::query(
            "SELECT dh.staff_id, dh.category,
                    s.first_name, s.last_name
             FROM department_heads dh
             JOIN staff s ON s.id = dh.staff_id
             ORDER BY dh.category, s.first_name"
        )->fetchAll();

        $staff    = Database::query(
            "SELECT id, first_name, last_name FROM staff ORDER BY first_name, last_name"
        )->fetchAll();
        $classes  = Database::query("SELECT id, name FROM classes ORDER BY name")->fetchAll();
        $subjects = Database::query(
            "SELECT id, name, category FROM subjects ORDER BY category, name"
        )->fetchAll();

        return $this->view('teaching/index', compact(
            'assignments', 'heads', 'staff', 'classes', 'subjects'
        ));
    }

    /** Admin appoints a staff member as Head of a subject department. */
    public function storeHead(): string
    {
        $this->validateCsrf();
        $staffId  = (int) $this->input('staff_id');
        $category = (string) $this->input('category');
        $allowed  = ['core', 'science', 'arts', 'optional'];

        if (!$staffId || !in_array($category, $allowed, true)) {
            Flash::set('danger', 'Please choose a teacher and a valid department.');
            $this->redirect('/teaching'); return '';
        }
        Database::query(
            "INSERT IGNORE INTO department_heads (staff_id, category) VALUES (?, ?)",
            [$staffId, $category]
        );
        Flash::set('success', 'Department head appointed.');
        $this->redirect('/teaching'); return '';
    }

    public function destroyHead(): string
    {
        $this->validateCsrf();
        $staffId  = (int) $this->input('staff_id');
        $category = (string) $this->input('category');
        Database::query(
            "DELETE FROM department_heads WHERE staff_id = ? AND category = ?",
            [$staffId, $category]
        );
        Flash::set('success', 'Department head removed.');
        $this->redirect('/teaching'); return '';
    }

    public function store(): string
    {
        $this->validateCsrf();
        $staffId   = (int) $this->input('staff_id');
        $classId   = (int) $this->input('class_id');
        $subjectId = (int) $this->input('subject_id');

        if (!$staffId || !$classId || !$subjectId) {
            Flash::set('danger', 'Teacher, class and subject are all required.');
            $this->redirect('/teaching'); return '';
        }

        try {
            Database::query(
                "INSERT IGNORE INTO teaching_assignments (staff_id, class_id, subject_id)
                 VALUES (?, ?, ?)",
                [$staffId, $classId, $subjectId]
            );
            Flash::set('success', 'Teaching assignment saved.');
        } catch (\Throwable $e) {
            Flash::set('danger', 'Could not save assignment: ' . $e->getMessage());
        }
        $this->redirect('/teaching'); return '';
    }

    public function destroy(string $id): string
    {
        $this->validateCsrf();
        Database::query("DELETE FROM teaching_assignments WHERE id = ?", [(int) $id]);
        Flash::set('success', 'Assignment removed.');
        $this->redirect('/teaching'); return '';
    }
}
