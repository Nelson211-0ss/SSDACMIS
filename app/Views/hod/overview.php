<?php
use App\Core\View;

$layout = 'app';
$title  = 'Performance overview';

$catLabel = ['core' => 'Compulsory Core', 'science' => 'Science', 'arts' => 'Arts', 'optional' => 'Optional'];
$hasMarks = $gradeCount > 0;
?>

<section class="hod-overview-hero">
  <div class="hod-overview-hero__mesh" aria-hidden="true"></div>
  <div class="hod-overview-hero__inner row g-4 align-items-center">
    <div class="col-lg-7">
      <div class="hod-overview-hero__eyebrow">
        <i class="bi bi-activity"></i> Analytics
      </div>
      <h1 class="hod-overview-hero__title">Student performance overview</h1>
      <p class="hod-overview-hero__lead">
        Live snapshots for your department subjects: averages, spread, and class trends
        for <strong><?= View::e($year) ?></strong> &middot; <strong><?= View::e($term) ?></strong>.
      </p>
      <div class="hod-overview-hero__chips">
        <?php if (!empty($isSharedHod) && !empty($hodDepartmentLabel)): ?>
          <span class="hod-overview-chip hod-overview-chip--accent">
            <i class="bi bi-bookmark-star-fill"></i> <?= View::e($hodDepartmentLabel) ?>
          </span>
        <?php endif; ?>
        <?php foreach ($categories as $cat): ?>
          <span class="hod-overview-chip hod-overview-chip--<?= View::e($cat) ?>">
            <?= View::e($catLabel[$cat] ?? $cat) ?>
          </span>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="hod-overview-hero__panel">
        <div class="hod-overview-hero__stat-row">
          <span class="text-muted small">Department mean</span>
          <span class="hod-overview-hero__stat-num">
            <?= $avgOverall !== null ? View::e(rtrim(rtrim(number_format($avgOverall, 2, '.', ''), '0'), '.')) : '—' ?>
          </span>
        </div>
        <div class="hod-overview-hero__stat-row">
          <span class="text-muted small">Marks in view</span>
          <span class="hod-overview-hero__stat-num"><?= number_format($gradeCount) ?></span>
        </div>
        <div class="hod-overview-hero__stat-row">
          <span class="text-muted small">Students with marks</span>
          <span class="hod-overview-hero__stat-num"><?= number_format($studentsTouch) ?></span>
        </div>
        <a href="<?= $base ?>/hod" class="btn btn-light btn-sm w-100 mt-2">
          <i class="bi bi-mortarboard"></i> Open department dashboard
        </a>
      </div>
    </div>
  </div>
</section>

<form method="get"
      action="<?= $base ?>/hod/overview"
      class="period-bar <?= $periodSet ? 'period-bar--ok' : 'period-bar--warn' ?> mt-4 mb-4">

  <span class="period-bar__icon">
    <i class="bi bi-calendar-event"></i>
  </span>

  <div class="period-bar__lead">
    <div class="period-bar__title">Reporting period</div>
    <div class="period-bar__hint">
      <?= $periodSet
        ? 'Charts and metrics use this year and term.'
        : 'Showing sensible defaults — confirm year and term to match your reporting cycle.' ?>
    </div>
  </div>

  <div class="period-bar__field">
    <label class="form-label small mb-1">Academic year</label>
    <select name="year" class="form-select form-select-sm" required>
      <option value="">— Year —</option>
      <?php foreach ($availableYears as $y): ?>
        <option value="<?= View::e($y) ?>" <?= $y === $selYear ? 'selected' : '' ?>>
          <?= View::e($y) ?><?= $y === $defaultYear ? ' (current)' : '' ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="period-bar__field">
    <label class="form-label small mb-1">Term</label>
    <select name="term" class="form-select form-select-sm" required>
      <option value="">— Term —</option>
      <?php foreach ($availableTerms as $t): ?>
        <option value="<?= View::e($t) ?>" <?= $t === $selTerm ? 'selected' : '' ?>>
          <?= View::e($t) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <button type="submit" class="btn btn-primary btn-sm">
    <i class="bi bi-check2-circle"></i>
    Apply
  </button>

  <span class="period-bar__status">
    <i class="bi bi-graph-up-arrow"></i>
    <span>
      <strong><?= View::e($year) ?></strong> &middot; <?= View::e($term) ?>
      <?php if (!$periodSet): ?>
        <span class="text-muted">(default)</span>
      <?php endif; ?>
    </span>
  </span>
