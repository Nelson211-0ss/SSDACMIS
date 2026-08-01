<?php
use App\Core\View;
$layout = 'app';
$title = 'Enter Marks';
$dual = !empty($dualEntry);
$classLevel = trim((string) ($class['level'] ?? ''));
$isUpperForm = ($classLevel === 'Form 3' || $classLevel === 'Form 4');
$subjectCat = (string) ($subject['category'] ?? '');
$streamScoped = $isUpperForm && in_array($subjectCat, ['science', 'arts'], true);
$tiersJson = json_encode($gradingTiers ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <h4 class="mb-1"><i class="bi bi-pencil-square"></i>
      <?= View::e($subject['name']) ?>
      <span class="text-muted fw-normal">·</span>
      <?= View::e($class['name']) ?>
    </h4>
    <div class="small text-muted">
      <?= View::e($year) ?> &middot; <?= View::e($term) ?>
      <?php if (!$dual): ?>
        &middot; <?= View::e($exams[$examType]) ?>
      <?php else: ?>
        &middot; <abbr title="Mid-term" class="text-decoration-none">MT</abbr>
        &amp; <abbr title="End of term" class="text-decoration-none">EOT</abbr>
      <?php endif; ?>
    </div>
  </div>
  <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?><?= $portalPrefix ?>/marks?year=<?= rawurlencode($year) ?>&term=<?= rawurlencode($term) ?>">
    <i class="bi bi-arrow-left"></i> Back
  </a>
</div>

<?php if ($streamScoped): ?>
  <div class="msheet-stream-note">
    <i class="bi bi-funnel-fill"></i>
    <span>Showing only <strong class="text-capitalize"><?= View::e($subjectCat) ?></strong> stream students for this Form 3/4 subject.</span>
  </div>
<?php endif; ?>

<form method="get" action="<?= $base ?><?= $portalPrefix ?>/marks/entry" class="card border-0 shadow-sm mb-3" data-sheet-reload>
  <input type="hidden" name="class_id"   value="<?= (int) $class['id'] ?>">
  <input type="hidden" name="subject_id" value="<?= (int) $subject['id'] ?>">
  <div class="card-body row g-3 align-items-end">
    <div class="col-md-3">
      <label class="form-label">Academic year <span class="text-danger">*</span></label>
      <select name="year" class="form-select" required>
        <?php foreach ($years as $y): ?>
          <option value="<?= View::e($y) ?>" <?= $y === $year ? 'selected' : '' ?>><?= View::e($y) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Term <span class="text-danger">*</span></label>
      <select name="term" class="form-select" required>
        <?php foreach ($terms as $t): ?>
          <option <?= $t === $term ? 'selected' : '' ?>><?= View::e($t) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Exam</label>
      <select name="exam_type" class="form-select">
        <?php foreach ($exams as $k => $label): ?>
          <option value="<?= $k ?>" <?= $k === $examType ? 'selected' : '' ?>><?= View::e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="form-text small">Filters which mark fields are shown below.</div>
    </div>
    <div class="col-md-3">
      <button class="btn btn-outline-primary w-100"><i class="bi bi-arrow-clockwise"></i> Reload</button>
    </div>
  </div>
</form>

<?php if (empty($students)): ?>
  <div class="alert alert-info">No students in <?= View::e($class['name']) ?> yet.</div>
<?php elseif ($dual): ?>
  <form id="marks-entry-form-dual" method="post" action="<?= $base ?><?= $portalPrefix ?>/marks"
        data-autosave-url="<?= $base ?><?= $portalPrefix ?>/marks/autosave-cell">
    <input type="hidden" name="_csrf"         value="<?= $csrf ?>">
    <input type="hidden" name="class_id"      value="<?= (int) $class['id'] ?>">
    <input type="hidden" name="subject_id"    value="<?= (int) $subject['id'] ?>">
    <input type="hidden" name="year"          value="<?= View::e($year) ?>">
    <input type="hidden" name="term"          value="<?= View::e($term) ?>">
    <input type="hidden" name="dual_exam"     value="1">

    <div class="card border-0 shadow-sm marks-sheet-card">
      <div class="marks-sheet-toolbar">
        <div class="marks-sheet-toolbar__row marks-sheet-toolbar__row--primary">
          <div class="d-flex align-items-center gap-2 flex-wrap" style="min-width:0;">
            <div class="marks-sheet-search">
              <i class="bi bi-search"></i>
              <input type="text" class="form-control form-control-sm" data-sheet-search autocomplete="off"
                     placeholder="Name or admission no."
                     title="Find a student by name or admission number">
              <button type="button" class="marks-sheet-search__clear" data-sheet-search-clear aria-label="Clear search">
                <i class="bi bi-x-lg"></i>
              </button>
            </div>
            <span class="marks-sheet-search__count" data-sheet-search-count></span>
          </div>
          <div class="marks-sheet-toolbar__status">
            <span class="marks-sheet-progress" data-sheet-progress>
              <i class="bi bi-list-check"></i>
              <span data-progress-count>0 / 0 entered</span>
              <span class="marks-sheet-progress__bar"><span class="marks-sheet-progress__fill" style="width:0%"></span></span>
            </span>
            <span class="marks-sheet-unsaved" data-sheet-unsaved><i class="bi bi-circle-fill"></i> <span data-sheet-unsaved-text>All changes saved</span></span>
          </div>
        </div>
        <div class="marks-sheet-toolbar__row">
          <div class="marks-sheet-fill">
            <span class="marks-sheet-fill__label">Fill blank</span>
            <select class="form-select form-select-sm" style="width:6rem" data-sheet-fill-col>
              <option value="mid">Mid</option>
              <option value="end">End</option>
            </select>
            <span class="marks-sheet-fill__label">with</span>
            <input type="text" inputmode="decimal" class="form-control form-control-sm" data-sheet-fill-value placeholder="e.g. 20">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-sheet-fill-btn>Apply</button>
          </div>
        </div>
      </div>
      <div class="marks-sheet-scroll">
        <table class="marks-sheet">
          <thead>
            <tr>
              <th class="msheet-sticky-1">#</th>
              <th class="msheet-sticky-2">Admission</th>
              <th class="msheet-sticky-3">Student</th>
              <th class="text-center" title="Mid-term (max 30)">MT</th>
              <th class="text-center">Gr</th>
              <th class="text-center" title="End of term (max 70)">EOT</th>
              <th class="text-center">Gr</th>
              <th class="text-center">Total</th>
              <th class="text-center">Gr</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($students as $i => $st):
              $sid = (int) $st['id'];
              $valM = $existingMid[$sid] ?? '';
              $valE = $existingEnd[$sid] ?? '';
            ?>
              <tr data-row="<?= $i ?>"
                  data-search="<?= View::e(mb_strtolower($st['first_name'] . ' ' . $st['last_name'] . ' ' . $st['admission_no'])) ?>">
                <td class="msheet-sticky-1 msheet-row-num"><?= $i + 1 ?></td>
                <td class="msheet-sticky-2 font-monospace small"><?= View::e($st['admission_no']) ?></td>
                <td class="msheet-sticky-3"><?= View::e($st['first_name'] . ' ' . $st['last_name']) ?></td>
                <td>
                  <input type="text" inputmode="decimal" autocomplete="off"
                         class="msheet-input score-mid"
                         data-row="<?= $i ?>" data-col="mid"
                         data-student-id="<?= $sid ?>" data-subject-id="<?= (int) $subject['id'] ?>" data-exam-type="midterm"
                         name="scores_mid[<?= $sid ?>]"
                         value="<?= $valM !== '' ? View::e(rtrim(rtrim(number_format((float) $valM, 2, '.', ''), '0'), '.')) : '' ?>"
                         placeholder="—" aria-label="Mid-term mark, max 30">
                  <span class="msheet-cell-err"></span>
                </td>
                <td class="text-center"><span class="badge bg-secondary grade-mid">—</span></td>
                <td>
                  <input type="text" inputmode="decimal" autocomplete="off"
                         class="msheet-input score-end"
                         data-row="<?= $i ?>" data-col="end"
                         data-student-id="<?= $sid ?>" data-subject-id="<?= (int) $subject['id'] ?>" data-exam-type="endterm"
                         name="scores_end[<?= $sid ?>]"
                         value="<?= $valE !== '' ? View::e(rtrim(rtrim(number_format((float) $valE, 2, '.', ''), '0'), '.')) : '' ?>"
                         placeholder="—" aria-label="End of term mark, max 70">
                  <span class="msheet-cell-err"></span>
                </td>
                <td class="text-center"><span class="badge bg-secondary grade-end">—</span></td>
                <td class="text-center font-monospace small"><span class="score-total-val">—</span></td>
                <td class="text-center"><span class="badge bg-secondary grade-total">—</span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer d-flex justify-content-end gap-2">
        <div class="small text-muted me-auto">MT ≤ 30 · EOT ≤ 70 · saves automatically as you type · leave blank to skip · ↑↓ or Enter moves rows</div>
        <button type="submit" class="btn btn-primary" data-sheet-submit><i class="bi bi-save"></i> Save marks</button>
      </div>
    </div>
  </form>

  <script src="<?= View::asset($base, 'assets/js/academic-marks.js') ?>"></script>
  <script>
  SSDACMIS.academicMarks.initDualSheet(document.getElementById('marks-entry-form-dual'), { tiers: <?= $tiersJson ?> });
  </script>
<?php else: ?>
  <?php
    $examMax = ($examType === 'midterm') ? 30 : 70;
    $examHint = ($examType === 'midterm') ? 'Mid-term — maximum 30 marks' : 'End-of-term — maximum 70 marks';
  ?>
  <form id="marks-entry-form-single" method="post" action="<?= $base ?><?= $portalPrefix ?>/marks"
        data-autosave-url="<?= $base ?><?= $portalPrefix ?>/marks/autosave-cell">
    <input type="hidden" name="_csrf"      value="<?= $csrf ?>">
    <input type="hidden" name="class_id"   value="<?= (int) $class['id'] ?>">
    <input type="hidden" name="subject_id" value="<?= (int) $subject['id'] ?>">
    <input type="hidden" name="year"       value="<?= View::e($year) ?>">
    <input type="hidden" name="term"       value="<?= View::e($term) ?>">
    <input type="hidden" name="exam_type"  value="<?= View::e($examType) ?>">

    <div class="card border-0 shadow-sm marks-sheet-card">
      <div class="marks-sheet-toolbar">
        <div class="marks-sheet-toolbar__row marks-sheet-toolbar__row--primary">
          <div class="d-flex align-items-center gap-2 flex-wrap" style="min-width:0;">
            <div class="marks-sheet-search">
              <i class="bi bi-search"></i>
              <input type="text" class="form-control form-control-sm" data-sheet-search autocomplete="off"
                     placeholder="Name or admission no."
                     title="Find a student by name or admission number">
              <button type="button" class="marks-sheet-search__clear" data-sheet-search-clear aria-label="Clear search">
                <i class="bi bi-x-lg"></i>
              </button>
            </div>
            <span class="marks-sheet-search__count" data-sheet-search-count></span>
          </div>
          <div class="marks-sheet-toolbar__status">
            <span class="marks-sheet-progress" data-sheet-progress>
              <i class="bi bi-list-check"></i>
              <span data-progress-count>0 / 0 entered</span>
              <span class="marks-sheet-progress__bar"><span class="marks-sheet-progress__fill" style="width:0%"></span></span>
            </span>
            <span class="marks-sheet-unsaved" data-sheet-unsaved><i class="bi bi-circle-fill"></i> <span data-sheet-unsaved-text>All changes saved</span></span>
          </div>
        </div>
        <div class="marks-sheet-toolbar__row">
          <div class="marks-sheet-fill">
            <span class="marks-sheet-fill__label">Fill blank cells with</span>
            <input type="text" inputmode="decimal" class="form-control form-control-sm" data-sheet-fill-value placeholder="e.g. 20">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-sheet-fill-btn>Apply</button>
          </div>
        </div>
      </div>
      <div class="marks-sheet-scroll">
        <table class="marks-sheet">
          <thead>
            <tr>
              <th class="msheet-sticky-1">#</th>
              <th class="msheet-sticky-2">Admission</th>
              <th class="msheet-sticky-3">Student</th>
              <th class="text-center">Score (max <?= (int) $examMax ?>)</th>
              <th class="text-center">Grade</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($students as $i => $st):
              $val = $existing[(int) $st['id']] ?? '';
            ?>
              <tr data-row="<?= $i ?>"
                  data-search="<?= View::e(mb_strtolower($st['first_name'] . ' ' . $st['last_name'] . ' ' . $st['admission_no'])) ?>">
                <td class="msheet-sticky-1 msheet-row-num"><?= $i + 1 ?></td>
                <td class="msheet-sticky-2 font-monospace small"><?= View::e($st['admission_no']) ?></td>
                <td class="msheet-sticky-3"><?= View::e($st['first_name'] . ' ' . $st['last_name']) ?></td>
                <td>
                  <input type="text" inputmode="decimal" autocomplete="off"
                         class="msheet-input score-input"
                         data-row="<?= $i ?>" data-col="score"
                         data-student-id="<?= (int) $st['id'] ?>" data-subject-id="<?= (int) $subject['id'] ?>" data-exam-type="<?= View::e($examType) ?>"
                         name="scores[<?= (int) $st['id'] ?>]"
                         title="<?= View::e($examHint) ?>"
                         value="<?= $val !== '' ? View::e(rtrim(rtrim(number_format((float) $val, 2, '.', ''), '0'), '.')) : '' ?>"
                         placeholder="—" aria-label="<?= View::e($examHint) ?>">
                  <span class="msheet-cell-err"></span>
                </td>
                <td class="text-center"><span class="badge bg-secondary grade-badge">—</span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer d-flex justify-content-end gap-2">
        <div class="small text-muted me-auto"><?= View::e($exams[$examType]) ?> · saves automatically as you type · leave blank to skip · ↑↓ or Enter moves rows</div>
        <button type="submit" class="btn btn-primary" data-sheet-submit><i class="bi bi-save"></i> Save marks</button>
      </div>
    </div>
  </form>

  <script src="<?= View::asset($base, 'assets/js/academic-marks.js') ?>"></script>
  <script>
  SSDACMIS.academicMarks.initSingleSheet(document.getElementById('marks-entry-form-single'), {
    examType: <?= json_encode($examType, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    tiers: <?= $tiersJson ?>
  });
  </script>
<?php endif; ?>
