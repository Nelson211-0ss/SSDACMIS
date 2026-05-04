<?php
use App\Core\View;
use App\Services\FeesService;
$layout = 'app';
$title  = 'Fees Setup';

// Roll up totals so the bursar sees scope of the change at a glance.
$totalStudents = 0;
$totalYearly   = 0.0;
foreach ($levels as $lvl) {
  foreach (FeesService::SECTIONS as $sec) {
    $totalStudents += (int) ($counts[$lvl][$sec] ?? 0);
    $totalYearly   += (float) ($map[$lvl][$sec] ?? 0) * (int) ($counts[$lvl][$sec] ?? 0);
  }
}
$totalPerTerm = FeesService::termAmount($totalYearly);
?>

<div class="bursar-page d-flex flex-column gap-3">

  <section class="dash-hero dash-hero--slim">
    <div class="dash-hero__content d-flex align-items-center gap-3">
      <span class="icon-chip icon-chip--purple d-none d-sm-inline-grid"><i class="bi bi-sliders"></i></span>
      <div class="flex-grow-1" style="min-width:0;">
        <h2 class="dash-hero__title">Fees Structure Setup</h2>
        <p class="dash-hero__sub mb-0">
          <span class="dash-hero__inline"><i class="bi bi-cash-coin"></i> AY <?= View::e($year) ?></span>
          <span class="dash-hero__inline"><i class="bi bi-info-circle"></i> Yearly amount auto‑split into 3 terms</span>
        </p>
      </div>
    </div>
    <div class="dash-hero__actions">
      <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/bursar"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
  </section>

  <!-- KPI strip — scope of this structure -->
  <div class="row g-2">
    <div class="col-6 col-md-4">
      <div class="kpi-card kpi-card--xs h-100">
        <div class="kpi-card__icon kpi-card__icon--blue"><i class="bi bi-people-fill"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Students Affected</div>
          <div class="kpi-card__value"><?= number_format($totalStudents) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4">
      <div class="kpi-card kpi-card--xs h-100">
        <div class="kpi-card__icon kpi-card__icon--purple"><i class="bi bi-calendar-event"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Yearly Total</div>
          <div class="kpi-card__value"><?= number_format($totalYearly, 2) ?></div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="kpi-card kpi-card--xs h-100">
        <div class="kpi-card__icon kpi-card__icon--green"><i class="bi bi-bookmark-star"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Per Term (÷ 3)</div>
          <div class="kpi-card__value"><?= number_format($totalPerTerm, 2) ?></div>
        </div>
      </div>
    </div>
  </div>

  <form method="post" action="<?= $base ?>/bursar/structure" class="card border-0 shadow-sm">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">

    <div class="card-header bg-white d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div class="d-flex align-items-center fw-semibold">
        <span class="card-header-icon card-header-icon--green me-2" aria-hidden="true"><i class="bi bi-cash-coin"></i></span>
        Yearly fees · AY <?= View::e($year) ?>
      </div>
      <span class="badge bg-info-subtle text-info-emphasis small">
        <i class="bi bi-info-circle"></i> Lowering an amount below what a student already paid keeps that student at the paid level.
      </span>
    </div>

    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Form</th>
            <th>Section</th>
            <th class="text-end">Students</th>
            <th style="width: 240px;">Yearly Fees</th>
            <th class="text-end">Per term <small class="text-muted fw-normal">(÷ 3)</small></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($levels as $lvl): ?>
            <?php foreach (FeesService::SECTIONS as $sec):
              $key  = "amounts[$lvl][$sec]";
              $val  = (float) ($map[$lvl][$sec] ?? 0);
              $cnt  = (int) ($counts[$lvl][$sec] ?? 0);
              $perTerm = FeesService::termAmount($val);
            ?>
              <tr>
                <td><span class="badge bg-primary-subtle text-primary-emphasis"><?= View::e($lvl) ?></span></td>
                <td>
                  <?php if ($sec === 'boarding'): ?>
                    <span class="badge bg-info-subtle text-info-emphasis"><i class="bi bi-house-fill"></i> Boarding</span>
                  <?php else: ?>
                    <span class="badge bg-warning-subtle text-warning-emphasis"><i class="bi bi-sun"></i> Day</span>
                  <?php endif; ?>
                </td>
                <td class="text-end text-muted"><?= number_format($cnt) ?></td>
                <td>
                  <div class="input-group input-group-sm">
                    <span class="input-group-text">$</span>
                    <input type="number" name="<?= $key ?>"
                           class="form-control text-end"
                           min="0" step="0.01"
                           value="<?= number_format($val, 2, '.', '') ?>"
                           data-yearly-fee
                           required>
                  </div>
                </td>
                <td class="text-end fw-semibold" data-per-term>
                  <?= number_format($perTerm, 2) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="card-footer py-2 px-3 bg-body-secondary bg-opacity-25 border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
      <span class="small text-muted mb-0">
        Saving runs the auto‑assignment job for all Form 1–4 students.
      </span>
      <button type="submit" class="btn btn-primary btn-sm px-4"><i class="bi bi-check-lg me-1"></i> Save fees structure</button>
    </div>
  </form>

</div>

<script>
(function () {
  function update(input) {
    var row = input.closest('tr');
    if (!row) return;
    var cell = row.querySelector('[data-per-term]');
    if (!cell) return;
    var v = parseFloat(input.value || '0');
    if (!isFinite(v) || v < 0) v = 0;
    cell.textContent = (v / 3).toLocaleString(undefined, {
      minimumFractionDigits: 2, maximumFractionDigits: 2,
    });
  }
  document.querySelectorAll('[data-yearly-fee]').forEach(function (input) {
    input.addEventListener('input', function () { update(input); });
  });
})();
</script>
