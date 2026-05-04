<?php use App\Core\View; $layout = 'app'; $title = 'Grades'; ?>
<h4 class="mb-3"><i class="bi bi-bar-chart"></i> Grades</h4>

<?php if (!$isStudent): ?>
  <form class="card border-0 shadow-sm mb-3" method="get" action="<?= $base ?>/grades">
    <div class="card-body row g-3 align-items-end">
      <div class="col-md-9">
        <label class="form-label">Student</label>
        <select name="student_id" class="form-select" required>
          <option value="">— Select student —</option>
          <?php foreach ($students as $s): ?>
            <option value="<?= (int)$s['id'] ?>" <?= $studentId == $s['id'] ? 'selected' : '' ?>>
              <?= View::e($s['admission_no'].' — '.$s['first_name'].' '.$s['last_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3"><button class="btn btn-outline-primary w-100">Load</button></div>
    </div>
  </form>
<?php endif; ?>

<?php if ($studentId && in_array($auth['role'], ['admin','staff'])): ?>
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white d-flex align-items-center">
      <span class="card-header-icon card-header-icon--purple me-2" aria-hidden="true"><i class="bi bi-award"></i></span>
      <strong class="mb-0">Record grade</strong>
    </div>
    <div class="card-body">
      <form method="post" action="<?= $base ?>/grades" class="row g-3 align-items-end">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
        <input type="hidden" name="student_id" value="<?= (int)$studentId ?>">
        <div class="col-md-4">
          <label class="form-label">Subject</label>
          <select name="subject_id" class="form-select" required>
            <?php foreach ($subjects as $sub): ?>
              <option value="<?= (int)$sub['id'] ?>"><?= View::e($sub['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Term</label>
          <select name="term" class="form-select" required>
            <?php foreach ($terms as $t): ?><option><?= View::e((string) $t) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Score (0–100)</label>
          <input type="number" name="score" class="form-control" min="0" max="100" step="0.01" required>
        </div>
        <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-save"></i> Save</button></div>
      </form>
    </div>
  </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table mb-0">
      <thead class="table-light"><tr><th>Subject</th><th>Term</th><th>Score</th><th>Grade</th></tr></thead>
      <tbody>
      <?php if (empty($grades)): ?>
        <tr><td colspan="4" class="text-center text-muted py-4">No grades recorded.</td></tr>
      <?php else: foreach ($grades as $g):
        $score = (float) $g['score'];
        $letter = $score >= 80 ? 'A' : ($score >= 70 ? 'B' : ($score >= 60 ? 'C' : ($score >= 50 ? 'D' : 'F')));
        $color  = $score >= 70 ? 'success' : ($score >= 50 ? 'warning' : 'danger');
      ?>
        <tr>
          <td><?= View::e($g['subject_name']) ?></td>
          <td><?= View::e($g['term']) ?></td>
          <td><?= number_format($score, 1) ?></td>
          <td><span class="badge bg-<?= $color ?>"><?= $letter ?></span></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
