<?php
use App\Core\View;
$layout = 'app';
$title = 'Performance by class and gender';

$fmtPct = static fn (?float $v): string => $v === null ? '—' : number_format($v, 1) . '%';

$stage      = $stage ?? 'endterm';
$stageLabel = $stageLabel ?? 'End of term';
$passMark   = (float) ($passMark ?? 50);
$gradeLabels = $gradeLabels ?? [];
$qs = 'year=' . rawurlencode($year) . '&term=' . rawurlencode($term) . '&stage=' . rawurlencode($stage);

$boysTotal  = $totals['male'];
$girlsTotal = $totals['female'];
$gap = ($boysTotal['avg'] !== null && $girlsTotal['avg'] !== null)
    ? $girlsTotal['avg'] - $boysTotal['avg']
    : null;

$groups = ['male' => 'Boys', 'female' => 'Girls', 'all' => 'Total'];

/**
 * One Boys / Girls / Total row. $enrolled is null where enrolment isn't
 * known (per-school rows), and those columns then show a dash.
 */
$statRow = static function (string $group, array $s, ?int $enrolled) use ($fmtPct, $gradeLabels): string {
    $notSat = $enrolled === null ? null : max(0, $enrolled - (int) $s['n']);
    $cells = [
        $enrolled === null ? '—' : (string) $enrolled,
        (string) (int) $s['n'],
        $notSat === null ? '—' : ($notSat > 0 ? '<span class="text-danger">' . $notSat . '</span>' : '0'),
        '<strong>' . $fmtPct($s['avg']) . '</strong>',
        $fmtPct($s['max']),
        $fmtPct($s['min']),
        (string) (int) $s['passed'],
        (string) (int) ($s['n'] - $s['passed']),
        $fmtPct($s['passPct']),
    ];
    foreach ($gradeLabels as $gl) {
        $cells[] = (string) (int) ($s['grades'][$gl] ?? 0);
    }
    $html = '<td class="perf-group perf-group--' . $group . '">' . ['male' => 'Boys', 'female' => 'Girls', 'all' => 'Total'][$group] . '</td>';
    foreach ($cells as $c) {
        $html .= '<td class="text-center">' . $c . '</td>';
    }

    return $html;
};

