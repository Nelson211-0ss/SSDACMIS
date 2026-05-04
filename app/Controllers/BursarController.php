<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;
use App\Services\FeesService;

/**
 * Fees Management Module — runs the entire /bursar/* portal.
 *
 * Routes (all require role=bursar; see app/routes.php):
 *
 *   GET  /bursar                          dashboard with summary cards
 *   POST /bursar/period                   set active academic year + term
 *   GET  /bursar/structure                fees structure setup form
 *   POST /bursar/structure                save fees structure (per Form & section)
 *   GET  /bursar/students                 student fees table (search + class filter)
 *   GET  /bursar/students/{id}            single-student detail + transaction history
 *   POST /bursar/payments                 record a payment
 *   GET  /bursar/payments                 transaction history (school-wide)
 *   GET  /bursar/reports/paid             fully-paid students by class
 *   GET  /bursar/reports/balances         students with balances by class
 *   GET  /bursar/reports/print/{type}     printable view (paid|balances)
 *   GET  /bursar/reports/export.csv       CSV export
 *
 * The active period (academic_year + term) is bursar-selected and stored
 * in the session via FeesService::activePeriod(). Bills, payments, and
 * reports are scoped to that period.
 *
 * Students are *never* created here — the table is fetched live from the
 * existing `students` table via FeesService::syncAllStudents().
 */
class BursarController extends Controller
{
    /* ------------------------------------------------------------------ */
    /* Period selector (POST /bursar/period)                               */
    /* ------------------------------------------------------------------ */

    public function setPeriod(): string
    {
        $this->validateCsrf();

        $year = trim((string) $this->input('year', ''));
        $term = trim((string) $this->input('term', ''));
        FeesService::setActivePeriod($year, $term);

        $back = $this->safeReturn((string) $this->input('return', '/bursar'));
        $this->redirect($back);
        return '';
    }

    /* ------------------------------------------------------------------ */
    /* Dashboard                                                           */
    /* ------------------------------------------------------------------ */

