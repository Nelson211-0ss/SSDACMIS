<?php
namespace App\Services;

use App\Core\Database;

/**
 * Domain logic for the Fees Management module.
 *
 * The module bills per (academic_year, term). The fees structure is set as
 * a yearly amount per (Form, section) and the system splits it equally
 * across the three terms — so each student has one student_fees row per
 * term with total_amount = round(yearly / 3, 2).
 *
 * Concerns kept here so controllers stay slim and views can trust the
 * data they receive:
 *
 *   1. Academic-year + term helpers (Form 1–4 only). The bursar picks the
 *      active period; FeesService::activePeriod() reads it from session.
 *   2. Auto-assignment of student fees from fees_structure (run on every
 *      bursar page render) — creates missing per-term rows for every
 *      Form 1–4 student so admissions / class / section transfers are
 *      picked up automatically.
 *   3. Payment recording with overpayment guard (per term) + cached status
 *      update on student_fees so reports don't recompute totals per row.
 */
class FeesService
{
    /** Forms that the bursar can build a fees structure for. */
    public const FORM_LEVELS = ['Form 1', 'Form 2', 'Form 3', 'Form 4'];

    /** Sections (matches students.section + fees_structure.section enums). */
    public const SECTIONS = ['day', 'boarding'];

    /** Terms that bills/payments are scoped to. */
    public const TERMS = ['Term 1', 'Term 2', 'Term 3'];

    /** Session slot used by the period selector. */
    private const PERIOD_SESSION_KEY = 'bursar_period';

    /**
     * Current academic year string, e.g. "2025/2026". Matches the convention
     * already used by grades.academic_year (September is the cutover month).
     */
    public static function currentYear(): string
    {
        return (date('n') >= 9)
            ? date('Y') . '/' . (date('Y') + 1)
            : (date('Y') - 1) . '/' . date('Y');
    }

    /**
     * Default term, derived from the calendar month. Used only as a starting
     * value for the period selector — the bursar can pick any term.
     *
     *   Sep–Dec  -> Term 1
     *   Jan–Apr  -> Term 2
     *   May–Aug  -> Term 3
     */
    public static function currentTerm(): string
    {
        $m = (int) date('n');
        if ($m >= 9)            return 'Term 1';
        if ($m >= 1 && $m <= 4) return 'Term 2';
        return 'Term 3';
    }

    /**
     * Returns ['year' => ..., 'term' => ...] for the bursar's currently
     * selected period, falling back to the calendar defaults the first
     * time a bursar signs in.
     *
     * @return array{year:string, term:string}
     */
    public static function activePeriod(): array
    {
        $stored = $_SESSION[self::PERIOD_SESSION_KEY] ?? null;
        $year   = is_array($stored) ? (string) ($stored['year'] ?? '') : '';
        $term   = is_array($stored) ? (string) ($stored['term'] ?? '') : '';

        if ($year === '' || !preg_match('/^\d{4}\/\d{4}$/', $year)) {
            $year = self::currentYear();
        }
        if (!in_array($term, self::TERMS, true)) {
            $term = self::currentTerm();
        }
        return ['year' => $year, 'term' => $term];
    }

    /**
     * Persist the bursar's chosen period. Validates inputs; silently
     * ignores anything that doesn't match the supported shape.
     */
    public static function setActivePeriod(string $year, string $term): void
    {
        if (!preg_match('/^\d{4}\/\d{4}$/', $year))   return;
        if (!in_array($term, self::TERMS, true))      return;
        $_SESSION[self::PERIOD_SESSION_KEY] = [
            'year' => $year,
            'term' => $term,
        ];
    }

    /**
     * List of academic years the bursar has data for, plus the calendar
     * default and the active selection — so the dropdown always offers the
     * obvious choices even before any structure has been saved.
     *
     * @return string[] sorted desc (most recent first)
     */
    public static function knownYears(): array
    {
        $rows = Database::query(
            "SELECT academic_year FROM fees_structure
             UNION
             SELECT academic_year FROM student_fees"
        )->fetchAll();

        $years = [];
        foreach ($rows as $r) $years[(string) $r['academic_year']] = true;
        $years[self::currentYear()] = true;
        $years[self::activePeriod()['year']] = true;

        $list = array_keys($years);
        // Newer years first.
        usort($list, fn ($a, $b) => strcmp($b, $a));
        return $list;
    }

