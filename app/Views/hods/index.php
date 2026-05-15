<?php
use App\Core\View;
$layout = 'app';
$title  = 'Heads of Department';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="bi bi-mortarboard-fill"></i> Heads of Department</h4>
    <p class="text-muted small mb-0">
      HOD accounts sign in at <code class="small"><?= $base ?>/login</code> (same page as everyone else) and can enter marks for every subject across Form&nbsp;1–4.
    </p>
  </div>
  <a class="btn btn-primary" href="<?= $base ?>/hods/create">
    <i class="bi bi-plus-lg"></i> Add HOD
  </a>
</div>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
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
            <td colspan="6" class="text-center text-muted py-4">
              No HOD accounts yet. Click <strong>Add HOD</strong> to create one.
            </td>
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
                <span class="badge bg-success-subtle text-success-emphasis">Active</span>
              <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary-emphasis">Disabled</span>
              <?php endif; ?>
            </td>
            <td class="text-muted small"><?= View::e(substr((string) $h['created_at'], 0, 10)) ?></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/hods/<?= (int) $h['id'] ?>/edit">
                <i class="bi bi-pencil"></i>
              </a>
              <form class="d-inline" method="post"
                    action="<?= $base ?>/hods/<?= (int) $h['id'] ?>/delete"
                    data-confirm="Delete this HOD account? They will no longer be able to sign in.">
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