    public function dashboard(): string
    {
        ['year' => $year, 'term' => $term] = FeesService::activePeriod();
        FeesService::syncAllStudents($year);

        $totals = Database::query(
            "SELECT
                COUNT(*)                          AS students,
                COALESCE(SUM(total_amount), 0)    AS expected,
                COALESCE(SUM(paid_amount), 0)     AS collected,
                COALESCE(SUM(GREATEST(total_amount - paid_amount, 0)), 0) AS outstanding,
                SUM(status = 'paid')              AS paid_count,
                SUM(status = 'partial')           AS partial_count,
                SUM(status = 'not_paid')          AS unpaid_count
             FROM student_fees WHERE academic_year = ? AND term = ?",
            [$year, $term]
        )->fetch() ?: [];

        $byLevel = Database::query(
            "SELECT c.level,
                    COUNT(*) AS students,
                    COALESCE(SUM(sf.total_amount), 0) AS expected,
                    COALESCE(SUM(sf.paid_amount), 0)  AS collected,
                    COALESCE(SUM(GREATEST(sf.total_amount - sf.paid_amount, 0)), 0) AS outstanding
             FROM student_fees sf
             JOIN students s ON s.id = sf.student_id
             LEFT JOIN classes c ON c.id = s.class_id
             WHERE sf.academic_year = ? AND sf.term = ?
               AND c.level IN ('Form 1','Form 2','Form 3','Form 4')
             GROUP BY c.level
             ORDER BY c.level",
            [$year, $term]
        )->fetchAll();

        // Per-term spread for the active year — handy at-a-glance card.
        $byTerm = Database::query(
            "SELECT term,
                    COUNT(*) AS students,
                    COALESCE(SUM(total_amount), 0) AS expected,
                    COALESCE(SUM(paid_amount), 0)  AS collected,
                    COALESCE(SUM(GREATEST(total_amount - paid_amount, 0)), 0) AS outstanding
             FROM student_fees
             WHERE academic_year = ?
             GROUP BY term
             ORDER BY term",
            [$year]
        )->fetchAll();

        $recentPayments = Database::query(
            "SELECT p.id, p.amount, p.payment_date, p.receipt_no, p.created_at,
                    sf.term, sf.academic_year,
                    s.first_name, s.last_name, s.admission_no, s.photo_path,
                    u.name AS bursar_name
             FROM payments p
             JOIN students s ON s.id = p.student_id
             LEFT JOIN student_fees sf ON sf.id = p.student_fee_id
             LEFT JOIN users u ON u.id = p.recorded_by
             ORDER BY p.id DESC LIMIT 8"
        )->fetchAll();

        return $this->view('bursar/dashboard', [
            'year'           => $year,
            'term'           => $term,
            'totals'         => $totals,
            'byLevel'        => $byLevel,
            'byTerm'         => $byTerm,
            'recentPayments' => $recentPayments,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Fees structure setup                                                */
    /* ------------------------------------------------------------------ */

    public function showStructure(): string
    {
        ['year' => $year, 'term' => $term] = FeesService::activePeriod();
        $map = FeesService::structureMap($year);

        $studentCounts = Database::query(
            "SELECT c.level, s.section, COUNT(*) AS n
             FROM students s LEFT JOIN classes c ON c.id = s.class_id
             WHERE c.level IN ('Form 1','Form 2','Form 3','Form 4')
             GROUP BY c.level, s.section"
        )->fetchAll();

        $counts = [];
        foreach (FeesService::FORM_LEVELS as $lvl) {
            foreach (FeesService::SECTIONS as $sec) $counts[$lvl][$sec] = 0;
        }
        foreach ($studentCounts as $r) {
            $lvl = (string) $r['level']; $sec = (string) $r['section'];
            if (isset($counts[$lvl][$sec])) $counts[$lvl][$sec] = (int) $r['n'];
        }

        return $this->view('bursar/structure', [
            'year'   => $year,
            'term'   => $term,
            'map'    => $map,
            'counts' => $counts,
            'levels' => FeesService::FORM_LEVELS,
        ]);
    }

    public function saveStructure(): string
    {
        $this->validateCsrf();
        $year = FeesService::activePeriod()['year'];

        // Expected payload: amounts[Form 1][day], amounts[Form 1][boarding], ...
        $amounts = $this->input('amounts', []);
        if (!is_array($amounts)) $amounts = [];

        // Atomicity: every cell of the fees structure plus the per-student
        // re-sync must succeed together. Without this, a failure halfway
        // through left the school with a partial structure (some forms
        // updated, others stale) and student_fees still pointing at the old
        // amounts. One transaction = one consistent state for finance.
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            foreach (FeesService::FORM_LEVELS as $lvl) {
                foreach (FeesService::SECTIONS as $sec) {
                    $raw = $amounts[$lvl][$sec] ?? '0';
                    $amt = (float) $raw;
                    if ($amt < 0) $amt = 0.0;
                    FeesService::setStructure($lvl, $sec, $year, $amt);
                }
            }
            // syncAllStudents starts and commits its own transaction; nesting
            // is handled implicitly by MySQL via savepoints when running
            // inside an outer transaction. We commit our outer one only after
            // sync returns successfully.
            $touched = FeesService::syncAllStudents($year);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Flash::set('danger', 'Could not save the fees structure. Please retry.');
            \App\Core\ErrorHandler::log($e);
            $this->redirect('/bursar/structure');
            return '';
        }

        Flash::set('success', "Fees structure saved. Auto-assigned to $touched student bill" . ($touched === 1 ? '' : 's') . ' across all 3 terms.');
        $this->redirect('/bursar/structure');
        return '';
    }

    /* ------------------------------------------------------------------ */
    /* Student fees table                                                  */
    /* ------------------------------------------------------------------ */

    public function students(): string
    {
        ['year' => $year, 'term' => $term] = FeesService::activePeriod();
        FeesService::syncAllStudents($year);

        $q        = trim((string) $this->input('q', ''));
        $level    = (string) $this->input('level', '');
        $status   = (string) $this->input('status', '');

        $where = ['sf.academic_year = ?', 'sf.term = ?'];
        $args  = [$year, $term];

        if ($q !== '') {
            $where[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.admission_no LIKE ? OR CONCAT(s.first_name,' ',s.last_name) LIKE ?)";
            $like = '%' . $q . '%';
            array_push($args, $like, $like, $like, $like);
        }
        if (in_array($level, FeesService::FORM_LEVELS, true)) {
            $where[] = 'c.level = ?'; $args[] = $level;
        }
        if (in_array($status, ['paid', 'partial', 'not_paid'], true)) {
            $where[] = 'sf.status = ?'; $args[] = $status;
        }

        $sql = "SELECT s.id, s.admission_no, s.first_name, s.last_name, s.section, s.photo_path,
                       c.level, c.name AS class_name,
                       sf.id AS sf_id, sf.total_amount, sf.paid_amount, sf.status
                FROM student_fees sf
                JOIN students s   ON s.id = sf.student_id
                LEFT JOIN classes c ON c.id = s.class_id
                WHERE " . implode(' AND ', $where) . "
                  AND c.level IN ('Form 1','Form 2','Form 3','Form 4')
                ORDER BY c.level, s.last_name, s.first_name";

        $rows = Database::query($sql, $args)->fetchAll();

        return $this->view('bursar/students', [
            'year'        => $year,
            'term'        => $term,
            'rows'        => $rows,
            'q'           => $q,
            'levelFilter' => $level,
            'statusFilter'=> $status,
            'levels'      => FeesService::FORM_LEVELS,
            'nextReceipt' => FeesService::nextReceiptNumber(),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Single student: detail + transaction history                        */
    /* ------------------------------------------------------------------ */

    public function studentDetail(string $id): string
    {
        ['year' => $year, 'term' => $term] = FeesService::activePeriod();
        $studentId = (int) $id;

        $student = Database::query(
            "SELECT s.id, s.admission_no, s.first_name, s.last_name, s.section,
                    s.gender, s.dob, s.guardian_name, s.guardian_phone, s.photo_path,
                    c.level, c.name AS class_name
             FROM students s LEFT JOIN classes c ON c.id = s.class_id
             WHERE s.id = ? LIMIT 1",
            [$studentId]
        )->fetch();

        if (!$student) {
            http_response_code(404);
            return $this->view('errors/404');
        }

        // Make sure they have all 3 term bills before we render.
        FeesService::syncAllStudents($year);
        FeesService::ensureStudentFee($studentId, $year, $term);

        // All term bills for this student in the active year — so the page
        // can show a neat per-term breakdown card.
        $termBills = Database::query(
            "SELECT id, term, total_amount, paid_amount, status
             FROM student_fees
             WHERE student_id = ? AND academic_year = ?
             ORDER BY term",
            [$studentId, $year]
        )->fetchAll();

        // The "active" bill (for the term the bursar selected) drives the
        // payment form and the headline status cards.
        $bill = ['total_amount' => 0, 'paid_amount' => 0, 'status' => 'not_paid'];
        foreach ($termBills as $tb) {
            if ((string) $tb['term'] === $term) { $bill = $tb; break; }
        }

        $payments = Database::query(
            "SELECT p.id, p.amount, p.payment_date, p.receipt_no, p.notes, p.created_at,
                    sf.term, sf.academic_year,
                    u.name AS bursar_name
             FROM payments p
             LEFT JOIN student_fees sf ON sf.id = p.student_fee_id
             LEFT JOIN users u ON u.id = p.recorded_by
             WHERE p.student_id = ?
             ORDER BY p.payment_date DESC, p.id DESC",
            [$studentId]
        )->fetchAll();

        return $this->view('bursar/student_detail', [
            'year'        => $year,
            'term'        => $term,
            'student'     => $student,
            'bill'        => $bill,
            'termBills'   => $termBills,
            'payments'    => $payments,
            'nextReceipt' => FeesService::nextReceiptNumber(),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Record payment (POST handler)                                       */
    /* ------------------------------------------------------------------ */

    public function recordPayment(): string
    {
        $this->validateCsrf();

        $studentId   = (int) $this->input('student_id', 0);
        $amount      = (float) $this->input('amount', 0);
        $paymentDate = trim((string) $this->input('payment_date', date('Y-m-d')));
        $receiptNo   = trim((string) $this->input('receipt_no', ''));
        $notes       = trim((string) $this->input('notes', ''));

        $period = FeesService::activePeriod();
        // The form may override the active period — this lets the bursar
        // record a payment for a specific term without first switching.
        $year = trim((string) $this->input('academic_year', $period['year']));
        $term = trim((string) $this->input('term',          $period['term']));
        if (!preg_match('/^\d{4}\/\d{4}$/', $year))             $year = $period['year'];
        if (!in_array($term, FeesService::TERMS, true))         $term = $period['term'];

        $back = $this->safeReturn((string) $this->input('return', '/bursar/students'));

        if ($studentId <= 0) {
            Flash::set('danger', 'Pick a student before recording a payment.');
            $this->redirect($back); return '';
        }

        $bursar = Auth::user();
        $bursarId = $bursar ? (int) $bursar['id'] : null;

        try {
            $paymentId = FeesService::recordPayment(
                $studentId, $year, $term, $amount, $paymentDate, $receiptNo, $bursarId, $notes
            );

            // Build a structured "success popup" payload that the next page
            // render will consume to show a celebratory modal with all the
            // receipt details. Cleared on read by the partial that renders it.
            $stu = Database::query(
                "SELECT first_name, last_name, admission_no, photo_path FROM students WHERE id = ? LIMIT 1",
                [$studentId]
            )->fetch() ?: ['first_name' => '', 'last_name' => '', 'admission_no' => '', 'photo_path' => null];

            $bill = Database::query(
                "SELECT total_amount, paid_amount FROM student_fees
                 WHERE student_id = ? AND academic_year = ? AND term = ? LIMIT 1",
                [$studentId, $year, $term]
            )->fetch() ?: ['total_amount' => 0, 'paid_amount' => 0];

            $balance = max(0.0, (float) $bill['total_amount'] - (float) $bill['paid_amount']);

            $_SESSION['_payment_success'] = [
                'payment_id'    => $paymentId,
                'student_id'    => $studentId,
                'student_name'  => trim($stu['first_name'] . ' ' . $stu['last_name']),
                'first_name'    => (string) ($stu['first_name'] ?? ''),
                'last_name'     => (string) ($stu['last_name'] ?? ''),
                'admission_no'  => $stu['admission_no'],
                'photo_path'    => (string) ($stu['photo_path'] ?? ''),
                'amount'        => $amount,
                'receipt_no'    => $receiptNo,
                'payment_date'  => $paymentDate,
                'academic_year' => $year,
                'term'          => $term,
                'paid_total'    => (float) $bill['paid_amount'],
                'fee_total'     => (float) $bill['total_amount'],
                'balance'       => $balance,
                'fully_paid'    => $balance <= 0.001 && (float) $bill['total_amount'] > 0,
                'bursar_name'   => $bursar['name'] ?? '',
            ];
        } catch (\DomainException $e) {
            Flash::set('danger', $e->getMessage());
        } catch (\Throwable $e) {
            Flash::set('danger', 'Could not record payment. Please try again.');
        }

        $this->redirect($back);
        return '';
    }

    /* ------------------------------------------------------------------ */
    /* Single-payment printable receipt                                    */
    /* ------------------------------------------------------------------ */

    public function receipt(string $id): string
    {
        $row = Database::query(
            "SELECT p.id, p.amount, p.payment_date, p.receipt_no, p.notes, p.created_at,
                    p.student_id,
                    s.admission_no, s.first_name, s.last_name, s.section, s.photo_path,
                    c.level, c.name AS class_name,
                    sf.total_amount, sf.paid_amount, sf.status,
                    sf.academic_year, sf.term,
                    u.name AS bursar_name
             FROM payments p
             JOIN students s     ON s.id = p.student_id
             LEFT JOIN classes c ON c.id = s.class_id
             LEFT JOIN student_fees sf ON sf.id = p.student_fee_id
             LEFT JOIN users u   ON u.id = p.recorded_by
             WHERE p.id = ? LIMIT 1",
            [(int) $id]
        )->fetch();
        if (!$row) {
            http_response_code(404);
            return $this->view('errors/404');
        }
        return $this->view('bursar/receipt_print', ['p' => $row]);
    }

    /* ------------------------------------------------------------------ */
    /* Payments log (school-wide, optionally scoped to active period)      */
    /* ------------------------------------------------------------------ */

    public function payments(): string
    {
        ['year' => $year, 'term' => $term] = FeesService::activePeriod();

        // Default scope: just the active period. The "All periods" toggle
        // (?scope=all) lets the bursar audit every payment ever made.
        $scope = (string) $this->input('scope', 'period');
        $where = '';
        $args  = [];
        if ($scope !== 'all') {
            $where = "WHERE sf.academic_year = ? AND sf.term = ?";
            $args  = [$year, $term];
        }

        $rows = Database::query(
            "SELECT p.id, p.amount, p.payment_date, p.receipt_no, p.notes, p.created_at,
                    s.id AS student_id, s.admission_no, s.first_name, s.last_name, s.photo_path,
                    c.level,
                    sf.term, sf.academic_year,
                    u.name AS bursar_name
             FROM payments p
             JOIN students s ON s.id = p.student_id
             LEFT JOIN classes c ON c.id = s.class_id
             LEFT JOIN student_fees sf ON sf.id = p.student_fee_id
             LEFT JOIN users u ON u.id = p.recorded_by
             $where
             ORDER BY p.payment_date DESC, p.id DESC
             LIMIT 500",
            $args
        )->fetchAll();

        return $this->view('bursar/payments', [
            'year'  => $year,
            'term'  => $term,
            'scope' => $scope,
            'rows'  => $rows,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Reports                                                             */
    /* ------------------------------------------------------------------ */

    public function reportPaid(): string
    {
        ['year' => $year, 'term' => $term] = FeesService::activePeriod();
        FeesService::syncAllStudents($year);
        $level = $this->normalizeLevel($this->input('level', ''));

        $rows = $this->fetchReportRows($year, $term, 'paid', $level);

        return $this->view('bursar/report_paid', [
            'year'   => $year,
            'term'   => $term,
            'rows'   => $rows,
            'level'  => $level,
            'levels' => FeesService::FORM_LEVELS,
        ]);
    }

    public function reportBalances(): string
    {
        ['year' => $year, 'term' => $term] = FeesService::activePeriod();
        FeesService::syncAllStudents($year);
        $level = $this->normalizeLevel($this->input('level', ''));

        $rows = $this->fetchReportRows($year, $term, 'balances', $level);

        return $this->view('bursar/report_balances', [
            'year'   => $year,
            'term'   => $term,
            'rows'   => $rows,
            'level'  => $level,
            'levels' => FeesService::FORM_LEVELS,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Examination permits                                                  */
    /*                                                                       */
    /* The permit is auto-issued for the active period to any student whose */
    /* term balance is zero — i.e. status = 'paid' on student_fees. The     */
    /* bursar lands on the list view, can filter by class, then prints      */
    /* either an individual permit or the entire batch in one job.          */
    /* ------------------------------------------------------------------ */

    /** Bursar list view — every fully-paid student in the active period. */
    public function examPermits(): string
    {
        ['year' => $year, 'term' => $term] = FeesService::activePeriod();
        FeesService::syncAllStudents($year);
        $level = $this->normalizeLevel($this->input('level', ''));
        $rows  = $this->fetchExamPermitRows($year, $term, $level);

        return $this->view('bursar/exam_permits', [
            'year'   => $year,
            'term'   => $term,
            'rows'   => $rows,
            'level'  => $level,
            'levels' => FeesService::FORM_LEVELS,
        ]);
    }

    /**
     * Printable permit batch — one student per page. Optional ?id= renders
     * a single permit; otherwise every fully-paid student in the active
     * period (filtered by ?level= when supplied) is included.
     */
    public function examPermitsPrint(): string
    {
        ['year' => $year, 'term' => $term] = FeesService::activePeriod();
        FeesService::syncAllStudents($year);

        $only  = (int) $this->input('id', 0);
        $level = $this->normalizeLevel($this->input('level', ''));
        $rows  = $this->fetchExamPermitRows($year, $term, $level, $only > 0 ? $only : null);

        return $this->view('bursar/exam_permit_print', [
            'year'  => $year,
            'term'  => $term,
            'rows'  => $rows,
            'level' => $level,
        ]);
    }

    /**
     * Eligible students for an exam permit — only rows where the term's
     * balance is fully cleared. Pass $studentId to fetch a single row
     * (still gated by status='paid' so we never issue a permit for a
     * student who hasn't cleared).
     */
    private function fetchExamPermitRows(string $year, string $term, string $level, ?int $studentId = null): array
    {
        $where = [
            'sf.academic_year = ?',
            'sf.term = ?',
            "sf.status = 'paid'",
            "c.level IN ('Form 1','Form 2','Form 3','Form 4')",
        ];
        $args = [$year, $term];
        if ($level !== '') {
            $where[] = 'c.level = ?';
            $args[]  = $level;
        }
        if ($studentId !== null) {
            $where[] = 's.id = ?';
            $args[]  = $studentId;
        }

        $sql = "SELECT s.id, s.admission_no, s.first_name, s.last_name,
                       s.section, s.stream, s.photo_path,
                       c.level, c.name AS class_name,
                       sf.total_amount, sf.paid_amount, sf.status
                FROM student_fees sf
                JOIN students s   ON s.id = sf.student_id
                LEFT JOIN classes c ON c.id = s.class_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY c.level, s.last_name, s.first_name";

        return Database::query($sql, $args)->fetchAll();
    }

    /**
     * Print-friendly view for either report. type ∈ {paid, balances}.
     */
    public function reportPrint(string $type): string
    {
        if (!in_array($type, ['paid', 'balances'], true)) {
            http_response_code(404);
            return $this->view('errors/404');
        }
        ['year' => $year, 'term' => $term] = FeesService::activePeriod();
        FeesService::syncAllStudents($year);
        $level = $this->normalizeLevel($this->input('level', ''));
        $rows  = $this->fetchReportRows($year, $term, $type, $level);

        return $this->view('bursar/report_print', [
            'year'  => $year,
            'term'  => $term,
            'rows'  => $rows,
            'level' => $level,
            'type'  => $type,
        ]);
    }

    /**
     * CSV export. type ∈ {paid, balances, all}.
     */
    public function exportCsv(): string
    {
        $type  = (string) $this->input('type', 'all');
        if (!in_array($type, ['paid', 'balances', 'all'], true)) $type = 'all';

        ['year' => $year, 'term' => $term] = FeesService::activePeriod();
        FeesService::syncAllStudents($year);
        $level = $this->normalizeLevel($this->input('level', ''));
        $rows  = $this->fetchReportRows($year, $term, $type, $level);

        // Stream CSV directly; no view template needed.
        $termSlug = strtolower(str_replace(' ', '', $term));
        $filename = 'fees-' . $type
                  . '-' . $termSlug
                  . ($level ? '-' . str_replace(' ', '', strtolower($level)) : '')
                  . '-' . str_replace('/', '-', $year) . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $out = fopen('php://output', 'w');
        // BOM so Excel opens UTF-8 names correctly.
        fwrite($out, "\xEF\xBB\xBF");
        // Pass explicit $escape ('') to be forward-compatible with PHP 8.5+
        // (the legacy backslash escape is deprecated and removed in 9.0).
        fputcsv($out, ['Academic Year', 'Term', 'Admission No', 'Student Name', 'Class', 'Section', 'Term Fees', 'Paid', 'Balance', 'Status'], ',', '"', '');
        foreach ($rows as $r) {
            $balance = max(0.0, (float) $r['total_amount'] - (float) $r['paid_amount']);
            fputcsv($out, [
                $year,
                $term,
                $r['admission_no'],
                trim($r['first_name'] . ' ' . $r['last_name']),
                $r['class_name'] ?? ($r['level'] ?? '—'),
                ucfirst((string) $r['section']),
                number_format((float) $r['total_amount'], 2, '.', ''),
                number_format((float) $r['paid_amount'], 2, '.', ''),
                number_format($balance, 2, '.', ''),
                FeesService::statusLabel((string) $r['status']),
            ], ',', '"', '');
        }
        fclose($out);
        return '';
    }

    /* ------------------------------------------------------------------ */
    /* Internals                                                           */
    /* ------------------------------------------------------------------ */

    private function normalizeLevel($raw): string
    {
        $raw = (string) $raw;
        return in_array($raw, FeesService::FORM_LEVELS, true) ? $raw : '';
    }

    /**
     * Shared query for the three report views (paid, balances, all),
     * scoped to a single (year, term).
     */
    private function fetchReportRows(string $year, string $term, string $type, string $level): array
    {
        $where = [
            'sf.academic_year = ?',
            'sf.term = ?',
            "c.level IN ('Form 1','Form 2','Form 3','Form 4')",
        ];
        $args  = [$year, $term];
        if ($level !== '') { $where[] = 'c.level = ?'; $args[] = $level; }
        if ($type === 'paid') {
            $where[] = "sf.status = 'paid'";
        } elseif ($type === 'balances') {
            $where[] = "sf.status IN ('partial','not_paid')";
        }

        $sql = "SELECT s.id, s.admission_no, s.first_name, s.last_name, s.section, s.photo_path,
                       c.level, c.name AS class_name,
                       sf.total_amount, sf.paid_amount, sf.status
                FROM student_fees sf
                JOIN students s   ON s.id = sf.student_id
                LEFT JOIN classes c ON c.id = s.class_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY c.level, s.last_name, s.first_name";

        return Database::query($sql, $args)->fetchAll();
    }

    /**
     * Sanitise a "return" path supplied by a form so we only ever bounce
     * back to internal /bursar/* URLs.
     */
    private function safeReturn(string $back): string
    {
        $back = $back !== '' ? $back : '/bursar';
        // Defensive: if the form sent the full REQUEST_URI (which already
        // contains /SSDACMIS/public), strip that base — Controller::redirect()
        // re-prepends it and a doubled prefix would 404.
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
        if ($scriptDir !== '' && str_starts_with($back, $scriptDir)) {
            $back = substr($back, strlen($scriptDir)) ?: '/bursar';
        }
        if (!str_starts_with($back, '/bursar')) {
            $back = '/bursar';
        }
        return $back;
    }
}
