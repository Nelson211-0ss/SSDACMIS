<?php
use App\Core\View;
$layout = 'app';
$title  = 'Schools';
?>
<div class="page-header">
    <div>
      <h2>Schools</h2>
      <p class="page-header__sub mb-0">Manage every school in this installation — data, branding, and school admins.</p>
    </div>
    <a class="btn btn-primary" href="<?= $base ?>/schools/create">
      <i class="bi bi-plus-lg"></i> Add school
    </a>
  </div>

  <div class="sa-panel">
    <div class="sa-table-wrap">
      <table class="table sa-table align-middle mb-0">
        <thead>
          <tr>
            <th>School</th>
            <th>Code</th>
            <th class="d-none d-md-table-cell">Email</th>
            <th>Admins</th>
            <th>Status</th>
            <th class="d-none d-sm-table-cell">Created</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($schools)): ?>
            <tr>
              <td colspan="7" class="sa-empty">
                <i class="bi bi-buildings d-block"></i>
                <p class="mb-2">No schools yet.</p>
                <a href="<?= $base ?>/schools/create" class="btn btn-sm btn-primary">Add the first school</a>
              </td>
            </tr>
          <?php else: foreach ($schools as $s):
            $isActive = ($s['status'] ?? '') === 'active';
          ?>
            <tr class="<?= !$isActive ? 'sa-table__row--muted' : '' ?>">
              <td>
                <div class="fw-semibold"><?= View::e($s['name']) ?></div>
                <?php if (!empty($s['phone'])): ?>
                  <div class="small text-muted"><?= View::e($s['phone']) ?></div>
                <?php endif; ?>
              </td>
              <td><code class="sa-code"><?= View::e($s['code']) ?></code></td>
              <td class="small text-muted d-none d-md-table-cell"><?= View::e($s['email'] ?: '—') ?></td>
              <td>
                <span class="sa-pill"><?= (int) $s['admin_count'] ?> admin<?= $s['admin_count'] == 1 ? '' : 's' ?></span>
              </td>
              <td>
                <span class="sa-status <?= $isActive ? 'sa-status--on' : 'sa-status--off' ?>">
                  <?= $isActive ? 'Active' : 'Inactive' ?>
                </span>
              </td>
              <td class="text-muted small d-none d-sm-table-cell"><?= View::e(substr((string) $s['created_at'], 0, 10)) ?></td>
              <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-secondary" href="<?= $base ?>/schools/<?= (int) $s['id'] ?>" title="View">
                  <i class="bi bi-eye"></i>
                </a>
                <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/schools/<?= (int) $s['id'] ?>/edit" title="Edit">
                  <i class="bi bi-pencil"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