</form>

<div class="row g-3 mb-4">
  <?php
    $kpis = [
      ['Overall average', $avgOverall !== null ? rtrim(rtrim(number_format($avgOverall, 2, '.', ''), '0'), '.') : '—', 'bi-bullseye', 'purple', 'Mean score, department subjects'],
      ['Marks recorded', number_format($gradeCount), 'bi-clipboard-data', 'blue', 'Rows in this period'],
      ['Students assessed', number_format($studentsTouch), 'bi-people-fill', 'green', 'Unique learners with ≥1 mark'],
      ['Subjects tracked', number_format($subjectsOffered), 'bi-book-half', 'orange', 'Offered in your categories'],
    ];
    foreach ($kpis as [$label, $value, $icon, $tone, $hint]):
  ?>
    <div class="col-6 col-xl-3">
      <div class="hod-overview-kpi">
        <div class="hod-overview-kpi__icon hod-overview-kpi__icon--<?= View::e($tone) ?>">
          <i class="bi <?= View::e($icon) ?>"></i>
        </div>
        <div class="hod-overview-kpi__label"><?= View::e($label) ?></div>
        <div class="hod-overview-kpi__value"><?= View::e((string) $value) ?></div>
        <div class="hod-overview-kpi__hint"><?= View::e($hint) ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php if (!$hasMarks): ?>
  <div class="hod-overview-empty card border-0 shadow-sm">
    <div class="card-body text-center py-5 px-4">
      <div class="hod-overview-empty__icon">
        <i class="bi bi-bar-chart-line"></i>
      </div>
      <h2 class="h5 mt-3 mb-2">No marks for this period yet</h2>
      <p class="text-muted mb-4 col-lg-8 mx-auto">
        Once teachers capture marks for your department subjects, averages, grade spread,
        and class comparisons will appear here automatically.
      </p>
      <div class="d-flex flex-wrap justify-content-center gap-2">
        <a href="<?= $base ?>/hod/marks" class="btn btn-primary">
          <i class="bi bi-pencil-square"></i> Department marks
        </a>
        <a href="<?= $base ?>/hod" class="btn btn-outline-secondary">
          <i class="bi bi-arrow-left"></i> Dashboard
        </a>
      </div>
    </div>
  </div>
