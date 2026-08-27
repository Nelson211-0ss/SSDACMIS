<?php
use App\Core\View;
use App\Services\FeesService;
$layout = 'app';
$title  = 'Fees';

$pageTitle = 'Fees';
$pageSubtitle = 'Fee bill and payment history.';
$pageIcon = 'bi-cash-coin';
include dirname(__DIR__) . '/_partials/app_page_header.php';
?>

<?php if (!$student): ?>
  <div class="alert alert-warning"><i class="bi bi-info-circle"></i> Student not found.</div>
<?php else: ?>
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <div class="d-flex justify-content-between flex-wrap gap-2 align-items-end">
        <div>
          <a class="btn btn-outline-secondary btn-sm mb-2" href="<?= $base ?>/parent"><i class="bi bi-arrow-left"></i> Back</a>
          <div class="fw-semibold"><?= View::e(trim($student['first_name'] . ' ' . $student['last_name'])) ?></div>
          <div class="small text-muted">
            <span class="badge-soft"><?= View::e($student['admission_no']) ?></span>
            &middot; <?= View::e($student['level'] ?? '—') ?> <?= $student['class_name'] ? '(' . View::e($student['class_name']) . ')' : '' ?>
            &middot; <?= ucfirst((string) ($student['section'] ?? '')) ?>
          </div>
        </div>
        <form method="get" class="d-flex align-items-end flex-wrap gap-2">
          <div>
            <label class="form-label small mb-1">Academic year</label>
            <input name="year" class="form-control form-control-sm" value="<?= View::e($year) ?>">
          </div>
          <button type="submit" class="btn btn-outline-primary btn-sm">Reload</button>
        </form>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white"><strong class="mb-0">Bill by term — <?= View::e($year) ?></strong></div>
    <div class="table-responsive">
      <table class="table mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th>Term</th>
            <th class="text-end">Total</th>
            <th class="text-end">Paid</th>
            <th class="text-end">Balance</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (FeesService::TERMS as $term):
            $b = $bills[$term] ?? null;
            $total = (float) ($b['total_amount'] ?? 0);
            $paid  = (float) ($b['paid_amount']  ?? 0);
            $bal   = max(0.0, $total - $paid);
            $status = (string) ($b['status'] ?? 'not_paid');
          ?>
            <tr>
              <td><?= View::e($term) ?></td>
              <td class="text-end"><?= number_format($total, 2) ?></td>
              <td class="text-end text-success"><?= number_format($paid, 2) ?></td>
              <td class="text-end text-<?= $bal > 0 ? 'danger' : 'success' ?>"><?= number_format($bal, 2) ?></td>
              <td><span class="badge <?= FeesService::statusBadgeClass($status) ?>"><?= View::e(FeesService::statusLabel($status)) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex align-items-center">
      <span class="card-header-icon card-header-icon--blue me-2" aria-hidden="true"><i class="bi bi-clock-history"></i></span>
      <strong class="mb-0">Payment history</strong>
    </div>
    <div class="table-responsive">
      <table class="table mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th>Date</th>
            <th>Receipt No.</th>
            <th class="text-end">Amount</th>
            <th>Recorded By</th>
            <th>Notes</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($payments)): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">No payments recorded yet.</td></tr>
          <?php else: foreach ($payments as $p): ?>
            <tr>
              <td><?= View::e(date('Y-m-d', strtotime($p['payment_date']))) ?></td>
              <td><code class="small"><?= View::e($p['receipt_no']) ?></code></td>
              <td class="text-end fw-semibold text-success"><?= number_format((float) $p['amount'], 2) ?></td>
              <td><?= View::e($p['bursar_name'] ?? '—') ?></td>
              <td class="text-muted small"><?= View::e($p['notes'] ?? '') ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
