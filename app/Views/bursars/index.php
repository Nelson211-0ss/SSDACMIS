<?php
use App\Core\View;
$layout = 'app';
$title  = 'Bursars';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="bi bi-cash-coin"></i> Bursars</h4>
    <p class="text-muted small mb-0">
      Bursar accounts sign in at <code class="small"><?= $base ?>/login</code> (same page as everyone else) and are taken to the Fees Management module after sign-in.
    </p>
  </div>
  <a class="btn btn-primary" href="<?= $base ?>/bursars/create">
    <i class="bi bi-plus-lg"></i> Add Bursar
  </a>
</div>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
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
            <td colspan="5" class="text-center text-muted py-4">
              No bursar accounts yet. Click <strong>Add Bursar</strong> to create one.
            </td>
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
                <span class="badge bg-success-subtle text-success-emphasis">Active</span>
              <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary-emphasis">Disabled</span>
              <?php endif; ?>
            </td>
            <td class="text-muted small"><?= View::e(substr((string) $b['created_at'], 0, 10)) ?></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/bursars/<?= (int) $b['id'] ?>/edit">
                <i class="bi bi-pencil"></i>
              </a>
              <form class="d-inline" method="post"
                    action="<?= $base ?>/bursars/<?= (int) $b['id'] ?>/delete"
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
