<?php
use App\Core\View;
$layout = 'app';
$title  = 'Schools';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="bi bi-building"></i> Schools</h4>
    <p class="text-muted small mb-0">Manage all schools in this installation. Each school gets its own data, staff, and students.</p>
  </div>
  <a class="btn btn-primary" href="<?= $base ?>/schools/create">
    <i class="bi bi-plus-lg"></i> Add School
  </a>
</div>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>School</th>
          <th>Code</th>
          <th>Email</th>
          <th>Admins</th>
          <th>Status</th>
          <th>Created</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($schools)): ?>
          <tr>
            <td colspan="7" class="text-center text-muted py-4">
              No schools yet. Click <strong>Add School</strong> to create the first one.
            </td>
          </tr>
        <?php else: foreach ($schools as $s): ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= View::e($s['name']) ?></div>
              <?php if (!empty($s['phone'])): ?>
                <small class="text-muted"><?= View::e($s['phone']) ?></small>
              <?php endif; ?>
            </td>
            <td><code class="small"><?= View::e($s['code']) ?></code></td>
            <td class="small"><?= View::e($s['email'] ?: '—') ?></td>
            <td>
              <span class="badge bg-primary-subtle text-primary-emphasis">
                <?= (int) $s['admin_count'] ?> admin<?= $s['admin_count'] == 1 ? '' : 's' ?>
              </span>
            </td>
            <td>
              <?php if ($s['status'] === 'active'): ?>
                <span class="badge bg-success-subtle text-success-emphasis">Active</span>
              <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary-emphasis">Inactive</span>
              <?php endif; ?>
            </td>
            <td class="text-muted small"><?= View::e(substr((string) $s['created_at'], 0, 10)) ?></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-secondary" href="<?= $base ?>/schools/<?= (int) $s['id'] ?>">
                <i class="bi bi-eye"></i>
              </a>
              <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/schools/<?= (int) $s['id'] ?>/edit">
                <i class="bi bi-pencil"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
