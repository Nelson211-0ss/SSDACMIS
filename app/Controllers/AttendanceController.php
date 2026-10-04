<?php
namespace App\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;

class AttendanceController extends Controller
{
    public function index(): string
    {
        $schoolId = Auth::schoolId();
        $ssf = $schoolId !== null ? ' WHERE school_id = ?' : '';
        $ssp = $schoolId !== null ? [$schoolId] : [];
        $classes  = Database::query("SELECT id, name FROM classes{$ssf} ORDER BY name", $ssp)->fetchAll();
        $classId  = (int) ($this->input('class_id') ?: ($classes[0]['id'] ?? 0));
        $date     = $this->input('date') ?: date('Y-m-d');
        if ($classId && $schoolId !== null && !in_array($classId, array_map('intval', array_column($classes, 'id')), true)) {
            $classId = 0;
        }

        if ($classId) {
            $stuParams = [$classId];
            $stuFilter = '';
            if ($schoolId !== null) { $stuFilter = ' AND school_id = ?'; $stuParams[] = $schoolId; }
            $students = Database::query(
                "SELECT id, admission_no, first_name, last_name FROM students WHERE class_id = ?{$stuFilter} ORDER BY first_name",
                $stuParams
            )->fetchAll();
        } else {
            $students = [];
        }

        $existing = [];
        if ($classId) {
            $rows = Database::query(
                "SELECT student_id, status FROM attendance WHERE class_id = ? AND date = ?",
                [$classId, $date]
            )->fetchAll();
            foreach ($rows as $r) $existing[(int)$r['student_id']] = $r['status'];
        }

        return $this->view('attendance/index', compact('classes', 'classId', 'date', 'students', 'existing'));
    }

    public function store(): string
    {
        $this->validateCsrf();
        $classId = (int) $this->input('class_id');
        $date    = $this->input('date');
        $marks   = $this->input('status', []);

        if (!$classId || !$date) { Flash::set('danger', 'Class and date are required.'); $this->redirect('/attendance'); return ''; }

        $schoolId = Auth::schoolId();
        $class = Database::query(
            "SELECT school_id FROM classes WHERE id = ?" . ($schoolId !== null ? ' AND school_id = ?' : ''),
            $schoolId !== null ? [$classId, $schoolId] : [$classId]
        )->fetch();
        if (!$class) { Flash::set('danger', 'That class is not in your school.'); $this->redirect('/attendance'); return ''; }
        // Only students of this class (and therefore this school) may be marked.
        $validIds = array_flip(array_map('intval', Database::query(
            "SELECT id FROM students WHERE class_id = ? AND school_id = ?", [$classId, (int) $class['school_id']]
        )->fetchAll(\PDO::FETCH_COLUMN)));

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM attendance WHERE class_id = ? AND date = ?")->execute([$classId, $date]);
            $stmt = $pdo->prepare("INSERT INTO attendance (class_id, student_id, date, status) VALUES (?, ?, ?, ?)");
            foreach ((array)$marks as $studentId => $status) {
                if (!in_array($status, ['present','absent','late'], true)) continue;
                if (!isset($validIds[(int) $studentId])) continue;
                $stmt->execute([$classId, (int)$studentId, $date, $status]);
            }
            $pdo->commit();
            ActivityLog::record('create', 'attendance', null, "Recorded attendance for class #{$classId} on {$date}");
            Flash::set('success', 'Attendance saved.');
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Flash::set('danger', 'Could not save: ' . $e->getMessage());
        }

        $this->redirect('/attendance?class_id=' . $classId . '&date=' . urlencode($date));
        return '';
    }
}
