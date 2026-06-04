<?php
use App\Core\View;

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

// ---- Chart datasets ------------------------------------------------------
// Collection by class (grouped bar): expected vs collected vs outstanding.
$byLevel     = $byLevel     ?? [];
$byTerm      = $byTerm      ?? [];
$monthlyTrend = $monthlyTrend ?? [];

$classLabels      = array_map(fn($r) => (string) $r['level'], $byLevel);
$classExpected    = array_map(fn($r) => round((float) $r['expected'], 2), $byLevel);
$classCollected   = array_map(fn($r) => round((float) $r['collected'], 2), $byLevel);
$classOutstanding = array_map(fn($r) => round((float) $r['outstanding'], 2), $byLevel);

// Per-term comparison (bar): expected vs collected by term.
$termLabels    = array_map(fn($r) => (string) $r['term'], $byTerm);
$termExpected  = array_map(fn($r) => round((float) $r['expected'], 2), $byTerm);
$termCollected = array_map(fn($r) => round((float) $r['collected'], 2), $byTerm);

// Monthly collection trend (line/area): label months human-readably.
$trendLabels = array_map(function ($r) {
    $ts = strtotime(((string) $r['ym']) . '-01');
    return $ts ? date('M Y', $ts) : (string) $r['ym'];
}, $monthlyTrend);
$trendTotals = array_map(fn($r) => round((float) $r['total'], 2), $monthlyTrend);
$trendCounts = array_map(fn($r) => (int) $r['cnt'], $monthlyTrend);

// Payment status mix (doughnut).
$statusLabels = ['Paid', 'Partial', 'Not paid'];
$statusCounts = [$paidCount, $partialCount, $unpaidCount];

$hasClassChart  = count($classLabels) > 0 && (array_sum($classExpected) > 0 || array_sum($classCollected) > 0);
$hasTrendChart  = count($trendLabels) > 0 && array_sum($trendTotals) > 0;
$hasTermChart   = count($termLabels) > 0 && (array_sum($termExpected) > 0 || array_sum($termCollected) > 0);
$hasStatusChart = ($paidCount + $partialCount + $unpaidCount) > 0;
$hasAnyChart    = $hasClassChart || $hasTrendChart || $hasTermChart || $hasStatusChart;