$tableHead = static function (string $firstCol) use ($gradeLabels, $passMark): string {
    $h = '<thead class="table-light"><tr>'
       . '<th rowspan="2" class="align-middle">' . $firstCol . '</th>'
       . '<th rowspan="2" class="align-middle"></th>'
       . '<th colspan="3" class="text-center">Students</th>'
       . '<th colspan="3" class="text-center">Average mark</th>'
       . '<th colspan="3" class="text-center">Pass (' . (int) $passMark . '%+)</th>';
    if ($gradeLabels) {
        $h .= '<th colspan="' . count($gradeLabels) . '" class="text-center">Grades</th>';
    }
    $h .= '</tr><tr>'
        . '<th class="text-center">Enrolled</th><th class="text-center">Sat</th><th class="text-center">Not sat</th>'
        . '<th class="text-center">Mean</th><th class="text-center">Highest</th><th class="text-center">Lowest</th>'
        . '<th class="text-center">Passed</th><th class="text-center">Failed</th><th class="text-center">Pass %</th>';
    foreach ($gradeLabels as $gl) {
        $h .= '<th class="text-center">' . View::e($gl) . '</th>';
    }

    return $h . '</tr></thead>';
};
?>
<div class="results-landscape-root results-print-area report-page--print-landscape">
  <div class="results-toolbar d-print-none d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
      <h4 class="mb-1">
        <i class="bi bi-bar-chart-steps"></i> Performance by class and gender
        <span class="badge <?= $stage === 'midterm' ? 'text-bg-warning' : 'text-bg-primary' ?> align-middle ms-1"><?= View::e($stageLabel) ?></span>
      </h4>
      <div class="small text-muted">
        <?= View::e($year) ?> · <?= View::e($term) ?> · Every class, boys and girls side by side
      </div>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-end">
      <form method="get" data-auto-reload class="d-flex flex-wrap gap-2 align-items-end"
            action="<?= $base ?><?= $portalPrefix ?>/results/gender">
        <div>
          <label class="form-label small mb-1">Year</label>
          <select name="year" class="form-select form-select-sm">
            <?php foreach (($years ?? []) as $y): ?>
              <option value="<?= View::e($y) ?>" <?= $y === $year ? 'selected' : '' ?>><?= View::e($y) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label small mb-1">Term</label>
          <select name="term" class="form-select form-select-sm">
            <?php foreach (($terms ?? []) as $t): ?>
              <option <?= $t === $term ? 'selected' : '' ?>><?= View::e($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label small mb-1">Assessment</label>
          <select name="stage" class="form-select form-select-sm">
            <?php foreach (($stages ?? []) as $key => $label): ?>
              <option value="<?= View::e((string) $key) ?>" <?= $key === $stage ? 'selected' : '' ?>><?= View::e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-outline-primary btn-sm">Reload</button>
      </form>
      <button type="button" class="btn btn-primary btn-sm" onclick="window.print()" title="Print this page">
        <i class="bi bi-printer"></i> Print
      </button>
      <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?><?= $portalPrefix ?>/results?<?= View::e($qs) ?>">
        <i class="bi bi-arrow-left"></i> Results
      </a>
    </div>
  </div>

  <div class="results-print-brand border-bottom pb-2 mb-3 d-none d-print-block">
    <div class="fw-bold"><?= View::e($schoolName) ?></div>
    <div class="small">Performance by class and gender · <?= View::e($stageLabel) ?> · <?= View::e($year) ?> · <?= View::e($term) ?> · Pass mark <?= (int) $passMark ?>%</div>
  </div>

  <?php if (empty($byClass)): ?>
    <div class="alert alert-info d-print-none">
      No classes available for your account.
    </div>
  <?php else: ?>

  <div class="row g-3 mb-3 d-print-none">
    <div class="col-sm-6 col-lg-3">
      <div class="kpi-card">
        <div class="kpi-card__icon kpi-card__icon--blue"><i class="bi bi-gender-male"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Boys — average</div>
          <div class="kpi-card__value"><?= $fmtPct($boysTotal['avg']) ?></div>
          <div class="kpi-card__delta kpi-card__delta--flat"><?= (int) $boysTotal['n'] ?> of <?= (int) $totalsEnrolled['male'] ?> boys sat</div>
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
          <div class="kpi-card__delta kpi-card__delta--flat"><?= (int) $girlsTotal['n'] ?> of <?= (int) $totalsEnrolled['female'] ?> girls sat</div>
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
    <p class="small text-muted mb-2">
      <i class="bi bi-<?= $gap > 0.05 ? 'arrow-up-right' : ($gap < -0.05 ? 'arrow-down-right' : 'dash-lg') ?>"></i>
      <?php if (abs($gap) <= 0.05): ?>
        Boys and girls averaged the same across the school.
      <?php else: ?>
        Across the school, girls averaged <?= number_format(abs($gap), 1) ?> percentage point<?= abs($gap) == 1 ? '' : 's' ?>
        <?= $gap > 0 ? 'higher' : 'lower' ?> than boys.
      <?php endif; ?>
    </p>
  <?php endif; ?>
  <?php if ($otherCount > 0): ?>
    <p class="small text-muted mb-2">
      <i class="bi bi-info-circle"></i> <?= (int) $otherCount ?> student<?= $otherCount === 1 ? '' : 's' ?> with another gender recorded
      <?= $otherCount === 1 ? 'is' : 'are' ?> counted in the Total rows only.
    </p>
  <?php endif; ?>
  <p class="small text-muted mb-3">
    <strong>Enrolled</strong> is the class register for <?= View::e($year) ?>; <strong>Sat</strong> is students with at least one
    mark this term; <strong>Not sat</strong> is the difference. Averages, pass counts and grades are each student's overall average.
  </p>

  <div class="chart-surface chart-surface--gender-class mb-4 d-print-none">
    <canvas id="genderClassChart" role="img"
            aria-label="Average percentage by class, boys versus girls"></canvas>
  </div>

  <h5 class="mb-2"><i class="bi bi-mortarboard"></i> By class</h5>
  <div class="results-table-panel mb-4">
    <div class="table-responsive">
      <table class="table table-sm align-middle bg-white results-density-table perf-stats-table mb-0">
        <?= $tableHead('Class') ?>
        <?php foreach ($byClass as $classId => $row): ?>
          <tbody class="perf-class">
            <?php foreach ($groups as $gk => $gLabel): ?>
              <tr>
                <?php if ($gk === 'male'): ?>
                  <th rowspan="3" scope="rowgroup" class="align-middle perf-class__name">
                    <?= View::e($row['name']) ?>
                    <?php if (!empty($row['level'])): ?>
                      <div class="text-muted small fw-normal"><?= View::e($row['level']) ?></div>
                    <?php endif; ?>
                    <a class="small fw-normal d-print-none" title="Full analysis for <?= View::e($row['name']) ?>"
                       href="<?= $base ?><?= $portalPrefix ?>/results/analysis/<?= (int) $classId ?>?<?= View::e($qs) ?>">
                      <i class="bi bi-clipboard-data"></i> Analysis
                    </a>
                  </th>
                <?php endif; ?>
                <?= $statRow($gk, $row['stats'][$gk], (int) $row['enrolled'][$gk]) ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        <?php endforeach; ?>
        <tbody class="perf-class perf-class--school">
          <?php foreach ($groups as $gk => $gLabel): ?>
            <tr>
              <?php if ($gk === 'male'): ?>
                <th rowspan="3" scope="rowgroup" class="align-middle perf-class__name">Whole school</th>
              <?php endif; ?>
              <?= $statRow($gk, $totals[$gk], (int) $totalsEnrolled[$gk]) ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if (!empty($bySchool)): ?>
    <h5 class="mb-2"><i class="bi bi-buildings"></i> By school</h5>
    <div class="results-table-panel mb-4">
      <div class="table-responsive">
        <table class="table table-sm align-middle bg-white results-density-table perf-stats-table mb-0">
          <?= $tableHead('School') ?>
          <?php foreach ($bySchool as $row): ?>
            <tbody class="perf-class">
              <?php foreach ($groups as $gk => $gLabel): ?>
                <tr>
                  <?php if ($gk === 'male'): ?>
                    <th rowspan="3" scope="rowgroup" class="align-middle perf-class__name"><?= View::e($row['name']) ?></th>
                  <?php endif; ?>
                  <?= $statRow($gk, $row['stats'][$gk], null) ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          <?php endforeach; ?>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" defer></script>
  <script>
  (function () {
    <?php $classList = array_values($byClass); ?>
    var labels = <?= json_encode(array_map(static fn ($r) => $r['name'], $classList)) ?>;
    var boysAvg  = <?= json_encode(array_map(static fn ($r) => $r['stats']['male']['avg'] !== null ? round($r['stats']['male']['avg'], 1) : null, $classList)) ?>;
    var girlsAvg = <?= json_encode(array_map(static fn ($r) => $r['stats']['female']['avg'] !== null ? round($r['stats']['female']['avg'], 1) : null, $classList)) ?>;

    var chart = null;
    function build() {
      var el = document.getElementById('genderClassChart');
      if (!el || !window.Chart || labels.length === 0) return;
      if (chart) { chart.destroy(); }

      var styles = getComputedStyle(document.body);
      var gridColor = (styles.getPropertyValue('--border') || '#e5e7eb').trim();
      var textColor = (styles.getPropertyValue('--text-muted') || '#6b7280').trim();

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
      new MutationObserver(function (mutations) {
        for (var i = 0; i < mutations.length; i++) {
          if (mutations[i].attributeName === 'data-bs-theme') { whenChartReady(build); break; }
        }
      }).observe(document.documentElement, { attributes: true });
    });

    window.addEventListener('resize', function () {
      clearTimeout(window.__genderChartResize);
      window.__genderChartResize = setTimeout(build, 150);
    });
  })();
  </script>

  <?php endif; ?>
</div>
