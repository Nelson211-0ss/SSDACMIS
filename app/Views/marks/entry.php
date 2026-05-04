<?php
use App\Core\View;
$layout = 'app';
$title = 'Enter Marks';
$dual = !empty($dualEntry);
$classLevel = trim((string) ($class['level'] ?? ''));
$isUpperForm = ($classLevel === 'Form 3' || $classLevel === 'Form 4');
$subjectCat = (string) ($subject['category'] ?? '');
$streamScoped = $isUpperForm && in_array($subjectCat, ['science', 'arts'], true);
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
  <div class="alert alert-warning py-2 small d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-funnel-fill"></i>
    <div>
      Form 3 &amp; Form 4 stream subject: showing only <strong class="text-capitalize"><?= View::e($subjectCat) ?></strong> stream students.
      <?= View::e($subjectCat === 'science' ? 'Arts students do not study this subject.' : 'Science students do not study this subject.') ?>
    </div>
  </div>
<?php endif; ?>

<div class="alert alert-info py-2 small d-flex align-items-center gap-2 mb-3">
  <i class="bi bi-calendar2-check"></i>
  <div class="flex-grow-1">
    Recording marks for <strong><?= View::e($year) ?></strong> &middot; <strong><?= View::e($term) ?></strong>
    <?php if (!$dual): ?>&middot; <strong><?= View::e($exams[$examType]) ?></strong><?php endif; ?>.
  </div>
</div>

<form method="get" action="<?= $base ?><?= $portalPrefix ?>/marks/entry" class="card border-0 shadow-sm mb-3">
  <input type="hidden" name="class_id"   value="<?= (int) $class['id'] ?>">
  <input type="hidden" name="subject_id" value="<?= (int) $subject['id'] ?>">
  <div class="card-body row g-3 align-items-end">
    <div class="col-md-<?= $dual ? '4' : '3' ?>">
      <label class="form-label">Academic year <span class="text-danger">*</span></label>
      <select name="year" class="form-select" required>
        <?php foreach ($years as $y): ?>
          <option value="<?= View::e($y) ?>" <?= $y === $year ? 'selected' : '' ?>><?= View::e($y) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-<?= $dual ? '4' : '3' ?>">
      <label class="form-label">Term <span class="text-danger">*</span></label>
      <select name="term" class="form-select" required>
        <?php foreach ($terms as $t): ?>
          <option <?= $t === $term ? 'selected' : '' ?>><?= View::e($t) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if (!$dual): ?>
      <div class="col-md-3">
        <label class="form-label">Exam</label>
        <select name="exam_type" class="form-select">
          <?php foreach ($exams as $k => $label): ?>
            <option value="<?= $k ?>" <?= $k === $examType ? 'selected' : '' ?>><?= View::e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>
    <div class="col-md-<?= $dual ? '4' : '3' ?>">
      <button class="btn btn-outline-primary w-100"><i class="bi bi-arrow-clockwise"></i> Reload</button>
    </div>
  </div>
</form>

<?php if (empty($students)): ?>
  <div class="alert alert-info">No students in <?= View::e($class['name']) ?> yet.</div>
