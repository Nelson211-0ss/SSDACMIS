<?php
use App\Core\View;
$layout = 'app';
$title = 'Results — Period';
?>
<div class="page-header">
  <div>
    <h2><i class="bi bi-graph-up-arrow"></i> Results</h2>
    <p class="page-header__sub mb-0">Choose academic year, term and assessment to view published averages and positions.</p>
  </div>
</div>

<div class="card border-0 shadow-sm mb-4" style="max-width: 36rem;">
  <div class="card-body">
    <?php if (!empty($invalid)): ?>
      <div class="alert alert-warning py-2 small mb-3">
        Pick both a valid academic year (YYYY/YYYY) and Term 1–3.
      </div>
    <?php endif; ?>
    <form method="get" action="<?= $base ?><?= $portalPrefix ?>/results" class="row g-3 align-items-end">
      <div class="col-md-6">
        <label class="form-label">Academic year</label>
        <select name="year" class="form-select" required>
          <?php foreach (($years ?? []) as $y): ?>
            <option value="<?= View::e($y) ?>" <?= ($submittedYear ?? '') === $y ? 'selected' : '' ?>><?= View::e($y) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Term</label>
        <select name="term" class="form-select" required>
          <?php foreach (($terms ?? []) as $t): ?>
            <option <?= ($submittedTerm ?? '') === $t ? 'selected' : '' ?>><?= View::e($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12">
        <label class="form-label">Assessment</label>
        <select name="stage" class="form-select" required>
          <?php foreach (($stages ?? []) as $key => $label): ?>
            <option value="<?= View::e((string) $key) ?>" <?= ($submittedStage ?? '') === $key ? 'selected' : '' ?>>
              <?= View::e($label) ?><?= $key === 'midterm' ? ' — each subject out of 30' : ' — mid + end, out of 100' ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="form-text">
          Mid-term results are published on their own and stay unchanged once end-of-term marks are entered.
        </div>
      </div>
      <div class="col-12">
        <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-right-circle"></i> Continue</button>
      </div>
    </form>
  </div>
</div>