    /* ------------------------------------------------------------------ */
    /* Fees structure                                                      */
    /* ------------------------------------------------------------------ */

    /**
     * @return array<string,array<string,float>>  $structure[level][section] = yearly amount
     */
    public static function structureMap(string $year): array
    {
        $rows = Database::query(
            "SELECT level, section, amount FROM fees_structure WHERE academic_year = ?",
            [$year]
        )->fetchAll();

        $map = [];
        foreach (self::FORM_LEVELS as $lvl) {
            foreach (self::SECTIONS as $sec) {
                $map[$lvl][$sec] = 0.0;
            }
        }
        foreach ($rows as $r) {
            $lvl = (string) $r['level'];
            $sec = (string) $r['section'];
            if (isset($map[$lvl][$sec])) {
                $map[$lvl][$sec] = (float) $r['amount'];
            }
        }
        return $map;
    }

    /**
     * Upsert one fees_structure cell. $amount is the YEARLY total; the
     * per-term bill is automatically yearly / 3.
     */
    public static function setStructure(string $level, string $section, string $year, float $amount): void
    {
        if (!in_array($level, self::FORM_LEVELS, true)) return;
        if (!in_array($section, self::SECTIONS, true)) return;
        if ($amount < 0) $amount = 0.0;

        Database::query(
            "INSERT INTO fees_structure (level, section, academic_year, amount)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE amount = VALUES(amount)",
            [$level, $section, $year, $amount]
        );
    }

    /**
     * Yearly amount split equally across the three terms (rounded to cents).
     */
    public static function termAmount(float $yearAmount): float
    {
        return round(max(0.0, $yearAmount) / 3, 2);
    }

    /* ------------------------------------------------------------------ */
    /* Auto-assignment: students -> student_fees (per term)                */
    /* ------------------------------------------------------------------ */

    /**
     * For the given academic year, make sure every Form 1–4 student has a
     * student_fees row PER TERM with total_amount = yearly / 3. Existing
     * paid_amount is preserved; status is recomputed from totals.
     *
     * Idempotent — safe to call on every page render.
     *
     * Performance: previously this fired one SELECT per (student × term) plus
     * one INSERT/UPDATE per change, which scaled as O(students × 3) round-
     * trips and dominated bursar page loads. The new implementation does:
     *
     *   - 1 SELECT to fetch every existing student_fees row for the year
     *     (already covered by the (student_id, academic_year, term) index)
     *   - 1 INSERT per missing (student, term) pair
     *   - 1 UPDATE per row whose total_amount actually changed
     *
     * In practice that turns the hot path on dashboard/students/payments
     * pages from O(n) round-trips into O(1) for steady-state schools.
     *
     * Nested-transaction safe: if the caller already opened a transaction
     * (e.g. saveStructure wraps the structure+sync as a single atomic unit)
     * we don't try to open a second one, since PDO/MySQL doesn't support
     * true nesting.
     *
     * Returns the number of student_fees rows touched (created or updated).
     */
    public static function syncAllStudents(string $year): int
    {
        $structure = self::structureMap($year);

        $students = Database::query(
            "SELECT s.id, s.section, c.level
             FROM students s
             LEFT JOIN classes c ON c.id = s.class_id
             WHERE c.level IN ('Form 1','Form 2','Form 3','Form 4')"
        )->fetchAll();

        if (empty($students)) return 0;

        // Single bulk read: every existing fee row for this year, indexed in
        // memory by "$studentId|$term" so the per-student loop is O(1).
        $existingRows = Database::query(
            "SELECT id, student_id, term, total_amount, paid_amount
             FROM student_fees
             WHERE academic_year = ?",
            [$year]
        )->fetchAll();

        $existing = [];
        foreach ($existingRows as $r) {
            $existing[$r['student_id'] . '|' . $r['term']] = $r;
        }

        $pdo = Database::connection();
        $owner = false; // whether *we* opened the transaction
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $owner = true;
        }

