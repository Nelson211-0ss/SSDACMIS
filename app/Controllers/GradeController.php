<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;

class GradeController extends Controller
{
    public function index(): string
    {
        $isStudent = Auth::role() === 'student';
        $studentId = (int) ($this->input('student_id') ?: 0);

        $students = Database::query("SELECT id, admission_no, first_name, last_name FROM students ORDER BY first_name")->fetchAll();
        $subjects = Database::query("SELECT id, name FROM subjects ORDER BY name")->fetchAll();
        $terms    = ['Term 1', 'Term 2', 'Term 3'];

        if ($isStudent) {
            $row = Database::query("SELECT id FROM students WHERE user_id = ? LIMIT 1", [Auth::user()['id']])->fetch();
            $studentId = $row['id'] ?? 0;
        }

        $grades = $studentId
            ? Database::query(
                "SELECT g.*, sub.name AS subject_name FROM grades g
                 JOIN subjects sub ON sub.id = g.subject_id
                 WHERE g.student_id = ? ORDER BY g.term, sub.name",
                [$studentId]
            )->fetchAll()
            : [];

        return $this->view('grades/index', compact('students', 'subjects', 'terms', 'grades', 'studentId', 'isStudent'));
    }

    public function store(): string
    {
        $this->validateCsrf();
        $studentId = (int) $this->input('student_id');
        $subjectId = (int) $this->input('subject_id');
        $term      = (string) $this->input('term');
        $score     = (float) $this->input('score');

        if (!$studentId || !$subjectId || $term === '') {
            Flash::set('danger', 'Student, subject and term are required.');
            $this->redirect('/grades?student_id=' . $studentId); return '';
        }

        Database::query(
            "INSERT INTO grades (student_id, subject_id, term, score)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE score = VALUES(score)",
            [$studentId, $subjectId, $term, $score]
        );

        Flash::set('success', 'Grade recorded.');
        $this->redirect('/grades?student_id=' . $studentId);
        return '';
    }
}
