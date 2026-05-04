<?php
use App\Core\View;
$layout = 'app';
$title  = 'Payments';

$scopeAll  = ($scope ?? 'period') === 'all';
$rowCount  = count($rows);
$rowSum    = array_sum(array_map(fn($r) => (float) $r['amount'], $rows));
?>

<style>
  .bursar-page .pretty-table .table { font-size: 0.86rem; }
  .bursar-page .pretty-table .table th,
  .bursar-page .pretty-table .table td { padding: 0.55rem 0.7rem; }
  .bursar-page .pretty-table thead th { position: sticky; top: 0; z-index: 1; }
</style>

<div class="bursar-page d-flex flex-column gap-3">

  <section class="dash-hero dash-hero--slim">
    <div class="dash-hero__content d-flex align-items-center gap-3">
      <span class="icon-chip icon-chip--green d-none d-sm-inline-grid"><i class="bi bi-receipt"></i></span>
      <div class="flex-grow-1" style="min-width:0;">
        <h2 class="dash-hero__title">Payments</h2>
        <p class="dash-hero__sub mb-0">
          <?php if ($scopeAll): ?>
            <span class="dash-hero__inline"><i class="bi bi-archive"></i> All periods · most recent first · up to 500</span>
          <?php else: ?>
            <span class="dash-hero__inline"><i class="bi bi-cash-coin"></i> AY <?= View::e($year) ?></span>
            <span class="dash-hero__inline"><i class="bi bi-bookmark-star"></i> <?= View::e($term) ?></span>
            <span class="dash-hero__inline"><i class="bi bi-clock-history"></i> Most recent first</span>
          <?php endif; ?>
        </p>
      </div>
    </div>
    <div class="dash-hero__actions">
      <div class="btn-group btn-group-sm" role="group" aria-label="Scope">
        <a class="btn btn-outline-secondary <?= $scopeAll ? '' : 'active' ?>"
           href="<?= $base ?>/bursar/payments">
          <i class="bi bi-bookmark-star"></i> Active period
        </a>
        <a class="btn btn-outline-secondary <?= $scopeAll ? 'active' : '' ?>"
           href="<?= $base ?>/bursar/payments?scope=all">
          <i class="bi bi-archive"></i> All periods
        </a>
      </div>
      <a class="btn btn-primary btn-sm" href="<?= $base ?>/bursar/students">
        <i class="bi bi-receipt-cutoff"></i> Record new
      </a>
    </div>
  </section>

  <!-- KPI strip -->
  <div class="row g-2">
    <div class="col-6 col-md-4">
      <div class="kpi-card kpi-card--xs h-100">
        <div class="kpi-card__icon kpi-card__icon--blue"><i class="bi bi-list-ol"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Transactions</div>
          <div class="kpi-card__value"><?= number_format($rowCount) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4">
      <div class="kpi-card kpi-card--xs h-100">
        <div class="kpi-card__icon kpi-card__icon--green"><i class="bi bi-wallet2"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Total Collected (shown)</div>
          <div class="kpi-card__value text-success"><?= number_format($rowSum, 2) ?></div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="kpi-card kpi-card--xs h-100">
        <div class="kpi-card__icon kpi-card__icon--purple"><i class="bi bi-bookmark-star"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Scope</div>
          <div class="kpi-card__value" style="font-size: 1rem;">
            <?= $scopeAll ? 'All periods' : View::e($year) . ' · ' . View::e($term) ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm pretty-table">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Date</th>
            <th>Receipt</th>
            <th>Student</th>
            <th>Class</th>
            <th>Period</th>
            <th class="text-end">Amount</th>
            <th>Entered By</th>
            <th>Notes</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">
              <?php if ($scopeAll): ?>
                No payments recorded yet.
              <?php else: ?>
                No payments yet for <?= View::e($year) ?> · <?= View::e($term) ?>.
                Use the period selector above to switch period, or
                <a href="<?= $base ?>/bursar/payments?scope=all">view all periods</a>.
              <?php endif; ?>
            </td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td><?= View::e(date('M j, Y', strtotime($r['payment_date']))) ?></td>
              <td><code class="small"><?= View::e($r['receipt_no']) ?></code></td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <?php
                    $av_photo = $r['photo_path'] ?? '';
                    $av_first = $r['first_name'] ?? '';
                    $av_last  = $r['last_name']  ?? '';
                    $av_size  = 40;
                    $av_shape = 'square';
                    include dirname(__DIR__) . '/_partials/student_avatar.php';
                  ?>
                  <div class="min-w-0">
                    <a href="<?= $base ?>/bursar/students/<?= (int) $r['student_id'] ?>" class="text-decoration-none fw-semibold">
                      <?= View::e(trim($r['first_name'] . ' ' . $r['last_name'])) ?>
                    </a>
                    <div class="small text-muted"><span class="badge-soft"><?= View::e($r['admission_no']) ?></span></div>
                  </div>
                </div>
              </td>
              <td><span class="badge bg-primary-subtle text-primary-emphasis"><?= View::e($r['level'] ?? '—') ?></span></td>
              <td>
                <span class="small text-muted"><?= View::e($r['academic_year'] ?? '—') ?></span>
                <?php if (!empty($r['term'])): ?>
                  <span class="badge bg-info-subtle text-info-emphasis ms-1"><?= View::e($r['term']) ?></span>
                <?php endif; ?>
              </td>
              <td class="text-end fw-semibold text-success"><?= number_format((float)$r['amount'], 2) ?></td>
              <td><?= View::e($r['bursar_name'] ?? '—') ?></td>
              <td class="text-muted small"><?= View::e($r['notes'] ?? '') ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