        $touched = 0;
        try {
            $insert = $pdo->prepare(
                "INSERT INTO student_fees (student_id, academic_year, term, total_amount, paid_amount, status)
                 VALUES (?, ?, ?, ?, 0, ?)"
            );
            $update = $pdo->prepare(
                "UPDATE student_fees SET total_amount = ?, status = ? WHERE id = ?"
            );

            foreach ($students as $s) {
                $sid     = (int) $s['id'];
                $level   = (string) $s['level'];
                $section = (string) $s['section'];
                $yearly  = (float) ($structure[$level][$section] ?? 0);
                $perTerm = self::termAmount($yearly);

                foreach (self::TERMS as $term) {
                    $key = $sid . '|' . $term;
                    $row = $existing[$key] ?? null;

                    if (!$row) {
                        $insert->execute([$sid, $year, $term, $perTerm, self::statusFor($perTerm, 0.0)]);
                        $touched++;
                        continue;
                    }

                    $paid = (float) $row['paid_amount'];
                    $newTotal = $perTerm;
                    // Defensive: keep total ≥ paid so legacy rows that already
                    // hold more than the new per-term amount don't end up with
                    // a negative balance / wrong status.
                    if ($newTotal < $paid) $newTotal = $paid;

                    if (abs($newTotal - (float) $row['total_amount']) > 0.001) {
                        $update->execute([$newTotal, self::statusFor($newTotal, $paid), (int) $row['id']]);
                        $touched++;
                    }
                }
            }

            if ($owner) $pdo->commit();
        } catch (\Throwable $e) {
            if ($owner && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        return $touched;
    }

    /**
     * Returns the student_fees row id for ($studentId, $year, $term),
     * creating it (with the structure-derived per-term total) if missing.
     * Used right before recording a payment so we never insert an orphan.
     */
    public static function ensureStudentFee(int $studentId, string $year, string $term): int
    {
        if (!in_array($term, self::TERMS, true)) {
            throw new \DomainException('Invalid term selection.');
        }

        $row = Database::query(
            "SELECT id FROM student_fees WHERE student_id = ? AND academic_year = ? AND term = ? LIMIT 1",
            [$studentId, $year, $term]
        )->fetch();
        if ($row) return (int) $row['id'];

        // Look up the student's class level + section so we know which
        // structure cell applies.
        $stu = Database::query(
            "SELECT s.section, c.level
             FROM students s LEFT JOIN classes c ON c.id = s.class_id
             WHERE s.id = ? LIMIT 1",
            [$studentId]
        )->fetch();
        if (!$stu) {
            throw new \RuntimeException('Student not found.');
        }

        $structure = self::structureMap($year);
        $yearly    = (float) ($structure[(string) $stu['level']][(string) $stu['section']] ?? 0);
        $perTerm   = self::termAmount($yearly);

        Database::query(
            "INSERT INTO student_fees (student_id, academic_year, term, total_amount, paid_amount, status)
             VALUES (?, ?, ?, ?, 0, ?)",
            [$studentId, $year, $term, $perTerm, self::statusFor($perTerm, 0.0)]
        );
        return (int) Database::connection()->lastInsertId();
    }

    /* ------------------------------------------------------------------ */
    /* Payment recording                                                   */
    /* ------------------------------------------------------------------ */

    /**
     * Record a single payment against a student's bill for the given
     * (year, term). Throws \DomainException with a user-facing message on
     * validation failure (controller catches and flashes). Returns the new
     * payment id.
     */
    public static function recordPayment(
        int $studentId,
        string $year,
        string $term,
        float $amount,
        string $paymentDate,
        string $receiptNo,
        ?int $bursarUserId,
        string $notes = ''
    ): int {
        if (!in_array($term, self::TERMS, true)) {
            throw new \DomainException('Pick a valid term before recording the payment.');
        }
        if ($amount <= 0) {
            throw new \DomainException('Payment amount must be greater than zero.');
        }
        if ($receiptNo === '') {
            throw new \DomainException('Receipt number is required.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
            throw new \DomainException('Payment date must be a valid YYYY-MM-DD value.');
        }

        $sfId = self::ensureStudentFee($studentId, $year, $term);

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            // Lock the row to compute new totals safely under concurrent
            // payment entry (two browser tabs, two bursars, etc.).
            $sf = $pdo->prepare("SELECT id, total_amount, paid_amount FROM student_fees WHERE id = ? FOR UPDATE");
            $sf->execute([$sfId]);
            $row = $sf->fetch();
            if (!$row) {
                $pdo->rollBack();
                throw new \DomainException('Student fees record could not be located.');
            }

            $total   = (float) $row['total_amount'];
            $paid    = (float) $row['paid_amount'];
            $balance = max(0.0, $total - $paid);

            // OVERPAYMENT GUARD — applied per term, since each term has its
            // own bill. Bursar must split a payment across terms manually.
            if ($total > 0 && $amount > $balance + 0.001) {
                $pdo->rollBack();
                throw new \DomainException(sprintf(
                    'Payment exceeds outstanding balance for %s (%.2f). Reduce the amount or pick another term.',
                    $term,
                    $balance
                ));
            }

            // Receipt-number uniqueness is enforced by a UNIQUE KEY, but we
            // also pre-check so we can flash a friendly message instead of a
            // raw SQL error.
            $dup = $pdo->prepare("SELECT 1 FROM payments WHERE receipt_no = ? LIMIT 1");
            $dup->execute([$receiptNo]);
            if ($dup->fetchColumn()) {
                $pdo->rollBack();
                throw new \DomainException('That receipt number is already in use. Use a unique value.');
            }

            $ins = $pdo->prepare(
                "INSERT INTO payments (student_fee_id, student_id, amount, payment_date, receipt_no, recorded_by, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $ins->execute([
                $sfId,
                $studentId,
                $amount,
                $paymentDate,
                $receiptNo,
                $bursarUserId ?: null,
                $notes === '' ? null : mb_substr($notes, 0, 250),
            ]);
            $paymentId = (int) $pdo->lastInsertId();

            $newPaid = $paid + $amount;
            $upd = $pdo->prepare(
                "UPDATE student_fees SET paid_amount = ?, status = ? WHERE id = ?"
            );
            $upd->execute([$newPaid, self::statusFor($total, $newPaid), $sfId]);

            $pdo->commit();
            return $paymentId;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Suggest the next sequential receipt number, e.g. "RCT-2025-000123".
     * The bursar can override in the form; we just pre-fill it.
     */
    public static function nextReceiptNumber(): string
    {
        $row = Database::query(
            "SELECT receipt_no FROM payments
             WHERE receipt_no LIKE ?
             ORDER BY id DESC LIMIT 1",
            ['RCT-' . date('Y') . '-%']
        )->fetch();

        $next = 1;
        if ($row && preg_match('/RCT-\d{4}-(\d+)$/', (string) $row['receipt_no'], $m)) {
            $next = ((int) $m[1]) + 1;
        }
        return sprintf('RCT-%s-%06d', date('Y'), $next);
    }

    /* ------------------------------------------------------------------ */
    /* Status helper                                                       */
    /* ------------------------------------------------------------------ */

    public static function statusFor(float $total, float $paid): string
    {
        if ($total <= 0)            return 'not_paid';
        if ($paid <= 0.001)         return 'not_paid';
        if ($paid + 0.001 < $total) return 'partial';
        return 'paid';
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'paid'    => 'Paid',
            'partial' => 'Partial',
            default   => 'Not Paid',
        };
    }

    public static function statusBadgeClass(string $status): string
    {
        return match ($status) {
            'paid'    => 'bg-success-subtle text-success-emphasis',
            'partial' => 'bg-warning-subtle text-warning-emphasis',
            default   => 'bg-danger-subtle text-danger-emphasis',
        };
    }
}
