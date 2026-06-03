<?php
use App\Core\View;
$layout = 'app';
$title  = 'Bursars';

$pageTitle = 'Bursars';
$pageSubtitle = 'Bursar accounts use <code class="sa-code">' . View::e($base) . '/login</code> and manage the Fees module after sign-in.';
$pageIcon = 'bi-cash-coin';
$pageActionsHtml = '<a class="btn btn-primary" href="' . View::e($base) . '/bursars/create"><i class="bi bi-plus-lg"></i> Add bursar</a>';
include dirname(__DIR__) . '/_partials/app_page_header.php';
?>

<div class="app-panel">
  <div class="sa-table-wrap">
    <table class="table table-hover sa-table align-middle mb-0">
      <thead>
        <tr>
          <th>Name</th>
          <th>Email (login)</th>
          <th>Status</th>
          <th>Created</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($bursars)): ?>
          <tr>
            <td colspan="5" class="sa-empty">No bursar accounts yet. Click <strong>Add bursar</strong> to create one.</td>
          </tr>
        <?php else: foreach ($bursars as $b): ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= View::e($b['name']) ?></div>
              <span class="badge bg-success-subtle text-success-emphasis mt-1">
                <i class="bi bi-cash-coin"></i> Bursar
              </span>
            </td>
            <td class="font-monospace small"><?= View::e($b['email']) ?></td>
            <td>
              <?php if (($b['status'] ?? 'active') === 'active'): ?>
                <span class="sa-status sa-status--on">Active</span>
              <?php else: ?>
                <span class="sa-status sa-status--off">Disabled</span>
              <?php endif; ?>
            </td>
            <td class="text-muted small"><?= View::e(substr((string) $b['created_at'], 0, 10)) ?></td>
            <td class="text-end text-nowrap">
              <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/bursars/<?= (int) $b['id'] ?>/edit"><i class="bi bi-pencil"></i></a>
              <form class="d-inline" method="post" action="<?= $base ?>/bursars/<?= (int) $b['id'] ?>/delete"
                    data-confirm="Delete this bursar account? They will no longer be able to sign in. Past receipts they recorded are kept.">
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
