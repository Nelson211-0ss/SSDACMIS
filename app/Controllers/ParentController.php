<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\FeesService;

/**
 * Parent portal — read-only. A parent sees only the student(s) linked to
 * their account in `parent_students` (managed by admin/school_admin from
 * /parents, see ParentAccountController).
 *
 *   GET /parent                     -> dashboard: one card per linked child
 *   GET /parent/fees/{id}           -> that child's fee bill + payment history
 *   GET /parent/attendance/{id}     -> that child's attendance history
 *
 * Report cards reuse ReportController@student directly (see routes.php) —
 * canSeeStudent() there already checks parent_students.
 */
class ParentController extends Controller
{
    /** Every child linked to the signed-in parent. */
    private function children(): array
    {
        $u = Auth::user();
        return Database::query(
            "SELECT s.id, s.admission_no, s.first_name, s.last_name, s.gender,
                    s.photo_path, c.id AS class_id, c.name AS class_name, c.level
             FROM parent_students ps
             JOIN students s ON s.id = ps.student_id
             LEFT JOIN classes c ON c.id = s.class_id
             WHERE ps.parent_user_id = ?
             ORDER BY s.first_name, s.last_name",
            [(int) $u['id']]
        )->fetchAll();
    }

    /** True when the given student is linked to the signed-in parent. */
    private function ownsChild(int $studentId): bool
    {
        $u = Auth::user();
        $r = Database::query(
            "SELECT 1 FROM parent_students WHERE parent_user_id = ? AND student_id = ? LIMIT 1",
            [(int) $u['id'], $studentId]
        )->fetch();
        return (bool) $r;
    }

    public function dashboard(): string
    {
        $children = $this->children();

        $year = FeesService::currentYear();
        $feeStatusByStudent = [];
        if ($children) {
            $ids = array_map(static fn ($c) => (int) $c['id'], $children);
            $ph  = implode(',', array_fill(0, count($ids), '?'));
            // One status per student for the current year: worst-of-terms
            // (not_paid > partial > paid) so a single balance anywhere in
            // the year shows on the card.
            $rows = Database::query(
                "SELECT student_id, status FROM student_fees
                 WHERE academic_year = ? AND student_id IN ($ph)",
                array_merge([$year], $ids)
            )->fetchAll();
            $rank = ['not_paid' => 2, 'partial' => 1, 'paid' => 0];
            foreach ($rows as $r) {
                $sid = (int) $r['student_id'];
                $cur = $feeStatusByStudent[$sid] ?? null;
                if ($cur === null || ($rank[$r['status']] ?? 0) > ($rank[$cur] ?? 0)) {
                    $feeStatusByStudent[$sid] = (string) $r['status'];
                }
            }
        }

        return $this->view('parent/dashboard', [
            'children'           => $children,
            'feeStatusByStudent' => $feeStatusByStudent,
            'year'                => $year,
        ]);
    }

    public function fees(string $id): string
    {
        $studentId = (int) $id;
        if (!$this->ownsChild($studentId)) {
            http_response_code(403);
            return $this->view('errors/403');
        }

        $student = Database::query(
            "SELECT s.id, s.admission_no, s.first_name, s.last_name, s.section,
                    c.level, c.name AS class_name
             FROM students s LEFT JOIN classes c ON c.id = s.class_id
             WHERE s.id = ? LIMIT 1",
            [$studentId]
        )->fetch();

        $year = (string) ($this->input('year') ?: FeesService::currentYear());

        $bills = [];
        foreach (FeesService::TERMS as $term) {
            FeesService::ensureStudentFee($studentId, $year, $term);
        }
        $rows = Database::query(
            "SELECT term, total_amount, paid_amount, status
             FROM student_fees WHERE student_id = ? AND academic_year = ?
             ORDER BY FIELD(term, 'Term 1','Term 2','Term 3')",
            [$studentId, $year]
        )->fetchAll();
        foreach ($rows as $r) {
            $bills[$r['term']] = $r;
        }

        $payments = Database::query(
            "SELECT p.amount, p.payment_date, p.receipt_no, p.notes,
                    u.name AS bursar_name
             FROM payments p
             LEFT JOIN users u ON u.id = p.recorded_by
             WHERE p.student_id = ?
             ORDER BY p.payment_date DESC, p.id DESC",
            [$studentId]
        )->fetchAll();

        return $this->view('parent/fees', [
            'student'  => $student,
            'year'     => $year,
            'bills'    => $bills,
            'payments' => $payments,
        ]);
    }

    public function attendance(string $id): string
    {
        $studentId = (int) $id;
        if (!$this->ownsChild($studentId)) {
            http_response_code(403);
            return $this->view('errors/403');
        }

        $student = Database::query(
            "SELECT s.id, s.admission_no, s.first_name, s.last_name,
                    c.level, c.name AS class_name
             FROM students s LEFT JOIN classes c ON c.id = s.class_id
             WHERE s.id = ? LIMIT 1",
            [$studentId]
        )->fetch();

        $from = (string) ($this->input('from') ?: date('Y-m-01'));
        $to   = (string) ($this->input('to')   ?: date('Y-m-t'));

        $rows = Database::query(
            "SELECT date, status FROM attendance
             WHERE student_id = ? AND date BETWEEN ? AND ?
             ORDER BY date DESC",
            [$studentId, $from, $to]
        )->fetchAll();

        $tally = ['present' => 0, 'absent' => 0, 'late' => 0];
        foreach ($rows as $r) {
            $s = (string) $r['status'];
            if (isset($tally[$s])) $tally[$s]++;
        }

        return $this->view('parent/attendance', [
            'student' => $student,
            'from'    => $from,
            'to'      => $to,
            'records' => $rows,
            'tally'   => $tally,
        ]);
    }
}
