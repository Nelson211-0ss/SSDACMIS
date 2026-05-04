<?php
use App\Core\View;
use App\Services\FeesService;
$layout = 'app';
$title  = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));

$total   = (float) ($bill['total_amount'] ?? 0);
$paid    = (float) ($bill['paid_amount']  ?? 0);
$balance = max(0.0, $total - $paid);
$status  = (string) ($bill['status'] ?? 'not_paid');
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-3">
  <div class="d-flex align-items-center gap-3">
    <?php
      $av_photo = $student['photo_path'] ?? '';
      $av_first = $student['first_name'] ?? '';
      $av_last  = $student['last_name']  ?? '';
      $av_size  = 64;
      include dirname(__DIR__) . '/_partials/student_avatar.php';
    ?>
    <div>
      <h4 class="mb-1">
        <i class="bi bi-person-vcard"></i>
        <?= View::e($title) ?>
      </h4>
      <p class="text-muted small mb-0">
        <span class="badge-soft"><?= View::e($student['admission_no']) ?></span>
        &middot; <?= View::e($student['level'] ?? '—') ?> <?= $student['class_name'] ? '(' . View::e($student['class_name']) . ')' : '' ?>
        &middot; <?= ucfirst((string) $student['section']) ?>
        &middot; AY <?= View::e($year) ?>
        &middot; <span class="badge bg-primary-subtle text-primary-emphasis"><?= View::e($term) ?></span>
      </p>
      <?php if (!empty($student['guardian_name']) || !empty($student['guardian_phone'])): ?>
        <p class="text-muted small mb-0 mt-1">
          <i class="bi bi-people"></i>
          <?= View::e($student['guardian_name'] ?: '—') ?>
          <?php if (!empty($student['guardian_phone'])): ?>
            &middot; <span class="font-monospace"><?= View::e($student['guardian_phone']) ?></span>
          <?php endif; ?>
        </p>
      <?php endif; ?>
    </div>
  </div>
  <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/bursar/students"><i class="bi bi-arrow-left"></i> Back to all students</a>
</div>

<!-- Per-term bill summary so the bursar can see all three terms at a glance. -->
<div class="row g-2 mb-3">
  <?php foreach ($termBills as $tb):
    $tTotal = (float) $tb['total_amount'];
    $tPaid  = (float) $tb['paid_amount'];
    $tBal   = max(0.0, $tTotal - $tPaid);
    $tStat  = (string) $tb['status'];
    $isActive = (string) $tb['term'] === $term;
  ?>
    <div class="col-md-4">
      <div class="card border-0 shadow-sm h-100 <?= $isActive ? 'border-primary border-2' : '' ?>">
        <div class="card-body py-3">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <strong>
              <?= View::e($tb['term']) ?>
              <?php if ($isActive): ?>
                <span class="badge bg-primary ms-1">active</span>
              <?php endif; ?>
            </strong>
            <span class="badge <?= FeesService::statusBadgeClass($tStat) ?>"><?= View::e(FeesService::statusLabel($tStat)) ?></span>
          </div>
          <div class="d-flex justify-content-between small">
            <span class="text-muted">Term fees</span>
            <span class="fw-semibold"><?= number_format($tTotal, 2) ?></span>
          </div>
          <div class="d-flex justify-content-between small">
            <span class="text-muted">Paid</span>
            <span class="text-success fw-semibold"><?= number_format($tPaid, 2) ?></span>
          </div>
          <div class="d-flex justify-content-between small">
            <span class="text-muted">Balance</span>
            <span class="fw-semibold text-<?= $tBal > 0 ? 'danger' : 'success' ?>"><?= number_format($tBal, 2) ?></span>
          </div>
          <?php if (!$isActive): ?>
            <form method="post" action="<?= $base ?>/bursar/period" class="mt-2">
              <input type="hidden" name="_csrf" value="<?= $csrf ?>">
              <input type="hidden" name="year" value="<?= View::e($year) ?>">
              <input type="hidden" name="term" value="<?= View::e($tb['term']) ?>">
              <input type="hidden" name="return" value="/bursar/students/<?= (int) $student['id'] ?>">
              <button class="btn btn-outline-primary btn-sm w-100" type="submit">
                <i class="bi bi-arrow-right-circle"></i> Switch to <?= View::e($tb['term']) ?>
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Active term summary card row (kept for visual continuity with prior layout). -->
<div class="row g-3 mb-3">
  <div class="col-md-3">
    <div class="card border-0 shadow-sm"><div class="card-body py-3">
      <div class="text-muted small">Active term status</div>
      <div class="h5 mb-0">
        <span class="badge <?= FeesService::statusBadgeClass($status) ?>"><?= View::e(FeesService::statusLabel($status)) ?></span>
      </div>
    </div></div>
  </div>
  <div class="col-md-3">
    <div class="card border-0 shadow-sm"><div class="card-body py-3">
      <div class="text-muted small"><?= View::e($term) ?> fees</div>
      <div class="h5 mb-0"><?= number_format($total, 2) ?></div>
    </div></div>
  </div>
  <div class="col-md-3">
    <div class="card border-0 shadow-sm"><div class="card-body py-3">
      <div class="text-muted small">Paid (<?= View::e($term) ?>)</div>
      <div class="h5 mb-0 text-success"><?= number_format($paid, 2) ?></div>
    </div></div>
  </div>
  <div class="col-md-3">
    <div class="card border-0 shadow-sm"><div class="card-body py-3">
      <div class="text-muted small">Balance (<?= View::e($term) ?>)</div>
      <div class="h5 mb-0 text-<?= $balance > 0 ? 'danger' : 'success' ?>"><?= number_format($balance, 2) ?></div>
    </div></div>
  </div>
