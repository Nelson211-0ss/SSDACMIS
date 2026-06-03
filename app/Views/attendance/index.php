<?php use App\Core\View;
$layout = 'app';
$title = 'Attendance';

$pageTitle = 'Attendance';
$pageSubtitle = 'Mark daily presence by class.';
$pageIcon = 'bi-calendar-check';
include dirname(__DIR__) . '/_partials/app_page_header.php';
?>

<form class="card mb-3 filter-panel" method="get" action="<?= $base ?>/attendance">
  <div class="card-body row g-3 align-items-end">
    <div class="col-md-5">
      <label class="form-label fw-semibold">Class</label>
      <select name="class_id" class="form-select" required>
        <?php foreach ($classes as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $classId == $c['id'] ? 'selected' : '' ?>><?= View::e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label fw-semibold">Date</label>
      <input type="date" name="date" class="form-control" value="<?= View::e($date) ?>">
    </div>
    <div class="col-md-3">
      <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Load</button>
    </div>
  </div>
</form>

<form method="post" action="<?= $base ?>/attendance">
  <input type="hidden" name="_csrf" value="<?= $csrf ?>">
  <input type="hidden" name="class_id" value="<?= (int)$classId ?>">
  <input type="hidden" name="date" value="<?= View::e($date) ?>">

  <div class="app-panel">
    <div class="sa-table-wrap">
      <table class="table table-hover sa-table mb-0">
        <thead>
          <tr><th>Adm.</th><th>Student</th><th>Status</th></tr>
        </thead>
        <tbody>
        <?php if (empty($students)): ?>
          <tr><td colspan="3" class="sa-empty">No students in this class.</td></tr>
        <?php else: foreach ($students as $s): $cur = $existing[(int)$s['id']] ?? 'present'; ?>
          <tr>
            <td><code class="sa-code"><?= View::e($s['admission_no']) ?></code></td>
            <td class="fw-semibold"><?= View::e($s['first_name'].' '.$s['last_name']) ?></td>
            <td>
              <select name="status[<?= (int)$s['id'] ?>]" class="form-select form-select-sm" style="max-width:10rem">
                <option value="present" <?= $cur==='present'?'selected':'' ?>>Present</option>
                <option value="absent"  <?= $cur==='absent'?'selected':'' ?>>Absent</option>
                <option value="late"    <?= $cur==='late'?'selected':'' ?>>Late</option>
              </select>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if (!empty($students)): ?>
    <div class="mt-3">
      <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save attendance</button>
    </div>
  <?php endif; ?>
</form>
