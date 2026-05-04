<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;

class AttendanceController extends Controller
{
    public function index(): string
    {
        $classes  = Database::query("SELECT id, name FROM classes ORDER BY name")->fetchAll();
        $classId  = (int) ($this->input('class_id') ?: ($classes[0]['id'] ?? 0));
        $date     = $this->input('date') ?: date('Y-m-d');

        $students = $classId
            ? Database::query("SELECT id, admission_no, first_name, last_name FROM students WHERE class_id = ? ORDER BY first_name", [$classId])->fetchAll()
            : [];

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

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM attendance WHERE class_id = ? AND date = ?")->execute([$classId, $date]);
            $stmt = $pdo->prepare("INSERT INTO attendance (class_id, student_id, date, status) VALUES (?, ?, ?, ?)");
            foreach ((array)$marks as $studentId => $status) {
                if (!in_array($status, ['present','absent','late'], true)) continue;
                $stmt->execute([$classId, (int)$studentId, $date, $status]);
            }
            $pdo->commit();
            Flash::set('success', 'Attendance saved.');
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Flash::set('danger', 'Could not save: ' . $e->getMessage());
        }

        $this->redirect('/attendance?class_id=' . $classId . '&date=' . urlencode($date));
        return '';
    }
}
