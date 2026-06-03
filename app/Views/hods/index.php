<?php
use App\Core\View;
$layout = 'app';
$title  = 'Heads of Department';

$pageTitle = 'Heads of Department';
$pageSubtitle = 'HOD accounts sign in at <code class="sa-code">' . View::e($base) . '/login</code> and enter marks for subjects across Form 1–4.';
$pageIcon = 'bi-mortarboard-fill';
$pageActionsHtml = '<a class="btn btn-primary" href="' . View::e($base) . '/hods/create"><i class="bi bi-plus-lg"></i> Add HOD</a>';
include dirname(__DIR__) . '/_partials/app_page_header.php';
?>

<div class="app-panel">
  <div class="sa-table-wrap">
    <table class="table table-hover sa-table align-middle mb-0">
      <thead>
        <tr>
          <th>Name</th>
          <th>Email (login)</th>
          <th>Department</th>
          <th>Status</th>
          <th>Created</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($hods)): ?>
          <tr>
            <td colspan="6" class="sa-empty">No HOD accounts yet. Click <strong>Add HOD</strong> to create one.</td>
          </tr>
        <?php else: foreach ($hods as $h): ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= View::e($h['name']) ?></div>
              <span class="badge bg-primary-subtle text-primary-emphasis mt-1">
                <i class="bi bi-mortarboard"></i> Head of Department
              </span>
            </td>
            <td class="font-monospace small"><?= View::e($h['email']) ?></td>
            <td>
              <?php if (!empty($h['department'])): ?>
                <span class="badge bg-info-subtle text-info-emphasis"><?= View::e($h['department']) ?></span>
              <?php else: ?>
                <span class="text-muted small">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (($h['status'] ?? 'active') === 'active'): ?>
                <span class="sa-status sa-status--on">Active</span>
              <?php else: ?>
                <span class="sa-status sa-status--off">Disabled</span>
              <?php endif; ?>
            </td>
            <td class="text-muted small"><?= View::e(substr((string) ($h['created_at'] ?? ''), 0, 10)) ?></td>
            <td class="text-end text-nowrap">
              <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/hods/<?= (int) $h['id'] ?>/edit"><i class="bi bi-pencil"></i></a>
              <form class="d-inline" method="post" action="<?= $base ?>/hods/<?= (int) $h['id'] ?>/delete"
                    data-confirm="Remove this HOD account? They will no longer be able to sign in.">
                <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
