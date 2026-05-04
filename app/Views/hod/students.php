<?php
use App\Core\View;
$layout = 'app';
$title  = 'Students by Class';
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
  <div>
    <h4 class="mb-1"><i class="bi bi-people"></i> Students admitted by class</h4>
    <p class="text-muted mb-0">Read-only roster — Heads of Department can view but not edit student records.</p>
  </div>
  <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/hod">
    <i class="bi bi-arrow-left"></i> Back to dashboard
  </a>
</div>

<form class="card border-0 shadow-sm mb-3" method="get" action="<?= $base ?>/hod/students">
  <div class="card-body row g-3 align-items-end">
    <div class="col-md-6">
      <label class="form-label">Filter by class</label>
      <select name="class_id" class="form-select" onchange="this.form.submit()">
        <option value="0">All classes (<?= (int) $total ?> student<?= $total === 1 ? '' : 's' ?>)</option>
        <?php foreach ($classes as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= $classFilter === (int) $c['id'] ? 'selected' : '' ?>>
            <?= View::e($c['name']) ?>
            (<?= (int) $c['student_count'] ?> student<?= (int) $c['student_count'] === 1 ? '' : 's' ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6 text-md-end">
      <noscript><button class="btn btn-outline-primary"><i class="bi bi-funnel"></i> Apply</button></noscript>
    </div>
  </div>
</form>

<?php if (empty($byClass)): ?>
  <div class="card border-0 shadow-sm">
    <div class="card-body text-center text-muted py-5">
      <i class="bi bi-inbox fs-1 d-block mb-2"></i>
      No students found for the selected class.
    </div>
  </div>
<?php else: foreach ($byClass as $group):
    $cls = $group['students'];
    $level = trim((string) ($group['level'] ?? ''));
    $isUpper = ($level === 'Form 3' || $level === 'Form 4');
    // Stream summary for upper forms
    $sci = 0; $art = 0;
    foreach ($cls as $s) {
        $st = $s['stream'] ?? 'none';
        if ($st === 'science') $sci++;
        elseif ($st === 'arts') $art++;
    }
?>
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div class="d-flex align-items-center gap-2" style="min-width:0;">
        <span class="card-header-icon card-header-icon--blue flex-shrink-0" aria-hidden="true"><i class="bi bi-building-fill"></i></span>
        <div style="min-width:0;">
          <span class="fw-semibold"><?= View::e($group['class_name']) ?></span>
          <?php if ($level): ?><span class="badge bg-light text-dark border ms-1"><?= View::e($level) ?></span><?php endif; ?>
          <span class="text-muted small ms-2"><?= count($cls) ?> student<?= count($cls) === 1 ? '' : 's' ?></span>
          <?php if ($isUpper): ?>
            <span class="badge bg-success-subtle text-success-emphasis ms-1">Science: <?= (int) $sci ?></span>
            <span class="badge bg-warning-subtle text-warning-emphasis ms-1">Arts: <?= (int) $art ?></span>
          <?php endif; ?>
        </div>
      </div>
      <?php if (!empty($group['class_id'])): ?>
        <a class="btn btn-sm btn-outline-primary" href="<?= $base ?><?= $portalPrefix ?>/reports/class/<?= (int) $group['class_id'] ?>" title="Class report">
          <i class="bi bi-file-earmark-text"></i> Class report
        </a>
      <?php endif; ?>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th style="width:3rem">#</th>
            <th>Adm. No.</th>
            <th>Name</th>
            <th>Gender</th>
            <th>Section</th>
            <?php if ($isUpper): ?><th>Stream</th><?php endif; ?>
            <th>Guardian</th>
            <th>Phone</th>
            <th class="text-end">Reports</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($cls as $i => $s):
            $section = $s['section'] ?? 'day';
            $stream  = $s['stream']  ?? 'none';
          ?>
            <tr>
              <td class="text-muted small"><?= $i + 1 ?></td>
              <td class="font-monospace small"><?= View::e($s['admission_no']) ?></td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <?php
                    $av_photo = $s['photo_path'] ?? '';
                    $av_first = $s['first_name'] ?? '';
                    $av_last  = $s['last_name']  ?? '';
                    $av_size  = 32;
                    include dirname(__DIR__) . '/_partials/student_avatar.php';
                  ?>
                  <span><?= View::e($s['first_name'] . ' ' . $s['last_name']) ?></span>
                </div>
              </td>
              <td><span class="badge bg-secondary"><?= View::studentEnumUpper('gender', $s['gender'] ?? '') ?></span></td>
              <td><span class="badge <?= $section === 'boarding' ? 'bg-info-subtle text-info-emphasis' : 'bg-light text-secondary border' ?>"><?= View::studentEnumUpper('section', $section) ?></span></td>
              <?php if ($isUpper): ?>
                <td>
                  <?php if ($stream === 'science'): ?>
                    <span class="badge bg-success-subtle text-success-emphasis"><?= View::studentEnumUpper('stream', 'science') ?></span>
                  <?php elseif ($stream === 'arts'): ?>
                    <span class="badge bg-warning-subtle text-warning-emphasis"><?= View::studentEnumUpper('stream', 'arts') ?></span>
                  <?php else: ?>
                    <span class="text-muted small"><?= View::studentEnumUpper('stream', $stream) ?></span>
                  <?php endif; ?>
                </td>
              <?php endif; ?>
              <td><?= View::e($s['guardian_name'] ?: '—') ?></td>
              <td><?= View::e($s['guardian_phone'] ?: '—') ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-secondary" href="<?= $base ?><?= $portalPrefix ?>/reports/student/<?= (int) $s['id'] ?>" title="Student report card">
                  <i class="bi bi-file-person"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endforeach; endif; ?>
