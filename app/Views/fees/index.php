<?php
use App\Core\View;
use App\Services\FeesService;
$layout = 'app';
$title  = 'My Fees';

$total   = (float) ($bill['total_amount'] ?? 0);
$paid    = (float) ($bill['paid_amount']  ?? 0);
$balance = max(0.0, $total - $paid);
$status  = (string) ($bill['status'] ?? 'not_paid');
?>
<h4 class="mb-3"><i class="bi bi-cash-coin"></i> My Fees</h4>

<?php if (!$student): ?>
  <div class="alert alert-warning">
    <i class="bi bi-info-circle"></i>
    Your student record is not linked yet. Please contact the school administrator.
  </div>
<?php else: ?>
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <div class="d-flex justify-content-between flex-wrap gap-2">
        <div>
          <div class="fw-semibold"><?= View::e(trim($student['first_name'] . ' ' . $student['last_name'])) ?></div>
          <div class="small text-muted">
            <span class="badge-soft"><?= View::e($student['admission_no']) ?></span>
            &middot; <?= View::e($student['level'] ?? '—') ?> <?= $student['class_name'] ? '(' . View::e($student['class_name']) . ')' : '' ?>
            &middot; <?= ucfirst((string) $student['section']) ?>
          </div>
        </div>
        <div class="text-md-end">
          <div class="text-muted small">Academic year</div>
          <div class="fw-semibold"><?= View::e($year) ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-3">
      <div class="text-muted small">Status</div>
      <div class="h5 mb-0">
        <span class="badge <?= FeesService::statusBadgeClass($status) ?>"><?= View::e(FeesService::statusLabel($status)) ?></span>
      </div>
    </div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-3">
      <div class="text-muted small">Total fees</div>
      <div class="h5 mb-0"><?= number_format($total, 2) ?></div>
    </div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-3">
      <div class="text-muted small">Paid</div>
      <div class="h5 mb-0 text-success"><?= number_format($paid, 2) ?></div>
    </div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body py-3">
      <div class="text-muted small">Balance</div>
      <div class="h5 mb-0 text-<?= $balance > 0 ? 'danger' : 'success' ?>"><?= number_format($balance, 2) ?></div>
    </div></div></div>
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
              <td class="text-end fw-semibold text-success"><?= number_format((float)$p['amount'], 2) ?></td>
              <td><?= View::e($p['bursar_name'] ?? '—') ?></td>
              <td class="text-muted small"><?= View::e($p['notes'] ?? '') ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
