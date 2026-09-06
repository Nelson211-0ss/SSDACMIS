<?php
use App\Core\View;

$layout = 'app';
$title  = 'User management';

$users         = $users ?? [];
$counts        = $counts ?? ['total' => 0, 'active' => 0, 'disabled' => 0, 'byRole' => []];
$roles         = $roles ?? [];
$roleLabels    = $roleLabels ?? [];
$isSuperAdmin  = !empty($isSuperAdmin);
$schools       = $schools ?? [];
$overridden    = $overridden ?? [];
$filters       = $filters ?? ['role' => '', 'status' => '', 'school_id' => 0, 'q' => ''];
$currentUserId = (int) ($currentUserId ?? 0);

$roleTone = [
    'admin'        => 'danger',
    'school_admin' => 'primary',
    'hod'          => 'info',
    'staff'        => 'success',
    'bursar'       => 'warning',
    'parent'       => 'secondary',
    'student'      => 'light',
];
?>
<div class="users-page">

  <section class="dash-hero dash-hero--slim mb-3">
    <div class="d-flex flex-wrap align-items-center gap-3">
      <span class="icon-chip icon-chip--blue d-none d-sm-inline-grid" aria-hidden="true">
        <i class="bi bi-people-fill"></i>
      </span>
      <div class="flex-grow-1" style="min-width:0;">
        <h2 class="dash-hero__title mb-1">User management</h2>
        <p class="dash-hero__sub mb-0">
          <?= $isSuperAdmin
            ? 'Every account across all schools. Create, disable and set what each role can reach.'
            : 'Every account in your school. Create, disable and set what each role can reach.' ?>
        </p>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/users/permissions">
          <i class="bi bi-shield-lock"></i> <span class="d-none d-sm-inline">Permissions</span>
        </a>
        <a class="btn btn-primary btn-sm" href="<?= $base ?>/users/create">
          <i class="bi bi-person-plus"></i> <span class="d-none d-sm-inline">New user</span>
        </a>
      </div>
    </div>
  </section>

  <!-- Summary -->
  <div class="row g-2 g-md-3 mb-3">
    <div class="col-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body py-2 px-3">
          <div class="text-muted small text-uppercase">Accounts</div>
          <div class="fs-4 fw-bold"><?= (int) $counts['total'] ?></div>
        </div>
      </div>
    </div>
    <div class="col-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body py-2 px-3">
          <div class="text-muted small text-uppercase">Active</div>
          <div class="fs-4 fw-bold text-success"><?= (int) $counts['active'] ?></div>
        </div>
      </div>
    </div>
    <div class="col-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body py-2 px-3">
          <div class="text-muted small text-uppercase">Disabled</div>
          <div class="fs-4 fw-bold text-secondary"><?= (int) $counts['disabled'] ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filters -->
  <form class="card border-0 shadow-sm mb-3" method="get" action="<?= $base ?>/users">
    <div class="card-body">
      <div class="row g-2 align-items-end">
        <div class="col-12 col-md-4">
          <label class="form-label" for="uq">Search</label>
          <input id="uq" type="search" name="q" class="form-control form-control-sm"
                 value="<?= View::e((string) $filters['q']) ?>" placeholder="Name or email">
        </div>
        <div class="col-6 col-md-<?= $isSuperAdmin ? '2' : '3' ?>">
          <label class="form-label" for="urole">Role</label>
          <select id="urole" name="role" class="form-select form-select-sm">
            <option value="">All roles</option>
            <?php foreach ($roles as $key => $label): ?>
              <option value="<?= View::e((string) $key) ?>" <?= $filters['role'] === $key ? 'selected' : '' ?>>
                <?= View::e($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-<?= $isSuperAdmin ? '2' : '3' ?>">
          <label class="form-label" for="ustatus">Status</label>
          <select id="ustatus" name="status" class="form-select form-select-sm">
            <option value="">Any</option>
            <option value="active"   <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="disabled" <?= $filters['status'] === 'disabled' ? 'selected' : '' ?>>Disabled</option>
          </select>
        </div>
        <?php if ($isSuperAdmin): ?>
          <div class="col-6 col-md-2">
            <label class="form-label" for="uschool">School</label>
            <select id="uschool" name="school_id" class="form-select form-select-sm">
              <option value="0">All schools</option>
              <?php foreach ($schools as $s): ?>
                <option value="<?= (int) $s['id'] ?>" <?= (int) $filters['school_id'] === (int) $s['id'] ? 'selected' : '' ?>>
                  <?= View::e((string) $s['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
        <div class="col-6 col-md-2">
          <button type="submit" class="btn btn-outline-primary btn-sm w-100">
            <i class="bi bi-funnel"></i> Filter
          </button>
        </div>
      </div>
    </div>
  </form>

  <!-- List -->
  <?php if (!$users): ?>
    <div class="alert alert-info">
      <i class="bi bi-info-circle"></i> No accounts match these filters.
    </div>
  <?php else: ?>
    <div class="card border-0 shadow-sm">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 users-table">
          <thead class="table-light">
            <tr>
              <th>Name</th>
              <th class="d-none d-md-table-cell">Email</th>
              <th>Role</th>
              <?php if ($isSuperAdmin): ?><th class="d-none d-lg-table-cell">School</th><?php endif; ?>
              <th class="d-none d-sm-table-cell">Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u):
              $uid    = (int) $u['id'];
              $isSelf = ($uid === $currentUserId);
              $role   = (string) $u['role'];
            ?>
              <tr>
                <td>
                  <div class="fw-semibold">
                    <?= View::e((string) $u['name']) ?>
                    <?php if ($isSelf): ?>
                      <span class="badge text-bg-light border ms-1">You</span>
                    <?php endif; ?>
                  </div>
                  <div class="small text-muted d-md-none"><?= View::e((string) $u['email']) ?></div>
                  <?php if (!empty($u['department'])): ?>
                    <div class="small text-muted"><?= View::e((string) $u['department']) ?></div>
                  <?php endif; ?>
                </td>
                <td class="d-none d-md-table-cell text-break"><?= View::e((string) $u['email']) ?></td>
                <td>
                  <span class="badge text-bg-<?= $roleTone[$role] ?? 'secondary' ?>">
                    <?= View::e($roleLabels[$role] ?? $role) ?>
                  </span>
                  <?php if (!empty($overridden[$uid])): ?>
                    <span class="badge text-bg-light border ms-1" title="This account has per-user permission overrides">
                      <i class="bi bi-sliders2"></i>
                    </span>
                  <?php endif; ?>
                </td>
                <?php if ($isSuperAdmin): ?>
                  <td class="d-none d-lg-table-cell">
                    <?= View::e((string) ($u['school_name'] ?? '—')) ?>
                  </td>
                <?php endif; ?>
                <td class="d-none d-sm-table-cell">
                  <span class="badge <?= $u['status'] === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?>">
                    <?= View::e(ucfirst((string) $u['status'])) ?>
                  </span>
                </td>
                <td class="text-end">
                  <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                    <a class="btn btn-sm btn-outline-secondary" href="<?= $base ?>/users/<?= $uid ?>/edit" title="Edit">
                      <i class="bi bi-pencil"></i><span class="visually-hidden">Edit</span>
                    </a>
                    <?php if (!$isSelf): ?>
                      <form method="post" action="<?= $base ?>/users/<?= $uid ?>/status" class="d-inline">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <button class="btn btn-sm btn-outline-<?= $u['status'] === 'active' ? 'warning' : 'success' ?>"
                                title="<?= $u['status'] === 'active' ? 'Disable' : 'Enable' ?>">
                          <i class="bi <?= $u['status'] === 'active' ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                          <span class="visually-hidden"><?= $u['status'] === 'active' ? 'Disable' : 'Enable' ?></span>
                        </button>
                      </form>
                      <form method="post" action="<?= $base ?>/users/<?= $uid ?>/delete" class="d-inline"
                            onsubmit="return confirm('Delete the account for <?= View::e((string) $u['name']) ?>? This cannot be undone.');">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <button class="btn btn-sm btn-outline-danger" title="Delete">
                          <i class="bi bi-trash"></i><span class="visually-hidden">Delete</span>
                        </button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>
