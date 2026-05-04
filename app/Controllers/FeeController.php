<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\FeesService;

/**
 * Read-only "My Fees" page for students. The full Fees Management Module
 * (structure setup, payment recording, reports) lives in BursarController
 * under /bursar/* — staff/admin/HOD do not have access to it from here.
 *
 * If a non-student lands on /fees, we point them at the right place:
 *   - bursars  -> /bursar (the proper portal)
 *   - admins   -> /bursars (the bursar accounts admin page)
 *   - everyone else gets a 403.
 */
class FeeController extends Controller
{
    public function index(): string
    {
        $role = Auth::role();

        if ($role === 'bursar') { $this->redirect('/bursar'); return ''; }
        if ($role === 'admin')  { $this->redirect('/bursars'); return ''; }
        if ($role !== 'student') {
            http_response_code(403);
            return $this->view('errors/403');
        }

        $year = FeesService::currentYear();
        $user = Auth::user();
        $student = Database::query(
            "SELECT s.id, s.admission_no, s.first_name, s.last_name, s.section,
                    c.level, c.name AS class_name
             FROM students s LEFT JOIN classes c ON c.id = s.class_id
             WHERE s.user_id = ? LIMIT 1",
            [(int) $user['id']]
        )->fetch();

        $bill     = null;
        $payments = [];
        if ($student) {
            FeesService::ensureStudentFee((int) $student['id'], $year);
            $bill = Database::query(
                "SELECT total_amount, paid_amount, status
                 FROM student_fees WHERE student_id = ? AND academic_year = ? LIMIT 1",
                [(int) $student['id'], $year]
            )->fetch() ?: null;

            $payments = Database::query(
                "SELECT p.amount, p.payment_date, p.receipt_no, p.notes,
                        u.name AS bursar_name
                 FROM payments p
                 LEFT JOIN users u ON u.id = p.recorded_by
                 WHERE p.student_id = ?
                 ORDER BY p.payment_date DESC, p.id DESC",
                [(int) $student['id']]
            )->fetchAll();
        }

        return $this->view('fees/index', [
            'year'     => $year,
            'student'  => $student,
            'bill'     => $bill,
            'payments' => $payments,
        ]);
    }
}
