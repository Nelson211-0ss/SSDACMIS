<?php use App\Core\View; $layout = 'app'; $title = 'Attendance'; ?>
<h4 class="mb-3"><i class="bi bi-calendar-check"></i> Attendance</h4>

<form class="card border-0 shadow-sm mb-3" method="get" action="<?= $base ?>/attendance">
  <div class="card-body row g-3 align-items-end">
    <div class="col-md-5">
      <label class="form-label">Class</label>
      <select name="class_id" class="form-select" required>
        <?php foreach ($classes as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $classId == $c['id'] ? 'selected' : '' ?>><?= View::e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Date</label>
      <input type="date" name="date" class="form-control" value="<?= View::e($date) ?>">
    </div>
    <div class="col-md-3">
      <button class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Load</button>
    </div>
  </div>
</form>

<form method="post" action="<?= $base ?>/attendance">
  <input type="hidden" name="_csrf" value="<?= $csrf ?>">
  <input type="hidden" name="class_id" value="<?= (int)$classId ?>">
  <input type="hidden" name="date" value="<?= View::e($date) ?>">

  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table mb-0">
        <thead class="table-light"><tr><th>Adm.</th><th>Student</th><th>Status</th></tr></thead>
        <tbody>
        <?php if (empty($students)): ?>
          <tr><td colspan="3" class="text-center text-muted py-4">No students in this class.</td></tr>
        <?php else: foreach ($students as $s): $cur = $existing[(int)$s['id']] ?? 'present'; ?>
          <tr>
            <td><?= View::e($s['admission_no']) ?></td>
            <td><?= View::e($s['first_name'].' '.$s['last_name']) ?></td>
            <td>
              <?php foreach (['present','absent','late'] as $opt): ?>
                <label class="form-check form-check-inline">
                  <input class="form-check-input" type="radio" name="status[<?= (int)$s['id'] ?>]" value="<?= $opt ?>" <?= $cur === $opt ? 'checked' : '' ?>>
                  <span class="form-check-label text-capitalize"><?= $opt ?></span>
                </label>
              <?php endforeach; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if (!empty($students)): ?>
    <div class="mt-3"><button class="btn btn-primary"><i class="bi bi-save"></i> Save Attendance</button></div>
  <?php endif; ?>
</form>
