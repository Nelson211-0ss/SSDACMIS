<?php
use App\Core\View;
use App\Services\FeesService;

$layout = 'app';
$title  = 'Bursar Dashboard';

$students    = (int)   ($totals['students']    ?? 0);
$expected    = (float) ($totals['expected']    ?? 0);
$collected   = (float) ($totals['collected']   ?? 0);
$outstanding = (float) ($totals['outstanding'] ?? 0);
$paidCount    = (int) ($totals['paid_count']    ?? 0);
$partialCount = (int) ($totals['partial_count'] ?? 0);
$unpaidCount  = (int) ($totals['unpaid_count']  ?? 0);
$collectedPct = $expected > 0 ? min(100, round(($collected / $expected) * 100, 1)) : 0;

$h = (int) date('G');
$greeting  = $h < 12 ? 'Good morning' : ($h < 17 ? 'Good afternoon' : 'Good evening');
$greetIcon = $h < 12 ? 'bi-sunrise'   : ($h < 17 ? 'bi-sun'         : 'bi-moon-stars');
$greetTone = $h < 12 ? 'orange'       : ($h < 17 ? 'yellow'         : 'purple');
?>

<style>
  /* ----- Bursar dashboard "fit on one page" tuning -----
   * The dashboard is laid out as four stacked rows: hero, KPIs, the
   * main 3-column row (collection by class · recent payments · term
   * breakdown), and a tiny progress strip. Tables and the recent-
   * payments list scroll *inside* their cards so the page itself
   * doesn't introduce vertical scrolling on typical 13"+ screens. */
  .bursar-dash .dash-row    { min-height: 0; }
  .bursar-dash .dash-card   { display: flex; flex-direction: column; min-height: 0; }
  .bursar-dash .dash-card .table { font-size: 0.84rem; }
  .bursar-dash .dash-card .table th,
  .bursar-dash .dash-card .table td { padding: 0.45rem 0.6rem; }
  .bursar-dash .dash-card .card-header { padding: 0.55rem 0.85rem; font-size: 0.9rem; }
  .bursar-dash .dash-card .card-body   { padding: 0.75rem 0.85rem; }
  .bursar-dash .dash-scroll { overflow-y: auto; min-height: 0; }
  .bursar-dash .dash-payments { max-height: 24rem; }

  /* Recent payment row: bigger square photo + cleaner alignment */
  .bursar-dash .pay-row {
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 0.75rem;
    align-items: center;
    padding: 0.6rem 0.85rem;
  }
  .bursar-dash .pay-row + .pay-row { border-top: 1px solid var(--bs-border-color-translucent); }
  .bursar-dash .pay-row__name  { font-weight: 600; line-height: 1.2; }
  .bursar-dash .pay-row__meta  { font-size: 0.75rem; color: var(--bs-secondary-color); }
  .bursar-dash .pay-row__amt   { font-weight: 700; font-size: 0.95rem; }

  /* Tighter KPI cards so all four fit on a 1280-wide viewport
   * alongside the page sidebar without wrapping. */
  .bursar-dash .kpi-card { padding: 0.7rem 0.85rem; }
  .bursar-dash .kpi-card__value { font-size: 1.3rem; }
</style>

