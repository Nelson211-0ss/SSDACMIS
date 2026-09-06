<?php
use App\Core\View;

$layout = 'app';
$stage      = $stage ?? 'endterm';
$stageLabel = $stageLabel ?? 'End of term';
$stageMax   = (float) ($stageMax ?? 100);
$isMid      = ($stage === 'midterm');
$title      = 'Subject analytics';

$subjects           = $subjects ?? [];
$classSummary       = $classSummary ?? [];
$classSubjects      = $classSubjects ?? [];
$classes            = $classes ?? [];
$totals             = $totals ?? [];
$filterClassId      = (int) ($filterClassId ?? 0);
$filterStream       = (string) ($filterStream ?? '');

$qs = 'year=' . rawurlencode($year) . '&term=' . rawurlencode($term) . '&stage=' . rawurlencode($stage);

/** One decimal, or an em dash when there is nothing to show. */
$num = static function ($v, int $dp = 1): string {
    return ($v === null) ? '—' : number_format((float) $v, $dp);
};
/** Bootstrap tone for a percentage: green pass, amber borderline, red fail. */
$tone = static function (?float $pct): string {
    if ($pct === null) return 'secondary';
    if ($pct >= 70) return 'success';
    if ($pct >= 50) return 'primary';
    if ($pct >= 40) return 'warning';
    return 'danger';
};
$catLabel = ['core' => 'Core', 'science' => 'Science', 'arts' => 'Arts', 'optional' => 'Optional'];