<?php else: ?>

  <div class="row g-4 mb-2">
    <div class="col-xl-7">
      <div class="hod-overview-card h-100">
        <div class="hod-overview-card__head">
          <div>
            <h2 class="hod-overview-card__title">
              <i class="bi bi-journal-text text-primary"></i> Average by subject
            </h2>
            <p class="hod-overview-card__sub">Mean score for each department subject in this term.</p>
          </div>
        </div>
        <div class="hod-overview-card__body chart-surface" style="height:min(420px, 55vh);">
          <canvas id="hodSubjectAvgChart" aria-label="Bar chart of average scores by subject"></canvas>
        </div>
      </div>
    </div>
    <div class="col-xl-5">
      <div class="hod-overview-card h-100">
        <div class="hod-overview-card__head">
          <div>
            <h2 class="hod-overview-card__title">
              <i class="bi bi-pie-chart-fill text-warning"></i> Grade spread
            </h2>
            <p class="hod-overview-card__sub">How marks cluster across performance bands.</p>
          </div>
        </div>
        <div class="hod-overview-card__body chart-surface" style="height:min(360px, 45vh);">
          <canvas id="hodBandChart" aria-label="Doughnut chart of grade distribution"></canvas>
        </div>
        <?php if ($bandTotal > 0): ?>
          <div class="hod-overview-card__footer small text-muted">
            Total marks in bands: <strong><?= number_format($bandTotal) ?></strong>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-xl-8">
      <div class="hod-overview-card h-100">
        <div class="hod-overview-card__head">
          <div>
            <h2 class="hod-overview-card__title">
              <i class="bi bi-building text-info"></i> Average by class
            </h2>
            <p class="hod-overview-card__sub">Cross-class comparison for the same department scope.</p>
          </div>
        </div>
        <div class="hod-overview-card__body chart-surface" style="height:min(340px, 50vh);">
          <canvas id="hodClassAvgChart" aria-label="Bar chart of average scores by class"></canvas>
        </div>
      </div>
    </div>
    <div class="col-xl-4">
      <div class="hod-overview-card h-100">
        <div class="hod-overview-card__head">
          <div>
            <h2 class="hod-overview-card__title">
              <i class="bi bi-lightning-charge text-success"></i> Exam focus
            </h2>
            <p class="hod-overview-card__sub">Mid-term vs end-of-term averages.</p>
          </div>
        </div>
        <div class="hod-overview-card__body">
          <div class="hod-exam-compare">
            <div class="hod-exam-compare__item hod-exam-compare__item--mid">
              <span class="hod-exam-compare__label">Mid-term</span>
              <span class="hod-exam-compare__val">
                <?= $midAvg !== null ? View::e(rtrim(rtrim(number_format($midAvg, 2, '.', ''), '0'), '.')) : '—' ?>
              </span>
            </div>
            <div class="hod-exam-compare__item hod-exam-compare__item--end">
              <span class="hod-exam-compare__label">End of term</span>
              <span class="hod-exam-compare__val">
                <?= $endAvg !== null ? View::e(rtrim(rtrim(number_format($endAvg, 2, '.', ''), '0'), '.')) : '—' ?>
              </span>
            </div>
          </div>
          <p class="small text-muted mt-3 mb-0">
            When only one exam type has entries, the other shows &ldquo;—&rdquo;. Encourage entry for both to compare focus areas.
          </p>
        </div>
      </div>
    </div>
  </div>

  <?php if (!empty($subjectRows)): ?>
    <div class="hod-overview-card mb-4">
      <div class="hod-overview-card__head">
        <div>
          <h2 class="hod-overview-card__title">
            <i class="bi bi-table"></i> Subject detail
          </h2>
          <p class="hod-overview-card__sub">Sortable figures behind the charts.</p>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 hod-overview-table">
          <thead>
            <tr>
              <th>Subject</th>
              <th class="text-end">Average</th>
              <th class="text-end">Mark count</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($subjectRows as $sr):
              $avg = round((float) $sr['avg_score'], 2);
              $n   = (int) $sr['n'];
            ?>
              <tr>
                <td class="fw-medium"><?= View::e($sr['subject_name']) ?></td>
                <td class="text-end font-monospace"><?= View::e(rtrim(rtrim(number_format($avg, 2, '.', ''), '0'), '.')) ?></td>
                <td class="text-end text-muted"><?= number_format($n) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" defer></script>
