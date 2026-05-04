<?php
use App\Core\View;
$layout = 'app';
$title = 'Results';
$schoolName = $schoolName ?? '';
?>
<div class="results-landscape-root results-print-area report-page--print-landscape">
  <div class="results-toolbar d-print-none d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      <h4 class="mb-1"><i class="bi bi-graph-up-arrow"></i> Results overview</h4>
      <div class="small text-muted">
        <?= View::e($year) ?> · <?= View::e($term) ?> · Mid-term ×<?= (int) ($midMax ?? 30) ?> + End-term ×<?= (int) ($endMax ?? 70) ?> per subject
      </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <button type="button" class="btn btn-primary btn-sm" onclick="window.print()" title="Print this page">
        <i class="bi bi-printer"></i> Print
      </button>
      <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?><?= $portalPrefix ?>/results">
        <i class="bi bi-calendar3"></i> Change period
      </a>
    </div>
  </div>

  <div class="results-print-brand border-bottom pb-2 mb-3 d-none d-print-block">
    <div class="fw-bold"><?= View::e($schoolName) ?></div>
    <div class="small">Term results overview · <?= View::e($year) ?> · <?= View::e($term) ?></div>
  </div>

  <form class="card border-0 shadow-sm mb-4 d-print-none" method="get" action="<?= $base ?><?= $portalPrefix ?>/results">
    <div class="card-body row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label">Year</label>
        <select name="year" class="form-select">
          <?php foreach (($years ?? []) as $y): ?>
            <option value="<?= View::e($y) ?>" <?= $y === $year ? 'selected' : '' ?>><?= View::e($y) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Term</label>
        <select name="term" class="form-select">
          <?php foreach (($terms ?? []) as $t): ?>
            <option <?= $t === $term ? 'selected' : '' ?>><?= View::e($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-arrow-clockwise"></i> Reload</button>
      </div>
    </div>
  </form>

  <?php if (empty($classes)): ?>
    <div class="alert alert-info d-print-none">
      No classes available for your account, or teaching assignments are not set up yet.
    </div>
  <?php else: ?>
    <div class="results-table-panel">
      <div class="small text-muted mb-2 d-print-none">Open a class for full marks breakdown. The class table uses the full width (landscape-style) to reduce sideways scrolling.</div>
      <div class="results-index-screen-list list-group list-group-flush shadow-none border rounded overflow-hidden">
        <?php foreach ($classes as $c): ?>
          <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3"
             href="<?= $base ?><?= $portalPrefix ?>/results/class/<?= (int) $c['id'] ?>?year=<?= rawurlencode($year) ?>&term=<?= rawurlencode($term) ?>">
            <span>
              <strong><?= View::e($c['name']) ?></strong>
              <?php if (!empty($c['level'])): ?>
                <span class="text-muted small ms-1"><?= View::e($c['level']) ?></span>
              <?php endif; ?>
            </span>
            <i class="bi bi-chevron-right text-muted"></i>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <table class="table table-sm d-none w-100 mb-0 border results-density-table results-print-index-table">
      <thead class="table-light">
        <tr>
          <th class="rd-pos">#</th>
          <th>Class</th>
          <th>Level</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (array_values($classes) as $i => $c): ?>
          <tr>
            <td class="rd-pos text-center"><?= $i + 1 ?></td>
            <td><?= View::e($c['name']) ?></td>
            <td><?= View::e($c['level'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
