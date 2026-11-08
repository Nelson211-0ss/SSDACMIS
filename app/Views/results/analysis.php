<?php
use App\Core\View;
use App\Services\AcademicMarking;

$layout = 'app';
$stage      = $stage ?? AcademicMarking::STAGE_END;
$stageLabel = $stageLabel ?? AcademicMarking::stageLabel($stage);
$isMid      = ($stage === AcademicMarking::STAGE_MID);
$title = 'Class analysis — ' . ($class['name'] ?? '') . ' · ' . $term;
$schoolName = $schoolName ?? '';
$classTeacherName = trim(($class['teacher_first'] ?? '') . ' ' . ($class['teacher_last'] ?? ''));
$qs = 'year=' . rawurlencode($year) . '&term=' . rawurlencode($term) . '&stage=' . rawurlencode($stage);
$passMark = (float) ($passMark ?? 50);

$fmtPct = static fn (?float $v): string => $v === null ? '—' : number_format($v, 1) . '%';
$fmtNum = static fn (?float $v): string => $v === null ? '—' : number_format($v, 1);
/** "3 (25%)" — a count with its share of the group. */
$fmtShare = static function (int $count, int $of): string {
    if ($of <= 0) {
        return '—';
    }
    return $count . ' <span class="text-muted small">(' . number_format($count / $of * 100, 0) . '%)</span>';
};
/** Girls minus boys, as an arrow and the size of the gap in points. */
$gapCell = static function (?float $boys, ?float $girls): string {
    if ($boys === null || $girls === null) {
        return '—';
    }
    $gap = $girls - $boys;
    $icon = $gap > 0.05 ? 'arrow-up-right' : ($gap < -0.05 ? 'arrow-down-right' : 'dash-lg');
    $who  = $gap > 0.05 ? 'Girls ahead' : ($gap < -0.05 ? 'Boys ahead' : 'Level');
    return '<span title="' . $who . '"><i class="bi bi-' . $icon . '"></i> ' . number_format(abs($gap), 1) . '</span>';
};
$studentName = static fn (array $s): string => trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? ''));

