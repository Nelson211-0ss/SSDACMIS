<?php
use App\Core\View;
use App\Services\FeesService;
$layout = 'app';
$title  = 'Family Dashboard';

$pageTitle = 'Family dashboard';
$pageSubtitle = 'Report cards, fees and attendance for your child' . (count($children) === 1 ? '' : 'ren') . '.';
$pageIcon = 'bi-house-heart';
include dirname(__DIR__) . '/_partials/app_page_header.php';
?>

<?php if (empty($children)): ?>
  <div class="alert alert-warning">
    <i class="bi bi-info-circle"></i>
    No children are linked to your account yet. Please contact the school administrator.
  </div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($children as $c):
      $sid    = (int) $c['id'];
      $status = $feeStatusByStudent[$sid] ?? null;
      $av_photo = $c['photo_path'] ?? '';
      $av_first = $c['first_name'] ?? '';
      $av_last  = $c['last_name'] ?? '';
      $av_size  = 48;
      $av_shape = 'circle';
    ?>
      <div class="col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex align-items-center gap-2 mb-3">
              <?php include dirname(__DIR__) . '/_partials/student_avatar.php'; ?>
              <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold text-truncate"><?= View::e(trim($av_first . ' ' . $av_last)) ?></div>
                <div class="small text-muted">
                  <span class="badge-soft"><?= View::e($c['admission_no']) ?></span>
                  <?php if (!empty($c['class_name'])): ?>
                    &middot; <?= View::e($c['class_name']) ?>
                  <?php endif; ?>
                </div>
              </div>
              <?php if ($status !== null): ?>
                <span class="badge <?= FeesService::statusBadgeClass($status) ?>"><?= View::e(FeesService::statusLabel($status)) ?></span>
              <?php endif; ?>
            </div>
            <div class="parent-child-actions">
              <a class="btn btn-sm btn-primary" href="<?= $base ?>/parent/reports/student/<?= $sid ?>">
                <i class="bi bi-file-earmark-text"></i> Report card
              </a>
              <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/parent/fees/<?= $sid ?>">
                <i class="bi bi-cash-coin"></i> Fees
              </a>
              <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/parent/attendance/<?= $sid ?>">
                <i class="bi bi-calendar-check"></i> Attendance
              </a>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