$classNameById = [];
foreach ($classes as $c) {
    $classNameById[(int) $c['id']] = (string) $c['name'];
}
?>
<div class="analytics-page">

  <!-- Hero -->
  <section class="dash-hero dash-hero--slim mb-3">
    <div class="d-flex flex-wrap align-items-center gap-3">
      <span class="icon-chip icon-chip--blue d-none d-sm-inline-grid" aria-hidden="true">
        <i class="bi bi-bar-chart-line-fill"></i>
      </span>
      <div class="flex-grow-1" style="min-width:0;">
        <h2 class="dash-hero__title mb-1">Subject performance</h2>
        <p class="dash-hero__sub mb-0">
          Best-to-worst subject ranking for the whole school and inside each class.
          Each subject is marked out of <?= (int) $stageMax ?>; a pass is <?= $num($passMark ?? ($stageMax / 2), 0) ?>.
        </p>
      </div>
      <span class="badge <?= $isMid ? 'text-bg-warning' : 'text-bg-primary' ?> align-self-start">
        <?= View::e($stageLabel) ?>
      </span>
    </div>
  </section>

  <!-- Filters -->
  <form class="card border-0 shadow-sm mb-3 d-print-none" method="get"
        action="<?= $base ?><?= $portalPrefix ?>/analytics">
    <div class="card-body">
      <div class="row g-2 g-md-3 align-items-end">
        <div class="col-6 col-md-4 col-xl-2">
          <label class="form-label" for="anYear">Year</label>
          <select id="anYear" name="year" class="form-select form-select-sm">
            <?php foreach (($years ?? []) as $y): ?>
              <option value="<?= View::e((string) $y) ?>" <?= (string) $y === (string) $year ? 'selected' : '' ?>><?= View::e((string) $y) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
          <label class="form-label" for="anTerm">Term</label>
          <select id="anTerm" name="term" class="form-select form-select-sm">
            <?php foreach (($terms ?? []) as $t): ?>
              <option <?= $t === $term ? 'selected' : '' ?>><?= View::e($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
          <label class="form-label" for="anStage">Assessment</label>
          <select id="anStage" name="stage" class="form-select form-select-sm">
            <?php foreach (($stages ?? []) as $key => $label): ?>
              <option value="<?= View::e((string) $key) ?>" <?= $key === $stage ? 'selected' : '' ?>><?= View::e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
          <label class="form-label" for="anClass">Class</label>
          <select id="anClass" name="class_id" class="form-select form-select-sm">
            <option value="0">All classes</option>
            <?php foreach ($classes as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === $filterClassId ? 'selected' : '' ?>>
                <?= View::e((string) $c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
          <label class="form-label" for="anStream">Stream</label>
          <select id="anStream" name="stream" class="form-select form-select-sm"
                  title="Science / Arts applies to Form 3 and Form 4 only">
            <option value="">All streams</option>
            <?php foreach (($streams ?? []) as $key => $label): ?>
              <option value="<?= View::e((string) $key) ?>" <?= (string) $key === $filterStream ? 'selected' : '' ?>>
                <?= View::e($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
          <button type="submit" class="btn btn-primary btn-sm w-100">
            <i class="bi bi-arrow-clockwise"></i> Apply
          </button>
        </div>
      </div>
    </div>
  </form>

<?php if (empty($subjects)): ?>
  <div class="alert alert-info">
    <i class="bi bi-info-circle"></i>
    No marks have been published for <strong><?= View::e($year) ?> · <?= View::e($term) ?> · <?= View::e($stageLabel) ?></strong>
    <?= $filterClassId > 0 ? ' in ' . View::e($classNameById[$filterClassId] ?? 'this class') : '' ?><?= $filterStream !== '' ? ' (' . View::e($filterStream) . ' stream)' : '' ?>.
    Analytics appear as soon as marks are saved for this period.
  </div>
<?php else: ?>

  <!-- KPI row -->
  <div class="row g-3 mb-4 analytics-kpis">
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small text-uppercase">Subjects assessed</div>
          <div class="fs-3 fw-bold"><?= (int) ($totals['subjects'] ?? 0) ?></div>
          <div class="small text-muted"><?= number_format((int) ($totals['entries'] ?? 0)) ?> subject marks</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small text-uppercase">Average score</div>
          <div class="fs-3 fw-bold text-<?= $tone($totals['percent'] ?? null) ?>">
            <?= $num($totals['percent'] ?? null) ?><span class="fs-6">%</span>
          </div>
          <div class="small text-muted"><?= $num($totals['average'] ?? null) ?> / <?= (int) $stageMax ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small text-uppercase">Pass rate</div>
          <div class="fs-3 fw-bold text-<?= $tone($totals['pass_rate'] ?? null) ?>">
            <?= $num($totals['pass_rate'] ?? null) ?><span class="fs-6">%</span>
          </div>
          <div class="small text-muted">at or above <?= $num($passMark ?? ($stageMax / 2), 0) ?> marks</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small text-uppercase">Best subject</div>
          <?php if (!empty($totals['best'])): ?>
            <div class="fs-5 fw-bold text-truncate" title="<?= View::e($totals['best']['name']) ?>">
              <?= View::e($totals['best']['name']) ?>
            </div>
            <div class="small text-muted"><?= $num($totals['best']['percent'] ?? null) ?>% average</div>
          <?php else: ?>
            <div class="fs-5 fw-bold">—</div>
          <?php endif; ?>
          <?php if (!empty($totals['worst'])): ?>
            <div class="small text-danger mt-1">
              Needs attention: <?= View::e($totals['worst']['name']) ?>
              (<?= $num($totals['worst']['percent'] ?? null) ?>%)
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- School-wide subject ranking -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent d-flex flex-wrap align-items-center gap-2">
      <i class="bi bi-trophy text-warning"></i>
      <strong class="me-auto">
        <?= $filterClassId > 0
          ? 'Subject ranking — ' . View::e($classNameById[$filterClassId] ?? 'class')
          : 'Subject ranking — whole school' ?>
      </strong>
      <span class="badge text-bg-light border"><?= View::e($year) ?> · <?= View::e($term) ?></span>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0 analytics-table">
        <thead class="table-light">
          <tr>
            <th style="width:3rem;">#</th>
            <th>Subject</th>
            <th class="d-none d-md-table-cell">Category</th>
            <th class="text-end">Average</th>
            <th class="text-end d-none d-sm-table-cell">Pass rate</th>
            <th class="text-end d-none d-lg-table-cell">Marks</th>
            <th class="text-end d-none d-lg-table-cell">vs last term</th>
            <th class="d-none d-xl-table-cell">Best class</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($subjects as $s):
            $pct = $s['percent'] !== null ? (float) $s['percent'] : null;
          ?>
            <tr>
              <td class="text-muted"><?= $s['rank'] !== null ? (int) $s['rank'] : '—' ?></td>
              <td>
                <span class="fw-semibold"><?= View::e($s['name']) ?></span>
                <?php if (!empty($s['code'])): ?>
                  <span class="text-muted small ms-1"><?= View::e($s['code']) ?></span>
                <?php endif; ?>
                <div class="analytics-bar d-lg-none mt-1" aria-hidden="true">
                  <span class="analytics-bar__fill bg-<?= $tone($pct) ?>"
                        style="width: <?= $pct !== null ? max(2, min(100, (int) round($pct))) : 0 ?>%"></span>
                </div>
              </td>
              <td class="d-none d-md-table-cell">
                <span class="badge text-bg-light border"><?= View::e($catLabel[$s['category']] ?? $s['category']) ?></span>
              </td>
              <td class="text-end">
                <span class="fw-semibold text-<?= $tone($pct) ?>"><?= $num($pct) ?>%</span>
                <div class="small text-muted"><?= $num($s['average']) ?>/<?= (int) $stageMax ?></div>
              </td>
              <td class="text-end d-none d-sm-table-cell"><?= $num($s['pass_rate']) ?>%</td>
              <td class="text-end d-none d-lg-table-cell"><?= (int) $s['entries'] ?></td>
              <td class="text-end d-none d-lg-table-cell">
                <?php if ($s['delta'] === null): ?>
                  <span class="text-muted">—</span>
                <?php else:
                  $up = ((float) $s['delta']) >= 0; ?>
                  <span class="<?= $up ? 'text-success' : 'text-danger' ?>">
                    <i class="bi <?= $up ? 'bi-arrow-up-short' : 'bi-arrow-down-short' ?>"></i>
                    <?= $num(abs((float) $s['delta'])) ?>
                  </span>
                <?php endif; ?>
              </td>
              <td class="d-none d-xl-table-cell">
                <?php if (!empty($s['best_class'])): ?>
                  <?= View::e($s['best_class']['class_name']) ?>
                  <span class="text-muted small">(<?= $num($s['best_class']['average']) ?>)</span>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer bg-transparent small text-muted">
      “vs last term” compares this subject’s average with the same assessment in the previous term.
    </div>
  </div>

  <!-- Class league table -->
  <?php if ($filterClassId === 0 && count($classSummary) > 1): ?>
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-transparent d-flex align-items-center gap-2">
        <i class="bi bi-buildings text-primary"></i>
        <strong>Class standings</strong>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 analytics-table">
          <thead class="table-light">
            <tr>
              <th style="width:3rem;">#</th>
              <th>Class</th>
              <th class="text-end">Average</th>
              <th class="text-end d-none d-sm-table-cell">Pass rate</th>
              <th class="d-none d-md-table-cell">Strongest subject</th>
              <th class="d-none d-lg-table-cell">Weakest subject</th>
              <th class="text-end d-none d-sm-table-cell"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($classSummary as $i => $cs):
              $pct = $cs['percent'] !== null ? (float) $cs['percent'] : null; ?>
              <tr>
                <td class="text-muted"><?= $i + 1 ?></td>
                <td>
                  <span class="fw-semibold"><?= View::e($cs['class_name']) ?></span>
                  <?php if (!empty($cs['level'])): ?>
                    <span class="text-muted small ms-1 d-none d-sm-inline"><?= View::e($cs['level']) ?></span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <span class="fw-semibold text-<?= $tone($pct) ?>"><?= $num($pct) ?>%</span>
                </td>
                <td class="text-end d-none d-sm-table-cell"><?= $num($cs['pass_rate']) ?>%</td>
                <td class="d-none d-md-table-cell">
                  <?= !empty($cs['best_subject']) ? View::e($cs['best_subject']['name']) : '—' ?>
                </td>
                <td class="d-none d-lg-table-cell text-danger">
                  <?= !empty($cs['worst_subject']) ? View::e($cs['worst_subject']['name']) : '—' ?>
                </td>
                <td class="text-end d-none d-sm-table-cell">
                  <a class="btn btn-sm btn-outline-secondary"
                     href="<?= $base ?><?= $portalPrefix ?>/analytics?<?= View::e($qs) ?>&amp;class_id=<?= (int) $cs['class_id'] ?>">
                    Open
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <!-- Per-class subject ranking -->
  <?php
    $perClassIds = $filterClassId > 0 ? [$filterClassId] : array_keys($classSubjects);
    if ($filterClassId === 0) {
        // Follow the class-standings order so the strongest class leads.
        $perClassIds = array_map(static fn ($cs) => (int) $cs['class_id'], $classSummary);
    }
  ?>
  <?php if ($perClassIds): ?>
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-transparent d-flex align-items-center gap-2">
        <i class="bi bi-list-ol text-success"></i>
        <strong>Subject ranking per class</strong>
      </div>
      <div class="accordion accordion-flush" id="analyticsClasses">
        <?php foreach ($perClassIds as $i => $cid):
          $list = $classSubjects[$cid] ?? [];
          if (!$list) continue;
          $open = ($i === 0);
        ?>
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button <?= $open ? '' : 'collapsed' ?>" type="button"
                      data-bs-toggle="collapse" data-bs-target="#anClass<?= (int) $cid ?>"
                      aria-expanded="<?= $open ? 'true' : 'false' ?>">
                <span class="fw-semibold"><?= View::e($classNameById[$cid] ?? ('Class ' . $cid)) ?></span>
                <span class="badge text-bg-light border ms-2"><?= count($list) ?> subjects</span>
              </button>
            </h2>
            <div id="anClass<?= (int) $cid ?>"
                 class="accordion-collapse collapse <?= $open ? 'show' : '' ?>"
                 data-bs-parent="#analyticsClasses">
              <div class="accordion-body p-0">
                <div class="table-responsive">
                  <table class="table table-sm table-hover align-middle mb-0 analytics-table">
                    <thead class="table-light">
                      <tr>
                        <th style="width:3rem;">#</th>
                        <th>Subject</th>
                        <th class="text-end">Average</th>
                        <th class="text-end d-none d-sm-table-cell">Pass rate</th>
                        <th class="text-end d-none d-md-table-cell">Best</th>
                        <th class="text-end d-none d-md-table-cell">Lowest</th>
                        <th class="text-end d-none d-sm-table-cell">Marks</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($list as $row):
                        $pct = $row['percent'] !== null ? (float) $row['percent'] : null; ?>
                        <tr>
                          <td class="text-muted"><?= $row['rank'] !== null ? (int) $row['rank'] : '—' ?></td>
                          <td><?= View::e($row['name']) ?></td>
                          <td class="text-end">
                            <span class="fw-semibold text-<?= $tone($pct) ?>"><?= $num($pct) ?>%</span>
                          </td>
                          <td class="text-end d-none d-sm-table-cell"><?= $num($row['pass_rate']) ?>%</td>
                          <td class="text-end d-none d-md-table-cell"><?= $num($row['best_marks']) ?></td>
                          <td class="text-end d-none d-md-table-cell"><?= $num($row['worst_marks']) ?></td>
                          <td class="text-end d-none d-sm-table-cell"><?= (int) $row['entries'] ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

<?php endif; ?>
</div>