</div>

<?php if ($balance > 0 || $total === 0.0): ?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-white d-flex align-items-center">
    <span class="card-header-icon card-header-icon--green me-2" aria-hidden="true"><i class="bi bi-cash-coin"></i></span>
    <strong class="mb-0">Record a payment for <?= View::e($term) ?> · <?= View::e($year) ?></strong>
  </div>
  <form method="post" action="<?= $base ?>/bursar/payments" class="card-body row g-2 align-items-end">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <input type="hidden" name="student_id" value="<?= (int) $student['id'] ?>">
    <input type="hidden" name="academic_year" value="<?= View::e($year) ?>">
    <input type="hidden" name="term" value="<?= View::e($term) ?>">
    <input type="hidden" name="return" value="/bursar/students/<?= (int) $student['id'] ?>">

    <div class="col-md-3">
      <label class="form-label small fw-semibold mb-1">Amount <span class="text-danger">*</span></label>
      <div class="input-group input-group-sm">
        <span class="input-group-text">$</span>
        <input type="number" min="0.01" step="0.01" name="amount" class="form-control text-end"
               required value="<?= number_format($balance, 2, '.', '') ?>">
      </div>
    </div>
    <div class="col-md-2">
      <label class="form-label small fw-semibold mb-1">Date <span class="text-danger">*</span></label>
      <input type="date" name="payment_date" class="form-control form-control-sm" required
             value="<?= date('Y-m-d') ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label small fw-semibold mb-1">Receipt no. <span class="text-danger">*</span></label>
      <input type="text" name="receipt_no" class="form-control form-control-sm" required maxlength="50"
             value="<?= View::e($nextReceipt) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label small fw-semibold mb-1">Notes <span class="text-muted fw-normal small">(optional)</span></label>
      <input type="text" name="notes" class="form-control form-control-sm" maxlength="250"
             placeholder="Cash, mobile, cheque, ...">
    </div>
    <div class="col-md-1 d-grid">
      <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save"></i></button>
    </div>
  </form>
</div>
<?php else: ?>
  <div class="alert alert-success">
    <i class="bi bi-check-circle"></i>
    <strong><?= View::e($term) ?> fully paid.</strong> No payments to record for academic year <?= View::e($year) ?> · <?= View::e($term) ?>.
    Switch to another term above to record a payment there.
  </div>
<?php endif; ?>

<?php include __DIR__ . '/_payment_modal.php'; ?>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex align-items-center">
    <span class="card-header-icon card-header-icon--blue me-2" aria-hidden="true"><i class="bi bi-clock-history"></i></span>
    <strong class="mb-0">Transaction history (all terms)</strong>
  </div>
  <div class="table-responsive">
    <table class="table mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>Date</th>
          <th>Period</th>
          <th>Receipt No.</th>
          <th class="text-end">Amount</th>
          <th>Entered By</th>
          <th>Notes</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($payments)): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">No payments yet.</td></tr>
        <?php else: foreach ($payments as $p): ?>
          <tr>
            <td><?= View::e(date('Y-m-d', strtotime($p['payment_date']))) ?></td>
            <td>
              <span class="small text-muted"><?= View::e($p['academic_year'] ?? $year) ?></span>
              <?php if (!empty($p['term'])): ?>
                <span class="badge bg-primary-subtle text-primary-emphasis ms-1"><?= View::e($p['term']) ?></span>
              <?php endif; ?>
            </td>
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