<script>
  (function () {
    'use strict';

    var subjectPayload = <?= json_encode([
      'labels' => $chartSubjectLabels,
      'avgs'   => $chartSubjectAvgs,
      'counts' => $chartSubjectNs,
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    var classPayload = <?= json_encode([
      'labels' => $chartClassLabels,
      'avgs'   => $chartClassAvgs,
      'levels' => $chartClassMeta,
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    var bandPayload = <?= json_encode([
      'labels' => $bandLabels,
      'data'   => $bandData,
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    var charts = { subject: null, band: null, klass: null };

    function readTheme() {
      var css = getComputedStyle(document.documentElement);
      function v(name, fallback) {
        var raw = css.getPropertyValue(name);
        return (raw && raw.trim()) || fallback;
      }
      var accentRgb = v('--accent-rgb', '37, 99, 235');
      return {
        accent:       v('--accent', '#2563eb'),
        accentSoft:   'rgba(' + accentRgb + ', 0.85)',
        accentSofter: 'rgba(' + accentRgb + ', 0.25)',
        border:       v('--border', '#e7e9ee'),
        muted:        v('--text-muted', '#6b7280'),
        text:         v('--text', '#1f2937'),
        surface:      v('--surface', '#ffffff'),
      };
    }

    function destroyAll() {
      Object.keys(charts).forEach(function (k) {
        if (charts[k]) { charts[k].destroy(); charts[k] = null; }
      });
    }

    function buildSubject(theme) {
      var el = document.getElementById('hodSubjectAvgChart');
      if (!el || !window.Chart) return;
      charts.subject = new Chart(el, {
        type: 'bar',
        data: {
          labels: subjectPayload.labels,
          datasets: [{
            label: 'Average',
            data: subjectPayload.avgs,
            backgroundColor: theme.accentSoft,
            borderColor: theme.accent,
            borderWidth: 1,
            borderRadius: 12,
            borderSkipped: false,
            maxBarThickness: 48,
          }]
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              backgroundColor: theme.surface,
              titleColor: theme.text,
              bodyColor: theme.muted,
              borderColor: theme.border,
              borderWidth: 1,
              padding: 10,
              displayColors: false,
              callbacks: {
                label: function (ctx) {
                  var i = ctx.dataIndex;
                  var c = subjectPayload.counts[i] != null ? subjectPayload.counts[i] : '';
                  return 'Avg: ' + ctx.parsed.x + (c !== '' ? ' · ' + c + ' marks' : '');
                }
              }
            }
          },
          scales: {
            x: {
              min: 0,
              max: 100,
              grid: { color: theme.border },
              ticks: { color: theme.muted, font: { size: 11 } }
            },
            y: {
              grid: { display: false },
              ticks: { color: theme.muted, font: { size: 11 } }
            }
          }
        }
      });
    }

    function buildBand(theme) {
      var el = document.getElementById('hodBandChart');
      if (!el || !window.Chart) return;
      var palette = ['#22c55e', '#3b82f6', '#f59e0b', '#ef4444'];
      charts.band = new Chart(el, {
        type: 'doughnut',
        data: {
          labels: bandPayload.labels,
          datasets: [{
            data: bandPayload.data,
            backgroundColor: palette,
            borderColor: theme.surface,
            borderWidth: 3,
            hoverOffset: 10,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '62%',
          plugins: {
            legend: {
              position: 'bottom',
              labels: { color: theme.muted, boxWidth: 10, padding: 14 }
            },
            tooltip: {
              backgroundColor: theme.surface,
              titleColor: theme.text,
              bodyColor: theme.muted,
              borderColor: theme.border,
              borderWidth: 1,
              callbacks: {
                label: function (ctx) {
                  var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                  var v = ctx.parsed;
                  var pct = total ? Math.round((v / total) * 100) : 0;
                  return ' ' + v + ' marks (' + pct + '%)';
                }
              }
            }
          }
        }
      });
    }

    function buildClass(theme) {
      var el = document.getElementById('hodClassAvgChart');
      if (!el || !window.Chart) return;
      var barColors = ['#6366f1', '#14b8a6', '#ec4899', '#f97316', '#8b5cf6', '#0ea5e9'];
      var colors = classPayload.labels.map(function (_, i) {
        return barColors[i % barColors.length];
      });
      charts.klass = new Chart(el, {
        type: 'bar',
        data: {
          labels: classPayload.labels,
          datasets: [{
            label: 'Average',
            data: classPayload.avgs,
            backgroundColor: colors,
            borderRadius: 10,
            borderSkipped: false,
            maxBarThickness: 52,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              backgroundColor: theme.surface,
              titleColor: theme.text,
              bodyColor: theme.muted,
              borderColor: theme.border,
              borderWidth: 1,
              displayColors: false,
              callbacks: {
                title: function (items) {
                  var i = items[0].dataIndex;
                  var lvl = classPayload.levels[i];
                  return classPayload.labels[i] + (lvl ? ' · ' + lvl : '');
                },
                label: function (ctx) {
                  return 'Average: ' + ctx.parsed.y;
                }
              }
            }
          },
          scales: {
            y: {
              min: 0,
              max: 100,
              grid: { color: theme.border },
              ticks: { color: theme.muted, font: { size: 11 } }
            },
            x: {
              grid: { display: false },
              ticks: { color: theme.muted, font: { size: 11 } }
            }
          }
        }
      });
    }

    function renderAll() {
      if (!window.Chart) return;
      destroyAll();
      var theme = readTheme();
      buildSubject(theme);
      buildBand(theme);
      buildClass(theme);
    }

    function whenChartReady(cb) {
      if (window.Chart) { cb(); return; }
      var tries = 0;
      var poll = setInterval(function () {
        if (window.Chart || tries++ > 80) {
          clearInterval(poll);
          if (window.Chart) cb();
        }
      }, 50);
    }

    document.addEventListener('DOMContentLoaded', function () {
      whenChartReady(renderAll);
      var obs = new MutationObserver(function (mutations) {
        for (var i = 0; i < mutations.length; i++) {
          if (mutations[i].attributeName === 'data-bs-theme') {
            whenChartReady(renderAll);
            break;
          }
        }
      });
      obs.observe(document.documentElement, { attributes: true });
    });
  })();
</script>
<?php endif; ?>
