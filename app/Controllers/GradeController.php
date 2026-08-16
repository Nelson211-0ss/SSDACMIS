<?php
namespace App\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;

class GradeController extends Controller
{
    public function index(): string
    {
        if (Auth::role() === 'admin') {
            http_response_code(403);
            return $this->view('errors/403');
        }

        $isStudent = Auth::role() === 'student';
        $studentId = (int) ($this->input('student_id') ?: 0);

        $schoolId = Auth::schoolId();
        $ssf = $schoolId !== null ? ' WHERE school_id = ?' : '';
        $ssp = $schoolId !== null ? [$schoolId] : [];
        $students = Database::query("SELECT id, admission_no, first_name, last_name FROM students{$ssf} ORDER BY first_name", $ssp)->fetchAll();
        $subjects = Database::query("SELECT id, name FROM subjects{$ssf} ORDER BY name", $ssp)->fetchAll();
        $terms    = ['Term 1', 'Term 2', 'Term 3'];

        if ($isStudent) {
            $row = Database::query("SELECT id FROM students WHERE user_id = ? LIMIT 1", [Auth::user()['id']])->fetch();
            $studentId = $row['id'] ?? 0;
        }

        $grades = $studentId
            ? Database::query(
                "SELECT g.*, sub.name AS subject_name FROM grades g
                 JOIN subjects sub ON sub.id = g.subject_id" . ($schoolId !== null ? ' AND sub.school_id = ?' : '') . "
                 WHERE g.student_id = ? ORDER BY g.term, sub.name",
                $schoolId !== null ? [$schoolId, $studentId] : [$studentId]
            )->fetchAll()
            : [];

        return $this->view('grades/index', compact('students', 'subjects', 'terms', 'grades', 'studentId', 'isStudent'));
    }

    public function store(): string
    {
        if (Auth::role() === 'admin') {
            http_response_code(403);
            return $this->view('errors/403');
        }

        $this->validateCsrf();
        $studentId = (int) $this->input('student_id');
        $subjectId = (int) $this->input('subject_id');
        $term      = (string) $this->input('term');
        $score     = (float) $this->input('score');

        if (!$studentId || !$subjectId || $term === '') {
            Flash::set('danger', 'Student, subject and term are required.');
            $this->redirect('/grades?student_id=' . $studentId); return '';
        }

        if (!$this->canRecordGrade($studentId, $subjectId)) {
            Flash::set('danger', 'You are not assigned to teach that student\'s class for this subject.');
            $this->redirect('/grades?student_id=' . $studentId); return '';
        }

        Database::query(
            "INSERT INTO grades (student_id, subject_id, term, score)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE score = VALUES(score)",
            [$studentId, $subjectId, $term, $score]
        );

        ActivityLog::record('create', 'grade', null, "Recorded grade for student #{$studentId}, subject #{$subjectId}, {$term} (score {$score})");
        Flash::set('success', 'Grade recorded.');
        $this->redirect('/grades?student_id=' . $studentId);
        return '';
    }

    /**
     * True iff the current staff member may record a grade for this student
     * in this subject — mirrors MarksController::canGrade() so this older,
     * single-score entry point can't be used to bypass the same rule: a
     * teacher must have an explicit teaching assignment for the student's
     * class and subject, or head the subject's department, before they can
     * grade it. School admins and HOD accounts grade freely within their
     * own school, same as everywhere else in the app.
     */
    private function canRecordGrade(int $studentId, int $subjectId): bool
    {
        $schoolId = Auth::schoolId();

        $student = Database::query(
            "SELECT class_id, school_id FROM students WHERE id = ?",
            [$studentId]
        )->fetch();
        if (!$student) return false;
        if ($schoolId !== null && (int) $student['school_id'] !== $schoolId) return false;

        $subject = Database::query(
            "SELECT category, is_offered, school_id FROM subjects WHERE id = ?",
            [$subjectId]
        )->fetch();
        if (!$subject || (int) $subject['is_offered'] !== 1) return false;
        if ($schoolId !== null && (int) $subject['school_id'] !== $schoolId) return false;

        $role = Auth::role();
        if ($role === 'school_admin') return true;
        if ($role === 'hod') return true;

        $staff = Database::query(
            "SELECT id FROM staff WHERE user_id = ? LIMIT 1",
            [(int) (Auth::user()['id'] ?? 0)]
        )->fetch();
        if (!$staff) return false;

        $headsDepartment = Database::query(
            "SELECT 1 FROM department_heads WHERE staff_id = ? AND category = ? LIMIT 1",
            [(int) $staff['id'], $subject['category']]
        )->fetch();
        if ($headsDepartment) return true;

        $assigned = Database::query(
            "SELECT 1 FROM teaching_assignments WHERE staff_id = ? AND class_id = ? AND subject_id = ? LIMIT 1",
            [(int) $staff['id'], (int) $student['class_id'], $subjectId]
        )->fetch();
        return (bool) $assigned;
    }
}
