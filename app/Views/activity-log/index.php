<?php
use App\Core\View;
$layout = 'app';
$title  = 'Activity Log';

$actionBadge = [
    'create' => 'bg-success-subtle text-success-emphasis',
    'update' => 'bg-primary-subtle text-primary-emphasis',
    'delete' => 'bg-danger-subtle text-danger-emphasis',
    'export' => 'bg-warning-subtle text-warning-emphasis',
    'login'  => 'bg-info-subtle text-info-emphasis',
    'logout' => 'bg-secondary-subtle text-secondary-emphasis',
    'login_failed' => 'bg-danger-subtle text-danger-emphasis',
    'denied' => 'bg-danger-subtle text-danger-emphasis',
];

$pageTitle    = 'Activity Log';
$pageSubtitle = 'Who did what, when — across ' . ($isSuperAdmin ? 'every school' : 'your school') . '.';
$pageIcon     = 'bi-clock-history';
include dirname(__DIR__) . '/_partials/app_page_header.php';
?>

<div class="app-panel mb-3">
  <form method="get" data-auto-reload action="<?= $base ?>/activity-log" class="row g-2 align-items-end p-3">
    <div class="col-6 col-md-2">
      <label class="form-label small mb-1">Action</label>
      <select name="action" class="form-select form-select-sm">
        <option value="">All</option>
        <?php foreach ($actionTypes as $a): ?>
          <option value="<?= View::e($a) ?>" <?= $action === $a ? 'selected' : '' ?>><?= View::e(ucfirst(str_replace('_', ' ', $a))) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label small mb-1">Entity</label>
      <select name="entity_type" class="form-select form-select-sm">
        <option value="">All</option>
        <?php foreach ($entityTypes as $et): ?>
          <option value="<?= View::e($et) ?>" <?= $entityType === $et ? 'selected' : '' ?>><?= View::e(ucfirst(str_replace('_', ' ', $et))) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label small mb-1">From</label>
      <input type="date" name="from" value="<?= View::e($from) ?>" class="form-control form-control-sm">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label small mb-1">To</label>
      <input type="date" name="to" value="<?= View::e($to) ?>" class="form-control form-control-sm">
    </div>
    <div class="col-12 col-md-4">
      <label class="form-label small mb-1">Search</label>
      <input type="search" name="q" value="<?= View::e($q) ?>" placeholder="User, description or IP" class="form-control form-control-sm">
    </div>
    <?php if ($isSuperAdmin): ?>
    <div class="col-12 col-md-3">
      <label class="form-label small mb-1">School</label>
      <select name="school_id" class="form-select form-select-sm">
        <option value="0">All schools</option>
        <?php foreach ($schools as $sc): ?>
          <option value="<?= (int) $sc['id'] ?>" <?= (int) $filterSchool === (int) $sc['id'] ? 'selected' : '' ?>><?= View::e($sc['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="col-12 col-md-3 d-flex gap-2">
      <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> Filter</button>
      <a href="<?= $base ?>/activity-log" class="btn btn-outline-secondary btn-sm">Reset</a>
    </div>
  </form>
</div>

<div class="app-panel">
  <div class="sa-table-wrap">
    <table class="table table-hover sa-table align-middle mb-0">
      <thead>
        <tr>
          <th>When</th>
          <th>User</th>
          <?php if ($isSuperAdmin): ?><th>School</th><?php endif; ?>
          <th>Action</th>
          <th>Entity</th>
          <th>Description</th>
          <th>IP</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($logs)): ?>
        <tr><td colspan="<?= $isSuperAdmin ? 7 : 6 ?>" class="sa-empty">No activity recorded yet.</td></tr>
      <?php else: foreach ($logs as $log): ?>
        <tr>
          <td class="text-nowrap"><?= View::e(date('d M Y, H:i', strtotime((string) $log['created_at']))) ?></td>
          <td>
            <div class="fw-semibold"><?= View::e($log['user_name'] ?? 'Unknown') ?></div>
            <?php if (!empty($log['role'])): ?>
              <span class="text-muted small text-uppercase"><?= View::e($log['role']) ?></span>
            <?php endif; ?>
          </td>
          <?php if ($isSuperAdmin): ?>
            <td><?= View::e($log['school_name'] ?? '—') ?></td>
          <?php endif; ?>
          <td>
            <span class="badge <?= $actionBadge[$log['action']] ?? 'bg-secondary' ?>">
              <?= View::e(ucfirst(str_replace('_', ' ', (string) $log['action']))) ?>
            </span>
          </td>
          <td>
            <?php if (!empty($log['entity_type'])): ?>
              <?= View::e(ucfirst(str_replace('_', ' ', $log['entity_type']))) ?><?= $log['entity_id'] ? ' #' . (int) $log['entity_id'] : '' ?>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td><?= View::e($log['description'] ?? '') ?></td>
          <td class="text-muted small" title="<?= View::e(trim(($log['request_method'] ?? '') . ' ' . ($log['request_uri'] ?? '') . "
" . ($log['user_agent'] ?? ''))) ?>"><?= View::e($log['ip_address'] ?? '—') ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <?php $qs = $_GET; unset($qs['page']); ?>
    <div class="d-flex justify-content-between align-items-center p-3">
      <span class="text-muted small"><?= (int) $total ?> entries · page <?= (int) $page ?> of <?= (int) $pages ?></span>
      <div class="d-flex gap-2">
        <?php if ($page > 1): ?>
          <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/activity-log?<?= View::e(http_build_query($qs + ['page' => $page - 1])) ?>">Previous</a>
        <?php endif; ?>
        <?php if ($page < $pages): ?>
          <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/activity-log?<?= View::e(http_build_query($qs + ['page' => $page + 1])) ?>">Next</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
