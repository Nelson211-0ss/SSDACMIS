<?php
use App\Core\View;
$layout = 'app';
$title  = 'Attendance';

$pageTitle = 'Attendance';
$pageSubtitle = 'Attendance history for the selected date range.';
$pageIcon = 'bi-calendar-check';
include dirname(__DIR__) . '/_partials/app_page_header.php';

$statusMeta = [
    'present' => ['label' => 'Present', 'badge' => 'text-bg-success'],
    'absent'  => ['label' => 'Absent',  'badge' => 'text-bg-danger'],
    'late'    => ['label' => 'Late',    'badge' => 'text-bg-warning'],
];
?>

<?php if (!$student): ?>
  <div class="alert alert-warning"><i class="bi bi-info-circle"></i> Student not found.</div>
<?php else: ?>
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <div class="d-flex justify-content-between flex-wrap gap-2 align-items-end">
        <div>
          <a class="btn btn-outline-secondary btn-sm mb-2" href="<?= $base ?>/parent"><i class="bi bi-arrow-left"></i> Back</a>
          <div class="fw-semibold"><?= View::e(trim($student['first_name'] . ' ' . $student['last_name'])) ?></div>
          <div class="small text-muted">
            <span class="badge-soft"><?= View::e($student['admission_no']) ?></span>
            <?= $student['class_name'] ? ' · ' . View::e($student['class_name']) : '' ?>
          </div>
        </div>
        <form method="get" class="d-flex align-items-end flex-wrap gap-2">
          <div>
            <label class="form-label small mb-1">From</label>
            <input type="date" name="from" class="form-control form-control-sm" value="<?= View::e($from) ?>">
          </div>
          <div>
            <label class="form-label small mb-1">To</label>
            <input type="date" name="to" class="form-control form-control-sm" value="<?= View::e($to) ?>">
          </div>
          <button type="submit" class="btn btn-outline-primary btn-sm">Reload</button>
        </form>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-3">
      <div class="text-muted small">Present</div>
      <div class="h5 mb-0 text-success"><?= (int) $tally['present'] ?></div>
    </div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-3">
      <div class="text-muted small">Absent</div>
      <div class="h5 mb-0 text-danger"><?= (int) $tally['absent'] ?></div>
    </div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body py-3">
      <div class="text-muted small">Late</div>
      <div class="h5 mb-0 text-warning"><?= (int) $tally['late'] ?></div>
    </div></div></div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white"><strong class="mb-0"><?= View::e($from) ?> &ndash; <?= View::e($to) ?></strong></div>
    <div class="table-responsive">
      <table class="table mb-0 align-middle">
        <thead class="table-light">
          <tr><th>Date</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php if (empty($records)): ?>
            <tr><td colspan="2" class="text-center text-muted py-4">No attendance recorded in this range.</td></tr>
          <?php else: foreach ($records as $r):
            $meta = $statusMeta[$r['status']] ?? ['label' => ucfirst((string) $r['status']), 'badge' => 'text-bg-secondary'];
          ?>
            <tr>
              <td><?= View::e($r['date']) ?></td>
              <td><span class="badge <?= $meta['badge'] ?>"><?= View::e($meta['label']) ?></span></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
