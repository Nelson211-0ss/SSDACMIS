<?php
use App\Core\View;
$layout = 'app';
$title  = $school['name'];
?>
<div class="mb-3 d-flex align-items-center gap-2">
  <a href="<?= $base ?>/schools" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i> Schools
  </a>
  <a href="<?= $base ?>/schools/<?= (int) $school['id'] ?>/edit" class="btn btn-sm btn-outline-primary">
    <i class="bi bi-pencil"></i> Edit
  </a>
</div>

<!-- School info card -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body px-4 py-3">
    <div class="d-flex align-items-start gap-3 flex-wrap">
      <div class="fs-1 text-primary"><i class="bi bi-building"></i></div>
      <div class="flex-grow-1">
        <h4 class="mb-1"><?= View::e($school['name']) ?></h4>
        <div class="d-flex flex-wrap gap-3 text-muted small mt-1">
          <span><i class="bi bi-code-square me-1"></i><?= View::e($school['code']) ?></span>
          <?php if ($school['email']): ?>
            <span><i class="bi bi-envelope me-1"></i><?= View::e($school['email']) ?></span>
          <?php endif; ?>
          <?php if ($school['phone']): ?>
            <span><i class="bi bi-telephone me-1"></i><?= View::e($school['phone']) ?></span>
          <?php endif; ?>
          <?php if ($school['address']): ?>
            <span><i class="bi bi-geo-alt me-1"></i><?= View::e($school['address']) ?></span>
          <?php endif; ?>
          <span>
            <?php if ($school['status'] === 'active'): ?>
              <span class="badge bg-success-subtle text-success-emphasis">Active</span>
            <?php else: ?>
              <span class="badge bg-secondary-subtle text-secondary-emphasis">Inactive</span>
            <?php endif; ?>
          </span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- School admin accounts -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-1"><i class="bi bi-person-gear"></i> School Admin Accounts</h5>
    <p class="text-muted small mb-0">
      School admins manage this school's staff, students, and data. A temporary password is emailed on creation.
    </p>
  </div>
  <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#addAdminForm">
    <i class="bi bi-plus-lg"></i> Add Admin
  </button>
</div>

<!-- Add admin inline form -->
<div class="collapse mb-3" id="addAdminForm">
  <div class="card border-0 shadow-sm">
    <div class="card-body px-4 py-3">
      <h6 class="mb-3">New School Admin for <?= View::e($school['name']) ?></h6>
      <form method="post" action="<?= $base ?>/schools/<?= (int) $school['id'] ?>/admins">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
        <div class="row g-3">
          <div class="col-md-5">
            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <div class="col-md-5">
            <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
            <input type="email" name="email" class="form-control" required>
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">Create</button>
          </div>
        </div>
        <div class="form-text mt-2">
          <i class="bi bi-info-circle me-1"></i>
          A random password will be auto-generated and emailed to the admin.
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Admins table -->
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
        <?php if (empty($admins)): ?>
          <tr>
            <td colspan="5" class="text-center text-muted py-4">
              No admin accounts yet. Click <strong>Add Admin</strong> to create one.
            </td>
          </tr>
        <?php else: foreach ($admins as $a): ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= View::e($a['name']) ?></div>
              <span class="badge bg-warning-subtle text-warning-emphasis mt-1">
                <i class="bi bi-person-gear"></i> School Admin
              </span>
            </td>
            <td class="font-monospace small"><?= View::e($a['email']) ?></td>
            <td>
              <?php if ($a['status'] === 'active'): ?>
                <span class="badge bg-success-subtle text-success-emphasis">Active</span>
              <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary-emphasis">Disabled</span>
              <?php endif; ?>
            </td>
            <td class="text-muted small"><?= View::e(substr((string) $a['created_at'], 0, 10)) ?></td>
            <td class="text-end">
              <form class="d-inline" method="post"
                    action="<?= $base ?>/school-admins/<?= (int) $a['id'] ?>/resend"
                    title="Reset password and resend login credentials">
                <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                <button class="btn btn-sm btn-outline-secondary">
                  <i class="bi bi-envelope-arrow-up"></i> Resend
                </button>
              </form>
              <form class="d-inline ms-1" method="post"
                    action="<?= $base ?>/school-admins/<?= (int) $a['id'] ?>/delete"
                    data-confirm="Remove this school admin account? They will no longer be able to sign in.">
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
