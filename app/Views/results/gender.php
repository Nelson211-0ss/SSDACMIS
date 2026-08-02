<?php
use App\Core\View;
$layout = 'app';
$title = 'Gender Performance';

$fmtPct = static function (?float $v): string {
    return $v === null ? '—' : number_format($v, 1) . '%';
};

$boysTotal  = $totals['male'];
$girlsTotal = $totals['female'];
$gap = ($boysTotal['avg'] !== null && $girlsTotal['avg'] !== null)
    ? $girlsTotal['avg'] - $boysTotal['avg']
    : null;
?>
<div class="results-landscape-root results-print-area report-page--print-landscape">
  <div class="results-toolbar d-print-none d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      <h4 class="mb-1"><i class="bi bi-bar-chart-steps"></i> Gender performance</h4>
      <div class="small text-muted">
        <?= View::e($year) ?> · <?= View::e($term) ?> · Boys vs girls — students, average % and pass rate
      </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <button type="button" class="btn btn-primary btn-sm" onclick="window.print()" title="Print this page">
        <i class="bi bi-printer"></i> Print
      </button>
      <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?><?= $portalPrefix ?>/results?year=<?= rawurlencode($year) ?>&term=<?= rawurlencode($term) ?>">
        <i class="bi bi-arrow-left"></i> Back to results
      </a>
      <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?><?= $portalPrefix ?>/results">
        <i class="bi bi-calendar3"></i> Change period
      </a>
    </div>
  </div>

  <div class="results-print-brand border-bottom pb-2 mb-3 d-none d-print-block">
    <div class="fw-bold"><?= View::e($schoolName) ?></div>
    <div class="small">Gender performance · <?= View::e($year) ?> · <?= View::e($term) ?></div>
  </div>

  <?php if (empty($byClass)): ?>
    <div class="alert alert-info d-print-none">
      No classes available for your account, or results have not been published for this period yet.
    </div>
  <?php else: ?>

  <div class="row g-3 mb-4 d-print-none">
    <div class="col-sm-6 col-lg-3">
      <div class="kpi-card">
        <div class="kpi-card__icon kpi-card__icon--blue"><i class="bi bi-gender-male"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Boys — average</div>
          <div class="kpi-card__value"><?= $fmtPct($boysTotal['avg']) ?></div>
          <div class="kpi-card__delta kpi-card__delta--flat"><?= (int) $boysTotal['n'] ?> student<?= $boysTotal['n'] === 1 ? '' : 's' ?></div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="kpi-card">
        <div class="kpi-card__icon kpi-card__icon--blue"><i class="bi bi-check2-circle"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Boys — pass rate</div>
          <div class="kpi-card__value"><?= $fmtPct($boysTotal['passPct']) ?></div>
          <div class="kpi-card__delta kpi-card__delta--flat"><?= (int) $boysTotal['passed'] ?> of <?= (int) $boysTotal['n'] ?> passed</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="kpi-card">
        <div class="kpi-card__icon kpi-card__icon--pink"><i class="bi bi-gender-female"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Girls — average</div>
          <div class="kpi-card__value"><?= $fmtPct($girlsTotal['avg']) ?></div>
          <div class="kpi-card__delta kpi-card__delta--flat"><?= (int) $girlsTotal['n'] ?> student<?= $girlsTotal['n'] === 1 ? '' : 's' ?></div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="kpi-card">
        <div class="kpi-card__icon kpi-card__icon--pink"><i class="bi bi-check2-circle"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Girls — pass rate</div>
          <div class="kpi-card__value"><?= $fmtPct($girlsTotal['passPct']) ?></div>
          <div class="kpi-card__delta kpi-card__delta--flat"><?= (int) $girlsTotal['passed'] ?> of <?= (int) $girlsTotal['n'] ?> passed</div>
        </div>
      </div>
    </div>
  </div>

  <?php if ($gap !== null): ?>
    <p class="small text-muted d-print-none mb-4">
      <i class="bi bi-<?= $gap > 0 ? 'arrow-up-right' : ($gap < 0 ? 'arrow-down-right' : 'dash-lg') ?>"></i>
      Girls average <?= number_format(abs($gap), 1) ?> percentage point<?= abs($gap) == 1 ? '' : 's' ?>
      <?= $gap > 0 ? 'higher' : ($gap < 0 ? 'lower' : 'the same') ?> than boys overall.
    </p>
  <?php endif; ?>

  <?php if ($otherCount > 0): ?>
    <p class="small text-muted d-print-none mb-4">
      <i class="bi bi-info-circle"></i> <?= (int) $otherCount ?> student<?= $otherCount === 1 ? '' : 's' ?> with another gender recorded
      <?= $otherCount === 1 ? 'is' : 'are' ?> excluded from this boys/girls comparison.
    </p>
  <?php endif; ?>

  <div class="chart-surface chart-surface--gender-class mb-4 d-print-none">
    <canvas id="genderClassChart" role="img"
            aria-label="Average percentage by class, boys versus girls"></canvas>
  </div>

  <?php if (!empty($bySchool)): ?>
    <h5 class="mb-2"><i class="bi bi-buildings"></i> By school</h5>
    <div class="results-table-panel mb-4">
      <div class="table-responsive">
        <table class="table table-sm align-middle bg-white results-density-table mb-0">
          <thead class="table-light">
            <tr>
              <th rowspan="2" class="align-middle">School</th>
              <th colspan="3" class="text-center">Boys</th>
              <th colspan="3" class="text-center">Girls</th>
              <th rowspan="2" class="align-middle text-center">Gap</th>
            </tr>
            <tr>
              <th class="text-center">Students</th>
              <th class="text-center">Avg %</th>
              <th class="text-center">Pass %</th>
              <th class="text-center">Students</th>
              <th class="text-center">Avg %</th>
              <th class="text-center">Pass %</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($bySchool as $row):
              $b = $row['male']; $g = $row['female'];
              $rowGap = ($b['avg'] !== null && $g['avg'] !== null) ? $g['avg'] - $b['avg'] : null;
            ?>
              <tr>
                <td><?= View::e($row['name']) ?></td>
                <td class="text-center"><?= (int) $b['n'] ?></td>
                <td class="text-center"><?= $fmtPct($b['avg']) ?></td>
                <td class="text-center"><?= $fmtPct($b['passPct']) ?></td>
                <td class="text-center"><?= (int) $g['n'] ?></td>
                <td class="text-center"><?= $fmtPct($g['avg']) ?></td>
                <td class="text-center"><?= $fmtPct($g['passPct']) ?></td>
                <td class="text-center">
                  <?php if ($rowGap === null): ?>—<?php else: ?>
                    <i class="bi bi-<?= $rowGap > 0 ? 'arrow-up-right' : ($rowGap < 0 ? 'arrow-down-right' : 'dash-lg') ?>"></i>
                    <?= number_format(abs($rowGap), 1) ?>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <h5 class="mb-2"><i class="bi bi-mortarboard"></i> By class</h5>
  <div class="results-table-panel">
    <div class="table-responsive">
      <table class="table table-sm align-middle bg-white results-density-table mb-0">
        <thead class="table-light">
          <tr>
            <th rowspan="2" class="align-middle">Class</th>
            <th colspan="3" class="text-center">Boys</th>
            <th colspan="3" class="text-center">Girls</th>
            <th rowspan="2" class="align-middle text-center">Gap</th>
          </tr>
          <tr>
            <th class="text-center">Students</th>
            <th class="text-center">Avg %</th>
            <th class="text-center">Pass %</th>
            <th class="text-center">Students</th>
            <th class="text-center">Avg %</th>
            <th class="text-center">Pass %</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($byClass as $classId => $row):
            $b = $row['male']; $g = $row['female'];
            $rowGap = ($b['avg'] !== null && $g['avg'] !== null) ? $g['avg'] - $b['avg'] : null;
          ?>
            <tr>
              <td>
                <a href="<?= $base ?><?= $portalPrefix ?>/results/class/<?= (int) $classId ?>?year=<?= rawurlencode($year) ?>&term=<?= rawurlencode($term) ?>">
                  <?= View::e($row['name']) ?>
                </a>
                <?php if (!empty($row['level'])): ?>
                  <span class="text-muted small ms-1"><?= View::e($row['level']) ?></span>
                <?php endif; ?>
              </td>
              <td class="text-center"><?= (int) $b['n'] ?></td>
              <td class="text-center"><?= $fmtPct($b['avg']) ?></td>
              <td class="text-center"><?= $fmtPct($b['passPct']) ?></td>
              <td class="text-center"><?= (int) $g['n'] ?></td>
              <td class="text-center"><?= $fmtPct($g['avg']) ?></td>
              <td class="text-center"><?= $fmtPct($g['passPct']) ?></td>
              <td class="text-center">
                <?php if ($rowGap === null): ?>—<?php else: ?>
                  <i class="bi bi-<?= $rowGap > 0 ? 'arrow-up-right' : ($rowGap < 0 ? 'arrow-down-right' : 'dash-lg') ?>"></i>
                  <?= number_format(abs($rowGap), 1) ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" defer></script>
  <script>
  (function () {
    <?php $classList = array_values($byClass); ?>
    var labels = <?= json_encode(array_map(static fn ($r) => $r['name'], $classList)) ?>;
    var boysAvg  = <?= json_encode(array_map(static fn ($r) => $r['male']['avg'],   $classList)) ?>;
    var girlsAvg = <?= json_encode(array_map(static fn ($r) => $r['female']['avg'], $classList)) ?>;

    var chart = null;
    function build() {
      var el = document.getElementById('genderClassChart');
      if (!el || !window.Chart || labels.length === 0) return;
      if (chart) { chart.destroy(); }

      var styles = getComputedStyle(document.body);
      var gridColor = styles.getPropertyValue('--border') || '#e5e7eb';
      var textColor = styles.getPropertyValue('--text-muted') || '#6b7280';

      chart = new Chart(el.getContext('2d'), {
        type: 'bar',
        data: {
          labels: labels,
          datasets: [
            { label: 'Boys avg %',  data: boysAvg,  backgroundColor: '#3b82f6', borderRadius: 4, maxBarThickness: 28 },
            { label: 'Girls avg %', data: girlsAvg, backgroundColor: '#ec4899', borderRadius: 4, maxBarThickness: 28 }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: { beginAtZero: true, max: 100, grid: { color: gridColor }, ticks: { color: textColor } },
            x: { grid: { display: false }, ticks: { color: textColor } }
          },
          plugins: {
            legend: { position: 'top', labels: { color: textColor } },
            tooltip: {
              callbacks: {
                label: function (ctx) {
                  var v = ctx.parsed.y;
                  return ctx.dataset.label + ': ' + (v === null ? '—' : v.toFixed(1) + '%');
                }
              }
            }
          }
        }
      });
    }

    function whenChartReady(cb, tries) {
      tries = tries || 0;
      if (window.Chart) { cb(); return; }
      if (tries > 40) return;
      setTimeout(function () { whenChartReady(cb, tries + 1); }, 50);
    }

    document.addEventListener('DOMContentLoaded', function () {
      whenChartReady(build);

      // Re-render on theme toggle (data-bs-theme flips on <html>)
      var obs = new MutationObserver(function (mutations) {
        for (var i = 0; i < mutations.length; i++) {
          if (mutations[i].attributeName === 'data-bs-theme') {
            whenChartReady(build);
            break;
          }
        }
      });
      obs.observe(document.documentElement, { attributes: true });
    });

    window.addEventListener('resize', function () {
      clearTimeout(window.__genderChartResize);
      window.__genderChartResize = setTimeout(build, 150);
    });
  })();
  </script>

  <?php endif; ?>
</div>
