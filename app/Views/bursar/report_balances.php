<?php
use App\Core\View;
use App\Services\FeesService;
$layout = 'app';
$title  = 'Students With Balances';

$totalBilled = array_sum(array_map(fn($r) => (float)$r['total_amount'], $rows));
$totalPaid   = array_sum(array_map(fn($r) => (float)$r['paid_amount'],  $rows));
$totalBal    = max(0.0, $totalBilled - $totalPaid);
$qs = $level !== '' ? '?level=' . urlencode($level) : '';
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
      <span class="icon-chip icon-chip--orange d-none d-sm-inline-grid"><i class="bi bi-graph-down-arrow"></i></span>
      <div class="flex-grow-1" style="min-width:0;">
        <h2 class="dash-hero__title">Students With Balances</h2>
        <p class="dash-hero__sub mb-0">
          <span class="dash-hero__inline"><i class="bi bi-cash-coin"></i> AY <?= View::e($year) ?></span>
          <span class="dash-hero__inline"><i class="bi bi-bookmark-star"></i> <?= View::e($term) ?></span>
          <?php if ($level): ?>
            <span class="dash-hero__inline"><i class="bi bi-funnel"></i> <?= View::e($level) ?></span>
          <?php endif; ?>
        </p>
      </div>
    </div>
    <div class="dash-hero__actions">
      <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/bursar/reports/paid<?= $qs ?>">
        <i class="bi bi-check2-circle"></i> Fully paid
      </a>
      <a class="btn btn-outline-success btn-sm" href="<?= $base ?>/bursar/reports/export.csv?type=balances<?= $level ? '&level=' . urlencode($level) : '' ?>">
        <i class="bi bi-filetype-csv"></i> CSV
      </a>
      <a class="btn btn-outline-primary btn-sm" href="<?= $base ?>/bursar/reports/print/balances<?= $qs ?>" data-inline-print>
        <i class="bi bi-printer"></i> Print
      </a>
    </div>
  </section>

  <!-- Filters + KPI -->
  <div class="row g-2">
    <div class="col-lg-5">
      <form class="card border-0 shadow-sm h-100" method="get" action="<?= $base ?>/bursar/reports/balances">
        <div class="card-body py-2 px-3 row g-2 align-items-end">
          <div class="col-8">
            <label class="form-label small fw-semibold mb-1">Filter by class</label>
            <select name="level" class="form-select form-select-sm">
              <option value="">All Form 1–4</option>
              <?php foreach ($levels as $lvl): ?>
                <option value="<?= View::e($lvl) ?>" <?= $level === $lvl ? 'selected' : '' ?>><?= View::e($lvl) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-4">
            <button class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel"></i> Apply</button>
          </div>
        </div>
      </form>
    </div>
    <div class="col-lg-3 col-md-6">
      <div class="kpi-card kpi-card--xs h-100">
        <div class="kpi-card__icon kpi-card__icon--orange"><i class="bi bi-people"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Owing</div>
          <div class="kpi-card__value"><?= number_format(count($rows)) ?></div>
        </div>
      </div>
    </div>
    <div class="col-lg-4 col-md-6">
      <div class="kpi-card kpi-card--xs h-100">
        <div class="kpi-card__icon kpi-card__icon--danger"><i class="bi bi-exclamation-circle"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Outstanding</div>
          <div class="kpi-card__value text-danger"><?= number_format($totalBal, 2) ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm pretty-table">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width: 40px;">#</th>
            <th>Student</th>
            <th>Class</th>
            <th>Section</th>
            <th class="text-end">Term Fees</th>
            <th class="text-end">Paid</th>
            <th class="text-end">Balance</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">
              No students with outstanding balances<?= $level ? ' in ' . View::e($level) : '' ?>. Nice!
            </td></tr>
          <?php else: foreach ($rows as $i => $r):
            $bal = max(0.0, (float)$r['total_amount'] - (float)$r['paid_amount']); ?>
            <tr>
              <td class="text-muted small"><?= $i + 1 ?></td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <?php
                    $av_photo = $r['photo_path'] ?? '';
                    $av_first = $r['first_name'] ?? '';
                    $av_last  = $r['last_name']  ?? '';
                    $av_size  = 44;
                    $av_shape = 'square';
                    include dirname(__DIR__) . '/_partials/student_avatar.php';
                  ?>
                  <div class="min-w-0">
                    <a href="<?= $base ?>/bursar/students/<?= (int) $r['id'] ?>" class="text-decoration-none fw-semibold">
                      <?= View::e(trim($r['first_name'] . ' ' . $r['last_name'])) ?>
                    </a>
                    <div class="small text-muted"><span class="badge-soft"><?= View::e($r['admission_no']) ?></span></div>
                  </div>
                </div>
              </td>
              <td><span class="badge bg-primary-subtle text-primary-emphasis"><?= View::e($r['level']) ?></span></td>
              <td>
                <?php if (($r['section'] ?? '') === 'boarding'): ?>
                  <span class="badge bg-info-subtle text-info-emphasis"><i class="bi bi-house-fill"></i> Boarding</span>
                <?php else: ?>
                  <span class="badge bg-warning-subtle text-warning-emphasis"><i class="bi bi-sun"></i> Day</span>
                <?php endif; ?>
              </td>
              <td class="text-end"><?= number_format((float)$r['total_amount'], 2) ?></td>
              <td class="text-end text-success"><?= number_format((float)$r['paid_amount'], 2) ?></td>
              <td class="text-end fw-bold text-danger"><?= number_format($bal, 2) ?></td>
              <td>
                <span class="badge <?= FeesService::statusBadgeClass((string)$r['status']) ?>">
                  <?= View::e(FeesService::statusLabel((string)$r['status'])) ?>
                </span>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
