<?php
use App\Core\View;

$layout = 'app';
$title  = 'Students with no marks';

$students = $students ?? [];
$classes  = $classes ?? [];
$classId  = (int) ($classId ?? 0);
$count    = count($students);
$className = 'all classes';
foreach ($classes as $c) {
    if ((int) $c['id'] === $classId) {
        $className = (string) $c['name'];
    }
}
$genderLabel = ['male' => 'M', 'female' => 'F', 'other' => 'O'];
?>

<div class="page-header mb-4">
  <div>
    <nav aria-label="breadcrumb" class="mb-2">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= $base ?>/students">Students</a></li>
        <li class="breadcrumb-item active" aria-current="page">No marks</li>
      </ol>
    </nav>
    <h2 class="h4 mb-1"><i class="bi bi-person-dash"></i> Students with no marks</h2>
    <p class="page-header__sub mb-0">
      Students who did not sit the term's exams: no mid-term and no end-of-term mark in any subject.
      Pick the term and class, then delete students who have left.
    </p>
  </div>
</div>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body">
    <form method="get" data-auto-reload action="<?= $base ?>/students/no-marks" class="row g-3 align-items-end">
      <div class="col-sm-4 col-lg-3">
        <label class="form-label fw-semibold" for="nmYear">Year</label>
        <select name="year" id="nmYear" class="form-select">
          <?php foreach (($years ?? []) as $y): ?>
            <option value="<?= View::e($y) ?>" <?= $y === $year ? 'selected' : '' ?>><?= View::e($y) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-4 col-lg-3">
        <label class="form-label fw-semibold" for="nmTerm">Term</label>
        <select name="term" id="nmTerm" class="form-select">
          <?php foreach (($terms ?? []) as $t): ?>
            <option <?= $t === $term ? 'selected' : '' ?>><?= View::e($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-4 col-lg-4">
        <label class="form-label fw-semibold" for="nmClass">Class</label>
        <select name="class_id" id="nmClass" class="form-select">
          <option value="0">All classes</option>
          <?php foreach ($classes as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === $classId ? 'selected' : '' ?>>
              <?= View::e((string) $c['name']) ?><?= !empty($c['level']) ? ' · ' . View::e($c['level']) : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-lg-2">
        <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-funnel"></i> Filter</button>
      </div>
    </form>
  </div>
</div>

<?php if ($count === 0): ?>
  <div class="alert alert-success">
    <i class="bi bi-check-circle"></i>
    Every student in <?= View::e($className) ?> has at least one mark for <?= View::e($term) ?> <?= View::e($year) ?>
    (<?= (int) $scanned ?> checked).
  </div>
<?php else: ?>
  <form method="post" action="<?= $base ?>/students/no-marks/delete" id="noMarksForm"
        data-confirm="Permanently delete the selected students, with their attendance, fees and login accounts? This cannot be undone.">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <input type="hidden" name="year" value="<?= View::e($year) ?>">
    <input type="hidden" name="term" value="<?= View::e($term) ?>">
    <input type="hidden" name="class_id" value="<?= $classId ?>">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
      <div>
        <strong><?= $count ?></strong> of <?= (int) $scanned ?> student<?= $scanned === 1 ? '' : 's' ?> in
        <?= View::e($className) ?> have no marks for <?= View::e($term) ?> <?= View::e($year) ?>.
      </div>
      <div class="small text-muted"><span id="nmSelected">0</span> selected</div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:2.5rem">
                <input type="checkbox" class="form-check-input" id="nmAll" aria-label="Select all students">
              </th>
              <th>Adm. no</th>
              <th>Student</th>
              <th class="text-center">Sex</th>
              <th>Class</th>
              <th>Marks in other terms of <?= View::e($year) ?></th>
              <th>Registered</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($students as $s): ?>
              <?php $sid = (int) $s['id']; ?>
              <tr>
                <td>
                  <input type="checkbox" class="form-check-input nm-pick" name="student_ids[]" value="<?= $sid ?>"
                         id="nm<?= $sid ?>" aria-label="Select <?= View::e($s['first_name'] . ' ' . $s['last_name']) ?>">
                </td>
                <td class="text-muted"><?= View::e((string) $s['admission_no']) ?></td>
                <td>
                  <label for="nm<?= $sid ?>" class="mb-0">
                    <a href="<?= $base ?>/students/<?= $sid ?>"><?= View::e($s['first_name'] . ' ' . $s['last_name']) ?></a>
                  </label>
                </td>
                <td class="text-center"><?= View::e($genderLabel[$s['gender']] ?? '') ?></td>
                <td>
                  <?= View::e($s['class_name']) ?>
                  <?php if (in_array($s['stream'], ['science', 'arts'], true)): ?>
                    <span class="text-muted small"><?= View::e(ucfirst($s['stream'])) ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (empty($s['other_terms'])): ?>
                    <span class="badge text-bg-danger">None this year</span>
                  <?php else: ?>
                    <?php foreach ($s['other_terms'] as $ot): ?>
                      <span class="badge text-bg-light border"><?= View::e($ot) ?></span>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </td>
                <td class="text-muted small"><?= !empty($s['created_at']) ? View::e(date('j M Y', strtotime((string) $s['created_at']))) : '—' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card border-danger border shadow-sm">
      <div class="card-body">
        <p class="small text-secondary mb-3">
          Deleting removes the student record together with their attendance, fees, payments, results and
          student login account. It cannot be undone. A student is skipped if a mark has been entered for
          them since this list was loaded.
          Check <strong>Marks in other terms</strong> first: a student with marks in another term may only
          have missed this exam, and a student registered after the term may simply not have been there yet.
        </p>
        <div class="row g-2 align-items-end">
          <div class="col-sm-6 col-md-4">
            <label for="nmConfirm" class="form-label fw-semibold">Type <code>DELETE</code> to confirm</label>
            <input id="nmConfirm" name="confirm_word" type="text" class="form-control font-monospace"
                   autocomplete="off" spellcheck="false" placeholder="DELETE" required maxlength="10">
          </div>
          <div class="col-sm-6 col-md-4">
            <button type="submit" class="btn btn-danger" id="nmDelete" disabled>
              <i class="bi bi-trash3"></i> Delete selected students
            </button>
          </div>
        </div>
      </div>
    </div>
  </form>

  <script>
  (function () {
    'use strict';
    var form = document.getElementById('noMarksForm');
    if (!form) return;
    var all = document.getElementById('nmAll');
    var picks = Array.prototype.slice.call(form.querySelectorAll('.nm-pick'));
    var out = document.getElementById('nmSelected');
    var btn = document.getElementById('nmDelete');
    var confirmBox = document.getElementById('nmConfirm');

    function refresh() {
      var n = picks.filter(function (p) { return p.checked; }).length;
      out.textContent = n;
      all.checked = n > 0 && n === picks.length;
      all.indeterminate = n > 0 && n < picks.length;
      btn.disabled = n === 0 || confirmBox.value.trim().toUpperCase() !== 'DELETE';
      btn.innerHTML = '<i class="bi bi-trash3"></i> Delete ' + (n > 0 ? n + ' ' : 'selected ') + 'student' + (n === 1 ? '' : 's');
    }

    all.addEventListener('change', function () {
      picks.forEach(function (p) { p.checked = all.checked; });
      refresh();
    });
    picks.forEach(function (p) { p.addEventListener('change', refresh); });
    confirmBox.addEventListener('input', refresh);
    refresh();
  })();
  </script>
<?php endif; ?>