<?php elseif ($dual): ?>
  <form id="marks-entry-form-dual" method="post" action="<?= $base ?><?= $portalPrefix ?>/marks">
    <input type="hidden" name="_csrf"         value="<?= $csrf ?>">
    <input type="hidden" name="class_id"      value="<?= (int) $class['id'] ?>">
    <input type="hidden" name="subject_id"    value="<?= (int) $subject['id'] ?>">
    <input type="hidden" name="year"          value="<?= View::e($year) ?>">
    <input type="hidden" name="term"          value="<?= View::e($term) ?>">
    <input type="hidden" name="dual_exam"     value="1">

    <div class="card border-0 shadow-sm">
      <div class="table-responsive">
        <table class="table mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th style="width:5%">#</th>
              <th>Admission #</th>
              <th>Student</th>
              <th style="width:11%" class="text-center" title="Mid-term (max 30)">
                <abbr title="Mid-term — maximum 30 marks">MT</abbr>
              </th>
              <th style="width:7%" class="text-center small text-muted">∑</th>
              <th style="width:11%" class="text-center" title="End of term (max 70)">
                <abbr title="End of term — maximum 70 marks">EOT</abbr>
              </th>
              <th style="width:7%" class="text-center small text-muted">∑</th>
              <th style="width:9%" class="text-center">Total</th>
              <th style="width:7%" class="text-center small">Gr</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($students as $i => $st):
              $sid = (int) $st['id'];
              $valM = $existingMid[$sid] ?? '';
              $valE = $existingEnd[$sid] ?? '';
            ?>
              <tr>
                <td class="text-muted small"><?= $i + 1 ?></td>
                <td><?= View::e($st['admission_no']) ?></td>
                <td><?= View::e($st['first_name'] . ' ' . $st['last_name']) ?></td>
                <td>
                  <input type="number" min="0" max="30" step="0.01"
                         class="form-control form-control-sm score-mid"
                         name="scores_mid[<?= $sid ?>]"
                         value="<?= $valM !== '' ? View::e(rtrim(rtrim(number_format((float) $valM, 2, '.', ''), '0'), '.')) : '' ?>"
                         placeholder="—" aria-label="Mid-term mark">
                </td>
                <td class="text-center"><span class="badge bg-secondary grade-mid">—</span></td>
                <td>
                  <input type="number" min="0" max="70" step="0.01"
                         class="form-control form-control-sm score-end"
                         name="scores_end[<?= $sid ?>]"
                         value="<?= $valE !== '' ? View::e(rtrim(rtrim(number_format((float) $valE, 2, '.', ''), '0'), '.')) : '' ?>"
                         placeholder="—" aria-label="End of term mark">
                </td>
                <td class="text-center"><span class="badge bg-secondary grade-end">—</span></td>
                <td class="text-center font-monospace small"><span class="score-total-val">—</span></td>
                <td class="text-center"><span class="badge bg-secondary grade-total">—</span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="small text-muted">
          Mid-term ≤ 30 · End-of-term ≤ 70 · Subject total = Mid + End (max 100). Leave blank to skip a cell.
        </div>
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save marks</button>
      </div>
    </div>
  </form>

  <script src="<?= View::asset($base, 'assets/js/academic-marks.js') ?>"></script>
  <script>
  SSDACMIS.academicMarks.wireDualExamRows(document.getElementById('marks-entry-form-dual'));
  SSDACMIS.academicMarks.attachFormGuard('marks-entry-form-dual', true);
  </script>
<?php else: ?>
  <?php
    $examMax = ($examType === 'midterm') ? 30 : 70;
    $examHint = ($examType === 'midterm') ? 'Mid-term — maximum 30 marks' : 'End-of-term — maximum 70 marks';
  ?>
  <form id="marks-entry-form-single" method="post" action="<?= $base ?><?= $portalPrefix ?>/marks">
    <input type="hidden" name="_csrf"      value="<?= $csrf ?>">
    <input type="hidden" name="class_id"   value="<?= (int) $class['id'] ?>">
    <input type="hidden" name="subject_id" value="<?= (int) $subject['id'] ?>">
    <input type="hidden" name="year"       value="<?= View::e($year) ?>">
    <input type="hidden" name="term"       value="<?= View::e($term) ?>">
    <input type="hidden" name="exam_type"  value="<?= View::e($examType) ?>">

    <div class="card border-0 shadow-sm">
      <div class="table-responsive">
        <table class="table mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th style="width:6%">#</th>
              <th>Admission #</th>
              <th>Student</th>
              <th style="width:18%">Score (max <?= (int) $examMax ?>)</th>
              <th style="width:10%">Preview</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($students as $i => $st):
              $val = $existing[(int) $st['id']] ?? '';
            ?>
              <tr>
                <td class="text-muted small"><?= $i + 1 ?></td>
                <td><?= View::e($st['admission_no']) ?></td>
                <td><?= View::e($st['first_name'] . ' ' . $st['last_name']) ?></td>
                <td>
                  <input type="number" min="0" max="<?= (int) $examMax ?>" step="0.01"
                         class="form-control form-control-sm score-input"
                         name="scores[<?= (int) $st['id'] ?>]"
                         title="<?= View::e($examHint) ?>"
                         value="<?= $val !== '' ? View::e(rtrim(rtrim(number_format((float) $val, 2, '.', ''), '0'), '.')) : '' ?>"
                         placeholder="—">
                </td>
                <td><span class="badge bg-secondary grade-badge">—</span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="small text-muted">
          <?= View::e($exams[$examType]) ?> marks:
          <?= $examType === 'midterm' ? '0–30 only.' : '0–70 only.' ?> Subject letter grade applies to the combined total once both Mid and End are entered (dual sheet).
        </div>
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save marks</button>
      </div>
    </div>
  </form>

  <script src="<?= View::asset($base, 'assets/js/academic-marks.js') ?>"></script>
  <script>
  SSDACMIS.academicMarks.wireSingleExam(document.getElementById('marks-entry-form-single'), <?= json_encode($examType, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
  SSDACMIS.academicMarks.attachFormGuard('marks-entry-form-single', false);
  </script>
<?php endif; ?>