<div class="bursar-dash d-flex flex-column gap-3">

  <!-- Hero -->
  <section class="dash-hero py-2">
    <div class="dash-hero__content d-flex align-items-center gap-3">
      <span class="icon-chip icon-chip--<?= $greetTone ?> d-none d-sm-inline-grid">
        <i class="bi <?= $greetIcon ?>"></i>
      </span>
      <div class="flex-grow-1" style="min-width:0;">
        <h2 class="dash-hero__title h5 mb-1">
          <?= $greeting ?>, <?= View::e($auth['name']) ?>.
        </h2>
        <p class="dash-hero__sub mb-0 small">
          <span class="dash-hero__date">
            <i class="bi bi-calendar3"></i><?= date('l, M j, Y') ?>
          </span>
          <span class="dash-hero__inline">
            <i class="bi bi-cash-coin"></i> AY <?= View::e($year) ?>
          </span>
          <span class="dash-hero__inline">
            <i class="bi bi-bookmark-star"></i> <?= View::e($term) ?>
          </span>
        </p>
      </div>
    </div>
    <div class="dash-hero__actions">
      <a href="<?= $base ?>/bursar/students" class="btn btn-primary btn-sm">
        <i class="bi bi-receipt-cutoff"></i> Record payment
      </a>
      <a href="<?= $base ?>/bursar/exam-permits" class="btn btn-outline-success btn-sm">
        <i class="bi bi-shield-check"></i> Exam permits
      </a>
      <a href="<?= $base ?>/bursar/structure" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-sliders"></i> Fees setup
      </a>
      <a href="<?= $base ?>/bursar/reports/balances" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-graph-down-arrow"></i> Balances
      </a>
    </div>
  </section>

  <!-- KPI strip -->
  <div class="row g-2">
    <div class="col-6 col-xl-3">
      <a href="<?= $base ?>/bursar/students" class="kpi-card kpi-card--compact h-100">
        <div class="kpi-card__icon kpi-card__icon--blue"><i class="bi bi-people-fill"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Total Students</div>
          <div class="kpi-card__value"><?= number_format($students) ?></div>
          <div class="kpi-card__delta kpi-card__delta--flat">
            <i class="bi bi-mortarboard"></i> Form 1–4 enrolled
          </div>
        </div>
      </a>
    </div>

    <div class="col-6 col-xl-3">
      <div class="kpi-card kpi-card--compact h-100">
        <div class="kpi-card__icon kpi-card__icon--purple"><i class="bi bi-cash-stack"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Expected (term)</div>
          <div class="kpi-card__value"><?= number_format($expected, 2) ?></div>
          <div class="kpi-card__delta kpi-card__delta--flat">
            <i class="bi bi-sliders"></i> Per current structure
          </div>
        </div>
      </div>
    </div>

    <div class="col-6 col-xl-3">
      <a href="<?= $base ?>/bursar/payments" class="kpi-card kpi-card--compact h-100">
        <div class="kpi-card__icon kpi-card__icon--green"><i class="bi bi-wallet2"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Collected (term)</div>
          <div class="kpi-card__value text-success"><?= number_format($collected, 2) ?></div>
          <div class="kpi-card__delta kpi-card__delta--up">
            <i class="bi bi-arrow-up-right"></i> <?= $collectedPct ?>% of expected
          </div>
        </div>
      </a>
    </div>

    <div class="col-6 col-xl-3">
      <a href="<?= $base ?>/bursar/reports/balances" class="kpi-card kpi-card--compact h-100">
        <div class="kpi-card__icon kpi-card__icon--orange"><i class="bi bi-exclamation-circle"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Outstanding</div>
          <div class="kpi-card__value text-danger"><?= number_format($outstanding, 2) ?></div>
          <div class="kpi-card__delta kpi-card__delta--down">
            <i class="bi bi-arrow-down-right"></i>
            <?= number_format($partialCount + $unpaidCount) ?> students owing
          </div>
        </div>
      </a>
    </div>
  </div>

  <!-- Slim collection progress strip -->
  <div class="card border-0 shadow-sm">
    <div class="card-body py-2 px-3">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
        <strong class="small">
          <i class="bi bi-bar-chart-steps"></i>
          Collection · <?= View::e($year) ?> · <?= View::e($term) ?>
        </strong>
        <span class="d-flex flex-wrap gap-1">
          <span class="badge <?= FeesService::statusBadgeClass('paid') ?>">Paid <?= number_format($paidCount) ?></span>
          <span class="badge <?= FeesService::statusBadgeClass('partial') ?>">Partial <?= number_format($partialCount) ?></span>
          <span class="badge <?= FeesService::statusBadgeClass('not_paid') ?>">Not paid <?= number_format($unpaidCount) ?></span>
        </span>
      </div>
      <div class="progress" role="progressbar" aria-valuenow="<?= $collectedPct ?>" aria-valuemin="0" aria-valuemax="100" style="height: 0.95rem;">
        <div class="progress-bar bg-success" style="width: <?= $collectedPct ?>%"><?= $collectedPct ?>%</div>
      </div>
      <div class="d-flex justify-content-between small text-muted mt-1">
        <span>Collected: <strong><?= number_format($collected, 2) ?></strong></span>
        <span>Outstanding: <strong class="text-danger"><?= number_format($outstanding, 2) ?></strong></span>
        <span>Expected: <strong><?= number_format($expected, 2) ?></strong></span>
      </div>
    </div>
  </div>

  <!-- Main 3-column row: by-class · recent payments · term breakdown -->
  <div class="row g-2 dash-row align-items-stretch">

    <!-- By-class -->
    <div class="col-12 col-xl-5">
      <div class="card dash-card h-100 border-0 shadow-sm">
        <div class="card-header bg-white d-flex align-items-center justify-content-between">
          <span class="d-flex align-items-center fw-semibold">
            <span class="card-header-icon card-header-icon--blue me-2" aria-hidden="true"><i class="bi bi-mortarboard"></i></span>
            By class · <?= View::e($term) ?>
          </span>
          <a href="<?= $base ?>/bursar/students" class="small text-decoration-none">
            View students <i class="bi bi-arrow-right small"></i>
          </a>
        </div>
        <div class="dash-scroll table-responsive">
          <table class="table table-sm mb-0 align-middle">
            <thead class="table-light sticky-top" style="top: 0;">
              <tr>
                <th>Class</th>
                <th class="text-end">Stud.</th>
                <th class="text-end">Expected</th>
                <th class="text-end">Collected</th>
                <th class="text-end">Outstanding</th>
                <th style="width: 120px;">Progress</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($byLevel)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No fees assigned yet. Set up the fees structure to get started.</td></tr>
              <?php else: foreach ($byLevel as $r):
                $exp = (float) $r['expected']; $col = (float) $r['collected'];
                $pct = $exp > 0 ? min(100, round(($col / $exp) * 100)) : 0; ?>
                <tr>
                  <td><span class="badge bg-primary-subtle text-primary-emphasis"><?= View::e($r['level']) ?></span></td>
                  <td class="text-end"><?= number_format((int) $r['students']) ?></td>
                  <td class="text-end"><?= number_format($exp, 2) ?></td>
                  <td class="text-end text-success"><?= number_format($col, 2) ?></td>
                  <td class="text-end text-danger"><?= number_format((float) $r['outstanding'], 2) ?></td>
                  <td>
                    <div class="progress" style="height: 0.45rem;" title="<?= $pct ?>% collected">
                      <div class="progress-bar bg-success" style="width: <?= $pct ?>%"></div>
                    </div>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Recent payments -->
    <div class="col-12 col-xl-4">
      <div class="card dash-card h-100 border-0 shadow-sm">
        <div class="card-header bg-white d-flex align-items-center justify-content-between">
          <span class="d-flex align-items-center fw-semibold">
            <span class="card-header-icon card-header-icon--green me-2" aria-hidden="true"><i class="bi bi-receipt"></i></span>
            Recent payments
          </span>
          <a href="<?= $base ?>/bursar/payments" class="small text-decoration-none">
            All payments <i class="bi bi-arrow-right small"></i>
          </a>
        </div>
        <div class="dash-scroll dash-payments">
          <?php if (empty($recentPayments)): ?>
            <div class="empty-state py-4 text-center">
              <i class="bi bi-receipt d-block"></i>
              <div>No payments recorded yet.</div>
              <a href="<?= $base ?>/bursar/students" class="btn btn-sm btn-outline-primary mt-3">
                <i class="bi bi-receipt-cutoff"></i> Record a payment
              </a>
            </div>
          <?php else: foreach ($recentPayments as $p): ?>
            <div class="pay-row">
              <?php
                $av_photo = $p['photo_path'] ?? '';
                $av_first = $p['first_name'] ?? '';
                $av_last  = $p['last_name']  ?? '';
                $av_size  = 56;
                $av_shape = 'square';
                include dirname(__DIR__) . '/_partials/student_avatar.php';
              ?>
              <div class="min-w-0">
                <div class="pay-row__name text-truncate">
                  <?= View::e(trim($p['first_name'] . ' ' . $p['last_name'])) ?>
                </div>
                <div class="pay-row__meta text-truncate">
                  <span class="badge-soft"><?= View::e($p['admission_no']) ?></span>
                  &middot; receipt <code class="small"><?= View::e($p['receipt_no']) ?></code>
                </div>
                <div class="pay-row__meta">
                  <i class="bi bi-person-circle"></i>
                  <?= View::e($p['bursar_name'] ?? 'Unknown') ?>
                  &middot; <?= View::e(date('M j, Y', strtotime($p['payment_date']))) ?>
                  <?php if (!empty($p['term'])): ?>
                    &middot; <span class="badge bg-primary-subtle text-primary-emphasis"><?= View::e($p['term']) ?></span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="pay-row__amt text-success text-end">
                <?= number_format((float) $p['amount'], 2) ?>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <!-- Term breakdown -->
    <div class="col-12 col-xl-3">
      <div class="card dash-card h-100 border-0 shadow-sm">
        <div class="card-header bg-white d-flex align-items-center justify-content-between">
          <span class="d-flex align-items-center fw-semibold">
            <span class="card-header-icon card-header-icon--purple me-2" aria-hidden="true"><i class="bi bi-bookmark-star"></i></span>
            Terms · <?= View::e($year) ?>
          </span>
        </div>
        <?php if (empty($byTerm)): ?>
          <div class="card-body small text-muted">
            Term data appears here once the fees structure is set up.
          </div>
        <?php else: ?>
          <div class="card-body p-0 dash-scroll">
            <ul class="list-unstyled mb-0">
              <?php foreach ($byTerm as $t):
                $exp = (float) $t['expected']; $col = (float) $t['collected'];
                $pct = $exp > 0 ? min(100, round(($col / $exp) * 100)) : 0;
                $isActive = (string) $t['term'] === $term; ?>
                <li class="px-3 py-2<?= $isActive ? ' bg-primary bg-opacity-10' : '' ?> border-bottom">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="badge <?= $isActive ? 'bg-primary' : 'bg-primary-subtle text-primary-emphasis' ?>">
                      <?= View::e($t['term']) ?><?= $isActive ? ' · active' : '' ?>
                    </span>
                    <?php if (!$isActive): ?>
                      <form method="post" action="<?= $base ?>/bursar/period" class="m-0">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="year" value="<?= View::e($year) ?>">
                        <input type="hidden" name="term" value="<?= View::e($t['term']) ?>">
                        <input type="hidden" name="return" value="/bursar">
                        <button class="btn btn-sm btn-outline-primary py-0 px-2" type="submit"
                                title="Switch to <?= View::e($t['term']) ?>">
                          Switch <i class="bi bi-arrow-right-circle"></i>
                        </button>
                      </form>
                    <?php endif; ?>
                  </div>
                  <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Expected</span>
                    <span><?= number_format($exp, 2) ?></span>
                  </div>
                  <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Collected</span>
                    <span class="text-success fw-semibold"><?= number_format($col, 2) ?></span>
                  </div>
                  <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Outstanding</span>
                    <span class="text-danger fw-semibold"><?= number_format((float) $t['outstanding'], 2) ?></span>
                  </div>
                  <div class="progress mt-1" style="height: 0.4rem;" title="<?= $pct ?>% collected">
                    <div class="progress-bar bg-success" style="width: <?= $pct ?>%"></div>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div>

</div>
