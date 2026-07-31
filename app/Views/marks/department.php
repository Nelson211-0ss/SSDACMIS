<?php
use App\Core\View;
$layout = 'app';
$title = 'Department Marks Entry';
$catLabel = ['core'=>'Compulsory Core','science'=>'Science','arts'=>'Arts','optional'=>'Optional'];
$catBadge = ['core'=>'bg-primary-subtle text-primary-emphasis','science'=>'bg-success-subtle text-success-emphasis','arts'=>'bg-warning-subtle text-warning-emphasis','optional'=>'bg-secondary-subtle text-secondary-emphasis'];
$catName  = $catLabel[$category] ?? ucfirst($category);
$badge    = $catBadge[$category]  ?? 'bg-secondary';
$existingMid = $existingMid ?? [];
$existingEnd = $existingEnd ?? [];
$tiersJson = json_encode($gradingTiers ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <h4 class="mb-1">
      <i class="bi bi-mortarboard"></i>
      <?= View::e($catName) ?> Department
      <span class="text-muted fw-normal">·</span>
      <?= View::e($class['name']) ?>
    </h4>
    <div class="small text-muted">
      <span class="badge text-capitalize <?= $badge ?>"><?= View::e($category) ?></span>
      &middot; Mid-term &amp; End-term on one sheet
      &middot; <?= View::e($year) ?>
      &middot; <?= View::e($term) ?>
    </div>
  </div>
  <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?><?= $portalPrefix ?>/marks">
    <i class="bi bi-arrow-left"></i> Back
  </a>
</div>

<?php
  $classLevel = trim((string) ($class['level'] ?? ''));
  $isUpperForm = ($classLevel === 'Form 3' || $classLevel === 'Form 4');
  $streamScoped = $isUpperForm && in_array($category, ['science', 'arts'], true);
?>

<?php if ($streamScoped): ?>
  <div class="msheet-stream-note">
    <i class="bi bi-funnel-fill"></i>
    <span>Form 3/4 <strong class="text-capitalize"><?= View::e($category) ?></strong> department: only <strong class="text-capitalize"><?= View::e($category) ?></strong> stream students appear in this matrix.</span>
  </div>
<?php endif; ?>

<form method="get" action="<?= $base ?><?= $portalPrefix ?>/marks/department" class="card border-0 shadow-sm mb-3" data-sheet-reload>
  <input type="hidden" name="class_id" value="<?= (int) $class['id'] ?>">
  <input type="hidden" name="category" value="<?= View::e($category) ?>">
  <div class="card-body row g-3 align-items-end">
    <div class="col-md-4">
      <label class="form-label">Academic year <span class="text-danger">*</span></label>
      <select name="year" class="form-select" required>
        <?php foreach (($years ?? []) as $y): ?>
          <option value="<?= View::e($y) ?>" <?= $y === $year ? 'selected' : '' ?>><?= View::e($y) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Term <span class="text-danger">*</span></label>
      <select name="term" class="form-select" required>
        <?php foreach ($terms as $t): ?>
          <option <?= $t === $term ? 'selected' : '' ?>><?= View::e($t) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <button class="btn btn-outline-primary w-100"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
    </div>
  </div>
</form>

<?php if (empty($subjects)): ?>
  <div class="alert alert-warning">
    There are no subjects in the <strong><?= View::e($catName) ?></strong> department yet.
  </div>
<?php elseif (empty($students)): ?>
  <div class="alert alert-info">No students in <?= View::e($class['name']) ?> yet.</div>
<?php else: ?>
  <form id="marks-dept-form" method="post" action="<?= $base ?><?= $portalPrefix ?>/marks/department">
    <input type="hidden" name="_csrf"     value="<?= $csrf ?>">
    <input type="hidden" name="class_id"  value="<?= (int) $class['id'] ?>">
    <input type="hidden" name="category"  value="<?= View::e($category) ?>">
    <input type="hidden" name="year"      value="<?= View::e($year) ?>">
    <input type="hidden" name="term"      value="<?= View::e($term) ?>">

    <?php if (count($subjects) > 3): ?>
      <div class="d-flex flex-wrap align-items-center gap-2 mb-2 d-print-none">
        <span class="small text-muted">Jump to:</span>
        <?php foreach ($subjects as $sub): ?>
          <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" data-jump-subject="<?= (int) $sub['id'] ?>">
            <?= View::e($sub['name']) ?>
          </button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm marks-sheet-card">
      <div class="marks-sheet-toolbar">
        <div class="marks-sheet-toolbar__left">
          <span class="marks-sheet-progress" data-sheet-progress>
            <i class="bi bi-list-check"></i>
            <span data-progress-count>0 / 0 entered</span>
            <span class="marks-sheet-progress__bar"><span class="marks-sheet-progress__fill" style="width:0%"></span></span>
          </span>
          <span class="marks-sheet-unsaved" data-sheet-unsaved><i class="bi bi-circle-fill" style="font-size:.5rem"></i> Unsaved changes</span>
        </div>
        <div class="small text-muted">Mid ≤ 30 · End ≤ 70 per subject · ↑↓ or Enter moves rows</div>
      </div>
      <div class="marks-sheet-scroll">
        <table class="marks-sheet">
          <thead>
            <tr>
              <th class="msheet-sticky-1">#</th>
              <th class="msheet-sticky-2">Admission</th>
              <th class="msheet-sticky-3">Student</th>
              <?php foreach ($subjects as $sub): ?>
                <th class="text-center" colspan="2" id="subj-head-<?= (int) $sub['id'] ?>">
                  <div class="fw-semibold" style="font-size:.72rem; text-transform:none; letter-spacing:0;"><?= View::e($sub['name']) ?></div>
                  <?php if (!empty($sub['code'])): ?>
                    <div class="text-muted" style="font-size:.62rem; text-transform:none;"><?= View::e($sub['code']) ?></div>
                  <?php endif; ?>
                  <div class="d-flex border-top mt-1 pt-1">
                    <span class="flex-fill text-primary" style="font-size:.65rem; text-transform:none;" title="Max 30">Mid</span>
                    <span class="flex-fill" style="font-size:.65rem; text-transform:none; color:#4f46e5;" title="Max 70">End</span>
                  </div>
                </th>
              <?php endforeach; ?>
              <th class="text-center" title="Average % across subjects entered so far">Avg %</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($students as $i => $st):
              $sid = (int) $st['id'];
              $rowMid = $existingMid[$sid] ?? [];
              $rowEnd = $existingEnd[$sid] ?? [];
            ?>
              <tr data-row="<?= $i ?>">
                <td class="msheet-sticky-1 msheet-row-num"><?= $i + 1 ?></td>
                <td class="msheet-sticky-2 font-monospace small"><?= View::e($st['admission_no']) ?></td>
                <td class="msheet-sticky-3"><?= View::e($st['first_name'] . ' ' . $st['last_name']) ?></td>
                <?php foreach ($subjects as $sub):
                  $subId = (int) $sub['id'];
                  $m = $rowMid[$subId] ?? '';
                  $e = $rowEnd[$subId] ?? '';
                  $md = $m !== '' ? rtrim(rtrim(number_format((float) $m, 2, '.', ''), '0'), '.') : '';
                  $ed = $e !== '' ? rtrim(rtrim(number_format((float) $e, 2, '.', ''), '0'), '.') : '';
                ?>
                  <td>
                    <input type="text" inputmode="decimal" autocomplete="off"
                           class="msheet-input score-mid"
                           data-row="<?= $i ?>" data-col="mid-<?= $subId ?>"
                           name="scores_mid[<?= $sid ?>][<?= $subId ?>]"
                           value="<?= View::e($md) ?>"
                           placeholder="—" aria-label="Mid <?= View::e($sub['name']) ?>">
                  </td>
                  <td>
                    <input type="text" inputmode="decimal" autocomplete="off"
                           class="msheet-input score-end"
                           data-row="<?= $i ?>" data-col="end-<?= $subId ?>"
                           name="scores_end[<?= $sid ?>][<?= $subId ?>]"
                           value="<?= View::e($ed) ?>"
                           placeholder="—" aria-label="End <?= View::e($sub['name']) ?>">
                  </td>
                <?php endforeach; ?>
                <td class="text-center"><span class="badge bg-secondary row-avg">—</span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="small text-muted">
          Leave blank to skip. Results overview updates after save — averages/positions compute from mid-term alone if end-term isn't in yet.
        </div>
        <div class="d-flex gap-2">
          <a class="btn btn-outline-primary btn-sm"
             href="<?= $base ?><?= $portalPrefix ?>/reports/class/<?= (int) $class['id'] ?>/booklet?year=<?= rawurlencode($year) ?>&term=<?= rawurlencode($term) ?>">
            <i class="bi bi-printer"></i> Print class reports
          </a>
          <button type="submit" class="btn btn-primary" data-sheet-submit><i class="bi bi-save"></i> Save all marks</button>
        </div>
      </div>
    </div>
  </form>
<?php endif; ?>

<script src="<?= View::asset($base, 'assets/js/academic-marks.js') ?>"></script>
<script>
SSDACMIS.academicMarks.initDepartmentSheet(document.getElementById('marks-dept-form'), { tiers: <?= $tiersJson ?> });
</script>