$b = $summary['male'];
$g = $summary['female'];
$a = $summary['all'];
$hasResults = $a['n'] > 0;
$gap = ($b['avg'] !== null && $g['avg'] !== null) ? $g['avg'] - $b['avg'] : null;
?>
<div class="results-landscape-root results-print-area report-page--print-landscape class-analysis">
  <div class="results-toolbar d-print-none mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
      <div>
        <h4 class="mb-1">
          <i class="bi bi-clipboard-data"></i> Class analysis — <?= View::e($class['name']) ?>
          <span class="badge <?= $isMid ? 'text-bg-warning' : 'text-bg-primary' ?> align-middle ms-1"><?= View::e($stageLabel) ?></span>
        </h4>
        <div class="small text-muted">
          <?= View::e($year) ?> · <?= View::e($term) ?>
          <?php if (!empty($class['level'])): ?> · <?= View::e($class['level']) ?><?php endif; ?>
          <?php if ($classTeacherName !== ''): ?> · Class teacher: <?= View::e($classTeacherName) ?><?php endif; ?>
          · Boys and girls compared · Pass mark <?= (int) $passMark ?>%
        </div>
      </div>
      <div class="d-flex flex-wrap gap-2 align-items-end justify-content-end">
        <form method="get" data-auto-reload class="d-flex flex-wrap gap-2 align-items-end"
              action="<?= $base ?><?= $portalPrefix ?>/results/analysis/<?= (int) $class['id'] ?>">
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
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()" title="Print this analysis">
          <i class="bi bi-printer"></i> Print
        </button>
      </div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-2">
      <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?><?= $portalPrefix ?>/results/class/<?= (int) $class['id'] ?>?<?= View::e($qs) ?>">
        <i class="bi bi-table"></i> Class results sheet
      </a>
      <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?><?= $portalPrefix ?>/results/gender?<?= View::e($qs) ?>">
        <i class="bi bi-bar-chart-steps"></i> All classes
      </a>
      <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?><?= $portalPrefix ?>/results?<?= View::e($qs) ?>">
        <i class="bi bi-arrow-left"></i> Classes
      </a>
    </div>
  </div>

  <div class="results-print-brand border-bottom pb-2 mb-3 d-none d-print-block">
    <div class="fw-bold"><?= View::e($schoolName) ?></div>
    <div>Class analysis — <?= View::e($class['name']) ?><?php if (!empty($class['level'])): ?> · <?= View::e($class['level']) ?><?php endif; ?></div>
    <div class="small">
      <?= View::e($stageLabel) ?> · <?= View::e($year) ?> · <?= View::e($term) ?>
      <?php if ($classTeacherName !== ''): ?> · Class teacher: <?= View::e($classTeacherName) ?><?php endif; ?>
      · Pass mark <?= (int) $passMark ?>%
    </div>
  </div>

  <?php if (!$hasResults): ?>
    <div class="alert alert-info">
      No <?= View::e(strtolower($stageLabel)) ?> results have been published for <?= View::e($class['name']) ?>
      in <?= View::e($term) ?> <?= View::e($year) ?> yet. Results appear here once marks are entered for the class.
    </div>
  <?php else: ?>

  <!-- ===== Summary ===== -->
  <div class="row g-3 mb-3 d-print-none">
    <div class="col-sm-6 col-lg-3">
      <div class="kpi-card">
        <div class="kpi-card__icon kpi-card__icon--purple"><i class="bi bi-people"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Class average</div>
          <div class="kpi-card__value"><?= $fmtPct($a['avg']) ?></div>
          <div class="kpi-card__delta kpi-card__delta--flat"><?= (int) $a['n'] ?> student<?= $a['n'] === 1 ? '' : 's' ?> with results</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="kpi-card">
        <div class="kpi-card__icon kpi-card__icon--green"><i class="bi bi-check2-circle"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Class pass rate</div>
          <div class="kpi-card__value"><?= $fmtPct($a['passPct']) ?></div>
          <div class="kpi-card__delta kpi-card__delta--flat"><?= (int) $a['passed'] ?> of <?= (int) $a['n'] ?> passed</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="kpi-card">
        <div class="kpi-card__icon kpi-card__icon--blue"><i class="bi bi-gender-male"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Boys — average</div>
          <div class="kpi-card__value"><?= $fmtPct($b['avg']) ?></div>
          <div class="kpi-card__delta kpi-card__delta--flat"><?= (int) $b['n'] ?> boy<?= $b['n'] === 1 ? '' : 's' ?> · <?= $fmtPct($b['passPct']) ?> passed</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="kpi-card">
        <div class="kpi-card__icon kpi-card__icon--pink"><i class="bi bi-gender-female"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Girls — average</div>
          <div class="kpi-card__value"><?= $fmtPct($g['avg']) ?></div>
          <div class="kpi-card__delta kpi-card__delta--flat"><?= (int) $g['n'] ?> girl<?= $g['n'] === 1 ? '' : 's' ?> · <?= $fmtPct($g['passPct']) ?> passed</div>
        </div>
      </div>
    </div>
  </div>

  <?php if ($gap !== null): ?>
    <p class="small text-muted mb-3">
      <i class="bi bi-<?= $gap > 0.05 ? 'arrow-up-right' : ($gap < -0.05 ? 'arrow-down-right' : 'dash-lg') ?>"></i>
      <?php if (abs($gap) <= 0.05): ?>
        Boys and girls averaged the same this term.
      <?php else: ?>
        Girls averaged <?= number_format(abs($gap), 1) ?> percentage point<?= abs($gap) == 1 ? '' : 's' ?>
        <?= $gap > 0 ? 'higher' : 'lower' ?> than boys.
      <?php endif; ?>
    </p>
  <?php endif; ?>
  <?php if ($otherCount > 0): ?>
    <p class="small text-muted mb-3">
      <i class="bi bi-info-circle"></i> <?= (int) $otherCount ?> student<?= $otherCount === 1 ? '' : 's' ?> with another gender recorded
      <?= $otherCount === 1 ? 'is' : 'are' ?> counted in the whole-class figures only.
    </p>
  <?php endif; ?>

  <div class="row g-3 mb-4">
    <div class="col-lg-6">
      <h5 class="mb-2"><i class="bi bi-list-columns-reverse"></i> Overall summary</h5>
      <div class="results-table-panel">
        <div class="table-responsive">
          <table class="table table-sm align-middle bg-white results-density-table mb-0">
            <thead class="table-light">
              <tr>
                <th></th>
                <th class="text-center">Boys</th>
                <th class="text-center">Girls</th>
                <th class="text-center">Whole class</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Students with results</td>
                <td class="text-center"><?= (int) $b['n'] ?></td>
                <td class="text-center"><?= (int) $g['n'] ?></td>
                <td class="text-center fw-semibold"><?= (int) $a['n'] ?></td>
              </tr>
              <tr>
                <td>Average</td>
                <td class="text-center"><?= $fmtPct($b['avg']) ?></td>
                <td class="text-center"><?= $fmtPct($g['avg']) ?></td>
                <td class="text-center fw-semibold"><?= $fmtPct($a['avg']) ?></td>
              </tr>
              <tr>
                <td>Highest</td>
                <td class="text-center"><?= $fmtPct($b['max']) ?></td>
                <td class="text-center"><?= $fmtPct($g['max']) ?></td>
                <td class="text-center fw-semibold"><?= $fmtPct($a['max']) ?></td>
              </tr>
              <tr>
                <td>Lowest</td>
                <td class="text-center"><?= $fmtPct($b['min']) ?></td>
                <td class="text-center"><?= $fmtPct($g['min']) ?></td>
                <td class="text-center fw-semibold"><?= $fmtPct($a['min']) ?></td>
              </tr>
              <tr>
                <td>Passed (<?= (int) $passMark ?>% and above)</td>
                <td class="text-center"><?= (int) $b['passed'] ?></td>
                <td class="text-center"><?= (int) $g['passed'] ?></td>
                <td class="text-center fw-semibold"><?= (int) $a['passed'] ?></td>
              </tr>
              <tr>
                <td>Failed (below <?= (int) $passMark ?>%)</td>
                <td class="text-center"><?= (int) ($b['n'] - $b['passed']) ?></td>
                <td class="text-center"><?= (int) ($g['n'] - $g['passed']) ?></td>
                <td class="text-center fw-semibold"><?= (int) ($a['n'] - $a['passed']) ?></td>
              </tr>
              <tr>
                <td>Pass rate</td>
                <td class="text-center"><?= $fmtPct($b['passPct']) ?></td>
                <td class="text-center"><?= $fmtPct($g['passPct']) ?></td>
                <td class="text-center fw-semibold"><?= $fmtPct($a['passPct']) ?></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <h5 class="mb-2"><i class="bi bi-award"></i> Grade distribution <span class="small text-muted fw-normal">(overall average)</span></h5>
      <div class="results-table-panel">
        <div class="table-responsive">
          <table class="table table-sm align-middle bg-white results-density-table mb-0">
            <thead class="table-light">
              <tr>
                <th>Grade</th>
                <th class="text-center">Boys</th>
                <th class="text-center">Girls</th>
                <th class="text-center">Whole class</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($gradeLabels as $gl): ?>
                <tr>
                  <td class="fw-semibold"><?= View::e($gl) ?></td>
                  <td class="text-center"><?= $fmtShare((int) $b['grades'][$gl], (int) $b['n']) ?></td>
                  <td class="text-center"><?= $fmtShare((int) $g['grades'][$gl], (int) $g['n']) ?></td>
                  <td class="text-center fw-semibold"><?= $fmtShare((int) $a['grades'][$gl], (int) $a['n']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="chart-surface class-analysis-chart class-analysis-chart--grades mt-3 d-print-none">
        <canvas id="caGradeChart" role="img" aria-label="Number of boys and girls at each grade"></canvas>
      </div>
    </div>
  </div>

  <!-- ===== Subjects ===== -->
  <?php if (!empty($subjects)): ?>
  <h5 class="mb-2"><i class="bi bi-journal-text"></i> Subject performance</h5>
  <p class="small text-muted mb-2">
    Mean is the average percentage scored in the subject<?= $isMid ? ' (mid-term marks out of ' . (int) AcademicMarking::MID_MAX . ', shown as %)' : '' ?>.
    Rank orders subjects by the whole-class mean. Gap is girls minus boys.
  </p>
  <div class="results-table-panel mb-3">
    <div class="table-responsive">
      <table class="table table-sm align-middle bg-white results-density-table mb-0">
        <thead class="table-light">
          <tr>
            <th rowspan="2" class="align-middle">Subject</th>
            <th colspan="3" class="text-center">Boys</th>
            <th colspan="3" class="text-center">Girls</th>
            <th colspan="3" class="text-center">Whole class</th>
            <th rowspan="2" class="align-middle text-center">Gap</th>
            <th rowspan="2" class="align-middle text-center">Rank</th>
          </tr>
          <tr>
            <th class="text-center">Sat</th><th class="text-center">Mean</th><th class="text-center">Pass</th>
            <th class="text-center">Sat</th><th class="text-center">Mean</th><th class="text-center">Pass</th>
            <th class="text-center">Sat</th><th class="text-center">Mean</th><th class="text-center">Pass</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($subjects as $sub):
            $sb = $sub['stats']['male']; $sg = $sub['stats']['female']; $sa = $sub['stats']['all'];
          ?>
            <tr>
              <td><?= View::e($sub['name']) ?><?php if ($sub['code'] !== ''): ?> <span class="text-muted small"><?= View::e($sub['code']) ?></span><?php endif; ?></td>
              <td class="text-center"><?= (int) $sb['n'] ?></td>
              <td class="text-center"><?= $fmtNum($sb['avg']) ?></td>
              <td class="text-center"><?= $fmtPct($sb['passPct']) ?></td>
              <td class="text-center"><?= (int) $sg['n'] ?></td>
              <td class="text-center"><?= $fmtNum($sg['avg']) ?></td>
              <td class="text-center"><?= $fmtPct($sg['passPct']) ?></td>
              <td class="text-center"><?= (int) $sa['n'] ?></td>
              <td class="text-center fw-semibold"><?= $fmtNum($sa['avg']) ?></td>
              <td class="text-center"><?= $fmtPct($sa['passPct']) ?></td>
              <td class="text-center"><?= $gapCell($sb['avg'], $sg['avg']) ?></td>
              <td class="text-center"><?= $sub['rank'] !== null ? (int) $sub['rank'] : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="chart-surface class-analysis-chart class-analysis-chart--subjects mb-4 d-print-none">
    <canvas id="caSubjectChart" role="img" aria-label="Mean percentage per subject, boys versus girls"></canvas>
  </div>

  <h5 class="mb-2"><i class="bi bi-bar-chart"></i> Subject grade distribution</h5>
  <p class="small text-muted mb-2">Number of students at each grade in each subject, shown as boys / girls.</p>
  <div class="results-table-panel mb-4">
    <div class="table-responsive">
      <table class="table table-sm align-middle bg-white results-density-table mb-0">
        <thead class="table-light">
          <tr>
            <th>Subject</th>
            <?php foreach ($gradeLabels as $gl): ?>
              <th class="text-center"><?= View::e($gl) ?> <span class="text-muted small fw-normal">B / G</span></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($subjects as $sub): ?>
            <tr>
              <td><?= View::e($sub['name']) ?></td>
              <?php foreach ($gradeLabels as $gl): ?>
                <td class="text-center text-nowrap">
                  <span class="class-analysis-boys"><?= (int) $sub['stats']['male']['grades'][$gl] ?></span>
                  <span class="text-muted">/</span>
                  <span class="class-analysis-girls"><?= (int) $sub['stats']['female']['grades'][$gl] ?></span>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <!-- ===== Term by term ===== -->
  <div class="row g-3 mb-4">
    <div class="col-lg-7">
      <h5 class="mb-2"><i class="bi bi-graph-up"></i> Term by term, <?= View::e($year) ?> <span class="small text-muted fw-normal">(<?= View::e(strtolower($stageLabel)) ?>)</span></h5>
      <div class="results-table-panel">
        <div class="table-responsive">
          <table class="table table-sm align-middle bg-white results-density-table mb-0">
            <thead class="table-light">
              <tr>
                <th rowspan="2" class="align-middle">Term</th>
                <th colspan="2" class="text-center">Boys</th>
                <th colspan="2" class="text-center">Girls</th>
                <th colspan="2" class="text-center">Whole class</th>
                <th rowspan="2" class="align-middle text-center">Gap</th>
              </tr>
              <tr>
                <th class="text-center">Avg</th><th class="text-center">Pass</th>
                <th class="text-center">Avg</th><th class="text-center">Pass</th>
                <th class="text-center">Avg</th><th class="text-center">Pass</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($trend as $t => $tb): ?>
                <tr class="<?= $t === $term ? 'table-active' : '' ?>">
                  <td>
                    <?php if ($tb['all']['n'] > 0 && $t !== $term): ?>
                      <a href="<?= $base ?><?= $portalPrefix ?>/results/analysis/<?= (int) $class['id'] ?>?year=<?= rawurlencode($year) ?>&amp;term=<?= rawurlencode($t) ?>&amp;stage=<?= rawurlencode($stage) ?>"><?= View::e($t) ?></a>
                    <?php else: ?>
                      <?= View::e($t) ?>
                    <?php endif; ?>
                  </td>
                  <td class="text-center"><?= $fmtPct($tb['male']['avg']) ?></td>
                  <td class="text-center"><?= $fmtPct($tb['male']['passPct']) ?></td>
                  <td class="text-center"><?= $fmtPct($tb['female']['avg']) ?></td>
                  <td class="text-center"><?= $fmtPct($tb['female']['passPct']) ?></td>
                  <td class="text-center fw-semibold"><?= $fmtPct($tb['all']['avg']) ?></td>
                  <td class="text-center"><?= $fmtPct($tb['all']['passPct']) ?></td>
                  <td class="text-center"><?= $gapCell($tb['male']['avg'], $tb['female']['avg']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-lg-5 d-print-none">
      <div class="chart-surface class-analysis-chart class-analysis-chart--trend mt-lg-4">
        <canvas id="caTrendChart" role="img" aria-label="Average percentage per term, boys, girls and whole class"></canvas>
      </div>
    </div>
  </div>

  <!-- ===== Best performers ===== -->
  <div class="row g-3">
    <?php foreach ([['Best boys', 'bi-gender-male', $topBoys], ['Best girls', 'bi-gender-female', $topGirls]] as [$heading, $icon, $list]): ?>
      <div class="col-md-6">
        <h5 class="mb-2"><i class="bi <?= $icon ?>"></i> <?= $heading ?></h5>
        <div class="results-table-panel">
          <?php if (empty($list)): ?>
            <p class="small text-muted mb-0 p-2">No results.</p>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm align-middle bg-white results-density-table mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="text-center">Pos</th>
                    <th>Student</th>
                    <th>Adm. no</th>
                    <th class="text-center">Average</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($list as $st): ?>
                    <tr>
                      <td class="text-center"><?= $st['class_position'] !== null ? (int) $st['class_position'] : '—' ?></td>
                      <td><?= View::e($studentName($st)) ?></td>
                      <td class="text-muted"><?= View::e((string) $st['admission_no']) ?></td>
                      <td class="text-center fw-semibold"><?= $fmtPct((float) $st['average_percentage']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" defer></script>
  <script>
  (function () {
    'use strict';
    <?php $subList = array_values($subjects); ?>
    var data = {
      grades:   <?= json_encode($gradeLabels) ?>,
      gradeBoys:  <?= json_encode(array_values(array_map('intval', $b['grades']))) ?>,
      gradeGirls: <?= json_encode(array_values(array_map('intval', $g['grades']))) ?>,
      subjects: <?= json_encode(array_map(static fn ($s) => $s['code'] !== '' ? $s['code'] : $s['name'], $subList)) ?>,
      subjectNames: <?= json_encode(array_map(static fn ($s) => $s['name'], $subList)) ?>,
      subBoys:  <?= json_encode(array_map(static fn ($s) => $s['stats']['male']['avg'] !== null ? round($s['stats']['male']['avg'], 1) : null, $subList)) ?>,
      subGirls: <?= json_encode(array_map(static fn ($s) => $s['stats']['female']['avg'] !== null ? round($s['stats']['female']['avg'], 1) : null, $subList)) ?>,
      terms:    <?= json_encode(array_keys($trend)) ?>,
      trendBoys:  <?= json_encode(array_values(array_map(static fn ($t) => $t['male']['avg'] !== null ? round($t['male']['avg'], 1) : null, $trend))) ?>,
      trendGirls: <?= json_encode(array_values(array_map(static fn ($t) => $t['female']['avg'] !== null ? round($t['female']['avg'], 1) : null, $trend))) ?>,
      trendAll:   <?= json_encode(array_values(array_map(static fn ($t) => $t['all']['avg'] !== null ? round($t['all']['avg'], 1) : null, $trend))) ?>
    };
    var BOYS = '#3b82f6', GIRLS = '#ec4899', CLASS = '#64748b';
    var charts = [];

    function pctLabel(ctx) {
      var v = ctx.parsed.y;
      return ctx.dataset.label + ': ' + (v === null ? '—' : v.toFixed(1) + '%');
    }

    function build() {
      if (!window.Chart) return;
      charts.forEach(function (c) { c.destroy(); });
      charts = [];
      var styles = getComputedStyle(document.body);
      var grid = (styles.getPropertyValue('--border') || '#e5e7eb').trim();
      var text = (styles.getPropertyValue('--text-muted') || '#6b7280').trim();
      var axes = function (pct) {
        return {
          y: pct
            ? { beginAtZero: true, max: 100, grid: { color: grid }, ticks: { color: text } }
            : { beginAtZero: true, grid: { color: grid }, ticks: { color: text, precision: 0 } },
          x: { grid: { display: false }, ticks: { color: text } }
        };
      };
      var legend = { position: 'top', labels: { color: text } };

      var el = document.getElementById('caGradeChart');
      if (el) {
        charts.push(new Chart(el, {
          type: 'bar',
          data: { labels: data.grades, datasets: [
            { label: 'Boys',  data: data.gradeBoys,  backgroundColor: BOYS,  borderRadius: 4, maxBarThickness: 28 },
            { label: 'Girls', data: data.gradeGirls, backgroundColor: GIRLS, borderRadius: 4, maxBarThickness: 28 }
          ] },
          options: { responsive: true, maintainAspectRatio: false, scales: axes(false), plugins: { legend: legend } }
        }));
      }

      el = document.getElementById('caSubjectChart');
      if (el && data.subjects.length) {
        charts.push(new Chart(el, {
          type: 'bar',
          data: { labels: data.subjects, datasets: [
            { label: 'Boys mean',  data: data.subBoys,  backgroundColor: BOYS,  borderRadius: 4, maxBarThickness: 24 },
            { label: 'Girls mean', data: data.subGirls, backgroundColor: GIRLS, borderRadius: 4, maxBarThickness: 24 }
          ] },
          options: {
            responsive: true, maintainAspectRatio: false, scales: axes(true),
            plugins: { legend: legend, tooltip: { callbacks: {
              title: function (items) { return items.length ? data.subjectNames[items[0].dataIndex] : ''; },
              label: pctLabel
            } } }
          }
        }));
      }

      el = document.getElementById('caTrendChart');
      if (el) {
        var line = function (label, values, color, dash) {
          return { label: label, data: values, borderColor: color, backgroundColor: color,
                   borderDash: dash || [], tension: 0.25, spanGaps: true, pointRadius: 4 };
        };
        charts.push(new Chart(el, {
          type: 'line',
          data: { labels: data.terms, datasets: [
            line('Boys', data.trendBoys, BOYS),
            line('Girls', data.trendGirls, GIRLS),
            line('Whole class', data.trendAll, CLASS, [5, 4])
          ] },
          options: {
            responsive: true, maintainAspectRatio: false, scales: axes(true),
            plugins: { legend: legend, tooltip: { callbacks: { label: pctLabel } } }
          }
        }));
      }
    }

    function whenChartReady(cb, tries) {
      tries = tries || 0;
      if (window.Chart) { cb(); return; }
      if (tries > 40) return;
      setTimeout(function () { whenChartReady(cb, tries + 1); }, 50);
    }

    document.addEventListener('DOMContentLoaded', function () {
      whenChartReady(build);
      // Re-colour axes when the theme flips (data-bs-theme on <html>).
      new MutationObserver(function (mutations) {
        for (var i = 0; i < mutations.length; i++) {
          if (mutations[i].attributeName === 'data-bs-theme') { whenChartReady(build); break; }
        }
      }).observe(document.documentElement, { attributes: true });
    });
  })();
  </script>

  <?php endif; ?>
</div>
