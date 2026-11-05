<?php
use App\Core\View;
use App\Services\PromotionService;

$layout = 'app';
$title  = 'Promotions';

$classes          = $classes ?? [];
$doneByClass      = $doneByClass ?? [];
$batches          = $batches ?? [];
$schools          = $schools ?? [];
$selectedSchoolId = $selectedSchoolId ?? null;
$yearQs           = 'year=' . rawurlencode($year);
?>

<div class="page-header mb-4">
  <div>
    <h2 class="h4 mb-1"><i class="bi bi-arrow-up-circle"></i> Promotions</h2>
    <p class="page-header__sub mb-0">
      At the end of the year, move each class up a level. You can also keep individual students back
      to repeat, move them down a level, or graduate the final class.
    </p>
  </div>
</div>

<div class="card border-0 shadow-sm mb-4">
  <div class="card-body">
    <form method="get" action="<?= $base ?>/promotions" data-auto-reload class="row g-3 align-items-end">
      <?php if (!empty($schools)): ?>
        <div class="col-sm-6 col-lg-4">
          <label class="form-label fw-semibold" for="prSchool">School</label>
          <select name="school_id" id="prSchool" class="form-select">
            <option value="">All schools</option>
            <?php foreach ($schools as $sch): ?>
              <option value="<?= (int) $sch['id'] ?>" <?= $selectedSchoolId === (int) $sch['id'] ? 'selected' : '' ?>><?= View::e((string) $sch['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <div class="col-sm-6 col-lg-3">
        <label class="form-label fw-semibold" for="prYear">Academic year being completed</label>
        <select name="year" id="prYear" class="form-select">
          <?php foreach ($years as $y): ?>
            <option value="<?= View::e($y) ?>" <?= $y === $year ? 'selected' : '' ?>><?= View::e($y) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>

    <div class="alert alert-info small mt-3 mb-0 d-flex gap-2">
      <i class="bi bi-info-circle flex-shrink-0"></i>
      <div>
        <strong>Before you start:</strong> finish entering <?= View::e($year) ?> marks, and promote before admitting
        the new Form 1 (S1) intake. Past report cards and results stay with the class each student was in
        during <?= View::e($year) ?>. Each student can only be moved once per year, so the order you do the classes in doesn't matter.
      </div>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-transparent fw-semibold">Classes</div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Class</th>
          <th>Level</th>
          <th class="text-center">Students now</th>
          <th class="text-center">Moved for <?= View::e($year) ?></th>
          <th class="text-end"></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$classes): ?>
          <tr><td colspan="5" class="text-center text-muted py-4">No classes yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($classes as $c):
          $cid = (int) $c['id'];
          $lvl = PromotionService::levelNumber($c['level'] ?? null);
          $isFinal = $lvl === PromotionService::FINAL_LEVEL;
          $done = (int) ($doneByClass[$cid] ?? 0);
        ?>
          <tr>
            <td class="fw-semibold"><?= View::e(mb_strtoupper((string) $c['name'], 'UTF-8')) ?></td>
            <td><?= View::e((string) ($c['level'] ?: '—')) ?><?= $isFinal ? ' <span class="badge text-bg-secondary ms-1">final year</span>' : '' ?></td>
            <td class="text-center"><?= (int) $c['student_count'] ?></td>
            <td class="text-center">
              <?php if ($done > 0): ?>
                <span class="badge text-bg-success"><?= $done ?></span>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/promotions/class/<?= $cid ?>?<?= $yearQs ?>">
                <i class="bi bi-arrow-up-right-circle"></i> <?= $isFinal ? 'Graduate / repeat' : 'Promote' ?>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-transparent fw-semibold">Recent promotion runs</div>
  <div class="table-responsive">
    <table class="table align-middle mb-0 small">
      <thead class="table-light">
        <tr>
          <th>When</th>
          <th>Year</th>
          <th>From class</th>
          <th>Result</th>
          <th>By</th>
          <th class="text-end"></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$batches): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">No promotions have been run yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($batches as $b):
          $parts = [];
          if ((int) $b['n_promote'])  $parts[] = (int) $b['n_promote'] . ' promoted';
          if ((int) $b['n_repeat'])   $parts[] = (int) $b['n_repeat'] . ' repeating';
          if ((int) $b['n_demote'])   $parts[] = (int) $b['n_demote'] . ' moved down';
          if ((int) $b['n_graduate']) $parts[] = (int) $b['n_graduate'] . ' graduated';
          $active = (int) $b['n_active'] > 0;
        ?>
          <tr class="<?= $active ? '' : 'text-muted' ?>">
            <td class="text-nowrap"><?= View::e(date('j M Y, H:i', strtotime((string) $b['created_at']))) ?></td>
            <td><?= View::e((string) $b['academic_year']) ?></td>
            <td><?= View::e(mb_strtoupper((string) ($b['from_class_name'] ?? '—'), 'UTF-8')) ?></td>
            <td><?= View::e(implode(', ', $parts)) ?></td>
            <td><?= View::e((string) ($b['performed_by_name'] ?? '—')) ?></td>
            <td class="text-end">
              <?php if ($active): ?>
                <form method="post" action="<?= $base ?>/promotions/undo/<?= View::e((string) $b['batch_id']) ?>" class="d-inline"
                      data-confirm="Undo this run and put these students back in <?= View::e((string) ($b['from_class_name'] ?? 'their old class')) ?>?">
                  <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                  <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise"></i> Undo</button>
                </form>
              <?php else: ?>
                <span class="badge text-bg-light">undone</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