$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
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

  /* ----- Analytics charts ----- */
  .bursar-dash .bursar-chart-card .card-body { display: flex; flex-direction: column; }
  .bursar-dash .chart-surface {
    position: relative;
    width: 100%;
    flex: 1 1 auto;
  }
  .bursar-dash .chart-surface--tall   { height: 280px; }
  .bursar-dash .chart-surface--medium { height: 240px; }
  .bursar-dash .chart-surface--donut  { height: 220px; }
  .bursar-dash .chart-surface canvas { max-width: 100%; }
  .bursar-dash .chart-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
  }
  .bursar-dash .chart-head__title { font-weight: 700; font-size: 0.95rem; margin: 0; }
  .bursar-dash .chart-head__sub { font-size: 0.78rem; color: var(--bs-secondary-color); margin: 0; }
  .bursar-dash .chart-badge {
    font-size: 0.72rem;
    font-weight: 600;
    padding: 0.25rem 0.6rem;
    border-radius: 999px;
    background: var(--accent-soft, #e3f2fd);
    color: var(--accent, #1e88e5);
    white-space: nowrap;
  }
  .bursar-dash .chart-empty {
    display: grid;
    place-items: center;
    height: 100%;
    color: var(--bs-secondary-color);
    font-size: 0.85rem;
    text-align: center;
  }
  .bursar-dash .chart-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem 1rem;
    margin-top: 0.75rem;
    padding: 0;
    list-style: none;
  }
  .bursar-dash .chart-legend li { display: flex; align-items: center; gap: 0.4rem; font-size: 0.78rem; }
  .bursar-dash .chart-legend__dot { width: 0.7rem; height: 0.7rem; border-radius: 3px; flex-shrink: 0; }
</style>

<div class="bursar-dash portal-dash d-flex flex-column gap-3">

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

  <?php if ($hasAnyChart): ?>
  <!-- ============================================================
       Statistical analysis — charts (bar · line · doughnut)
       ============================================================ -->
  <div class="row g-2 align-items-stretch">

    <!-- Monthly collection trend (line / area) -->
    <div class="col-12 col-xl-8">
      <div class="card bursar-chart-card h-100 border-0 shadow-sm">
        <div class="card-body">
          <div class="chart-head">
            <div>
              <h3 class="chart-head__title"><i class="bi bi-graph-up-arrow text-primary me-1"></i> Collection trend</h3>
              <p class="chart-head__sub">Total amount collected per month · AY <?= View::e($year) ?></p>
            </div>
            <span class="chart-badge"><i class="bi bi-cash-stack"></i> <?= number_format(array_sum($trendTotals), 2) ?></span>
          </div>
          <div class="chart-surface chart-surface--tall">
            <?php if ($hasTrendChart): ?>
              <canvas id="bursarTrendChart" role="img" aria-label="Monthly collection trend"></canvas>
            <?php else: ?>
              <div class="chart-empty">
                <div>
                  <i class="bi bi-graph-up d-block fs-3 mb-2"></i>
                  No payments recorded for this academic year yet.
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Payment status mix (doughnut) -->
    <div class="col-12 col-xl-4">
      <div class="card bursar-chart-card h-100 border-0 shadow-sm">
        <div class="card-body">
          <div class="chart-head">
            <div>
              <h3 class="chart-head__title"><i class="bi bi-pie-chart-fill text-primary me-1"></i> Payment status</h3>
              <p class="chart-head__sub"><?= number_format($students) ?> students · <?= View::e($term) ?></p>
            </div>
          </div>
          <div class="chart-surface chart-surface--donut">
            <?php if ($hasStatusChart): ?>
              <canvas id="bursarStatusChart" role="img" aria-label="Payment status distribution"></canvas>
            <?php else: ?>
              <div class="chart-empty">No status data yet.</div>
            <?php endif; ?>
          </div>
          <?php if ($hasStatusChart): ?>
          <ul class="chart-legend">
            <li><span class="chart-legend__dot" style="background:#2ecc71"></span>Paid · <?= number_format($paidCount) ?></li>
            <li><span class="chart-legend__dot" style="background:#f39c12"></span>Partial · <?= number_format($partialCount) ?></li>
            <li><span class="chart-legend__dot" style="background:#e74c3c"></span>Not paid · <?= number_format($unpaidCount) ?></li>
          </ul>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Expected vs collected by class (grouped bar) -->
    <div class="col-12 col-lg-6 col-xl-4">
      <div class="card bursar-chart-card h-100 border-0 shadow-sm">
        <div class="card-body">
          <div class="chart-head">
            <div>
              <h3 class="chart-head__title"><i class="bi bi-bar-chart-fill text-primary me-1"></i> By class</h3>
              <p class="chart-head__sub">Expected vs collected · <?= View::e($term) ?></p>
            </div>
          </div>
          <div class="chart-surface chart-surface--medium">
            <?php if ($hasClassChart): ?>
              <canvas id="bursarClassChart" role="img" aria-label="Expected versus collected by class"></canvas>
            <?php else: ?>
              <div class="chart-empty">No fees assigned yet.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Term comparison (bar) -->
    <div class="col-12 col-lg-6 col-xl-4">
      <div class="card bursar-chart-card h-100 border-0 shadow-sm">
        <div class="card-body">
          <div class="chart-head">
            <div>
              <h3 class="chart-head__title"><i class="bi bi-bookmark-star-fill text-primary me-1"></i> By term</h3>
              <p class="chart-head__sub">Expected vs collected · AY <?= View::e($year) ?></p>
            </div>
          </div>
          <div class="chart-surface chart-surface--medium">
            <?php if ($hasTermChart): ?>
              <canvas id="bursarTermChart" role="img" aria-label="Collection by term"></canvas>
            <?php else: ?>
              <div class="chart-empty">No term data yet.</div>
            <?php endif; ?>
          </div>
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

  </div>
  <?php endif; ?>

</div>

<?php if ($hasAnyChart): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" defer></script>
<script>
  // Theme-aware Chart.js setup for the bursar dashboard. Reads CSS vars at
  // draw time so charts follow the active light/dark theme and re-render on
  // theme toggle (mirrors the admin overview behaviour).
  (function () {
    'use strict';

    var data = {
      trend: {
        labels: <?= json_encode($trendLabels, $jsonFlags) ?>,
        totals: <?= json_encode($trendTotals, $jsonFlags) ?>,
        counts: <?= json_encode($trendCounts, $jsonFlags) ?>
      },
      cls: {
        labels:      <?= json_encode($classLabels, $jsonFlags) ?>,
        expected:    <?= json_encode($classExpected, $jsonFlags) ?>,
        collected:   <?= json_encode($classCollected, $jsonFlags) ?>,
        outstanding: <?= json_encode($classOutstanding, $jsonFlags) ?>
      },
      term: {
        labels:    <?= json_encode($termLabels, $jsonFlags) ?>,
        expected:  <?= json_encode($termExpected, $jsonFlags) ?>,
        collected: <?= json_encode($termCollected, $jsonFlags) ?>
      },
      status: {
        labels: <?= json_encode($statusLabels, $jsonFlags) ?>,
        counts: <?= json_encode($statusCounts, $jsonFlags) ?>
      }
    };

    var charts = {};

    // Bar chart palette — blue (expected), green (collected), red (outstanding)
    var barColors = {
      blue:   { fill: '#1e88e5', hover: '#1565c0' },
      green:  { fill: '#22c55e', hover: '#16a34a' },
      red:    { fill: '#ef4444', hover: '#dc2626' }
    };

    function barDataset(label, values, colorKey) {
      var c = barColors[colorKey];
      return {
        label: label,
        data: values,
        backgroundColor: c.fill,
        hoverBackgroundColor: c.hover,
        borderRadius: 8,
        borderSkipped: false,
        maxBarThickness: 32
      };
    }

    function readTheme() {
      var css = getComputedStyle(document.documentElement);
      function v(name, fallback) {
        var raw = css.getPropertyValue(name);
        return (raw && raw.trim()) || fallback;
      }
      var accentRgb = v('--accent-rgb', '30, 136, 229');
      return {
        accent:       v('--accent', '#1e88e5'),
        accentRgb:    accentRgb,
        accentFill:   'rgba(' + accentRgb + ', 0.15)',
        accentLine:   'rgba(' + accentRgb + ', 1)',
        border:       v('--border', '#e2e8f0'),
        muted:        v('--text-muted', '#64748b'),
        text:         v('--text', '#0f172a'),
        surface:      v('--surface', '#ffffff'),
        chartFont:    v('--bs-body-font-family', 'system-ui, sans-serif')
      };
    }

    function money(n) {
      return Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function baseTooltip(theme) {
      return {
        backgroundColor: theme.surface,
        titleColor: theme.text,
        bodyColor: theme.muted,
        borderColor: theme.border,
        borderWidth: 1,
        padding: 10,
        displayColors: true
      };
    }

    function gridScales(theme, opts) {
      opts = opts || {};
      return {
        x: {
          stacked: !!opts.stacked,
          grid: { display: false, drawBorder: false },
          ticks: { color: theme.muted, font: { family: theme.chartFont, size: 11 } }
        },
        y: {
          stacked: !!opts.stacked,
          beginAtZero: true,
          grid: { color: theme.border, drawBorder: false },
          ticks: {
            color: theme.muted,
            font: { family: theme.chartFont, size: 11 },
            callback: function (val) {
              if (val >= 1000000) return (val / 1000000) + 'M';
              if (val >= 1000) return (val / 1000) + 'k';
              return val;
            }
          }
        }
      };
    }

    function buildTrend(theme) {
      var el = document.getElementById('bursarTrendChart');
      if (!el) return;
      var ctx = el.getContext('2d');
      var grad = ctx.createLinearGradient(0, 0, 0, el.offsetHeight || 280);
      grad.addColorStop(0, 'rgba(' + theme.accentRgb + ', 0.28)');
      grad.addColorStop(1, 'rgba(' + theme.accentRgb + ', 0.01)');

      charts.trend = new Chart(ctx, {
        type: 'line',
        data: {
          labels: data.trend.labels,
          datasets: [{
            label: 'Collected',
            data: data.trend.totals,
            borderColor: theme.accentLine,
            backgroundColor: grad,
            borderWidth: 2.5,
            fill: true,
            tension: 0.4,
            pointRadius: 3,
            pointHoverRadius: 5,
            pointBackgroundColor: theme.accentLine,
            pointBorderColor: theme.surface,
            pointBorderWidth: 2
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: Object.assign(baseTooltip(theme), {
              displayColors: false,
              callbacks: {
                label: function (c) {
                  var i = c.dataIndex;
                  var cnt = data.trend.counts[i] || 0;
                  return [money(c.parsed.y) + ' collected', cnt + (cnt === 1 ? ' payment' : ' payments')];
                }
              }
            })
          },
          scales: gridScales(theme)
        }
      });
    }

    function buildClass(theme) {
      var el = document.getElementById('bursarClassChart');
      if (!el) return;
      charts.cls = new Chart(el.getContext('2d'), {
        type: 'bar',
        data: {
          labels: data.cls.labels,
          datasets: [
            barDataset('Expected',    data.cls.expected,    'blue'),
            barDataset('Collected',   data.cls.collected,   'green'),
            barDataset('Outstanding', data.cls.outstanding, 'red')
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { position: 'bottom', labels: { color: theme.muted, font: { family: theme.chartFont, size: 11 }, usePointStyle: true, pointStyle: 'rectRounded', boxWidth: 8, padding: 12 } },
            tooltip: Object.assign(baseTooltip(theme), {
              callbacks: { label: function (c) { return ' ' + c.dataset.label + ': ' + money(c.parsed.y); } }
            })
          },
          scales: gridScales(theme)
        }
      });
    }

    function buildTerm(theme) {
      var el = document.getElementById('bursarTermChart');
      if (!el) return;
      charts.term = new Chart(el.getContext('2d'), {
        type: 'bar',
        data: {
          labels: data.term.labels,
          datasets: [
            barDataset('Expected',  data.term.expected,  'blue'),
            barDataset('Collected', data.term.collected, 'green')
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { position: 'bottom', labels: { color: theme.muted, font: { family: theme.chartFont, size: 11 }, usePointStyle: true, pointStyle: 'rectRounded', boxWidth: 8, padding: 12 } },
            tooltip: Object.assign(baseTooltip(theme), {
              callbacks: { label: function (c) { return ' ' + c.dataset.label + ': ' + money(c.parsed.y); } }
            })
          },
          scales: gridScales(theme)
        }
      });
    }

    function buildStatus(theme) {
      var el = document.getElementById('bursarStatusChart');
      if (!el) return;
      charts.status = new Chart(el.getContext('2d'), {
        type: 'doughnut',
        data: {
          labels: data.status.labels,
          datasets: [{
            data: data.status.counts,
            backgroundColor: ['#2ecc71', '#f39c12', '#e74c3c'],
            borderColor: theme.surface,
            borderWidth: 3,
            hoverOffset: 6
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '64%',
          plugins: {
            legend: { display: false },
            tooltip: Object.assign(baseTooltip(theme), {
              callbacks: {
                label: function (c) {
                  var total = c.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                  var pct = total ? Math.round((c.parsed / total) * 100) : 0;
                  return ' ' + c.label + ': ' + c.parsed + ' (' + pct + '%)';
                }
              }
            })
          }
        }
      });
    }

    function renderAll() {
      if (!window.Chart) return;
      Object.keys(charts).forEach(function (k) { if (charts[k]) { charts[k].destroy(); charts[k] = null; } });
      var theme = readTheme();
      buildTrend(theme);
      buildClass(theme);
      buildTerm(theme);
      buildStatus(theme);
    }

    function whenReady(cb) {
      if (window.Chart) { cb(); return; }
      var tries = 0;
      var poll = setInterval(function () {
        if (window.Chart || tries++ > 60) { clearInterval(poll); if (window.Chart) cb(); }
      }, 50);
    }

    document.addEventListener('DOMContentLoaded', function () {
      whenReady(renderAll);
      var obs = new MutationObserver(function (m) {
        for (var i = 0; i < m.length; i++) {
          if (m[i].attributeName === 'data-bs-theme') { whenReady(renderAll); break; }
        }
      });
      obs.observe(document.documentElement, { attributes: true });
    });
  })();
</script>
<?php endif; ?>
