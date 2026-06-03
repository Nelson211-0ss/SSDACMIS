<?php
use App\Core\View;
$layout = 'app';
$title  = $school['name'];

// Resolve logo / signature paths for display.
$base64   = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$logoUrl  = '';
$sigUrl   = '';
$logoRel  = trim((string) ($school['logo'] ?? ''));
if ($logoRel !== '' && str_starts_with(ltrim($logoRel,'/'), 'uploads/')) {
    $abs = dirname(__DIR__, 3) . '/public/' . ltrim($logoRel, '/');
    if (is_file($abs)) $logoUrl = $base64 . '/' . ltrim($logoRel, '/');
}
$sigRel = trim((string) ($school['headteacher_signature'] ?? ''));
if ($sigRel !== '' && str_starts_with(ltrim($sigRel,'/'), 'uploads/')) {
    $abs = dirname(__DIR__, 3) . '/public/' . ltrim($sigRel, '/');
    if (is_file($abs)) $sigUrl = $base64 . '/' . ltrim($sigRel, '/');
}
$isActive = ($school['status'] ?? '') === 'active';
$htName   = trim((string)($school['headteacher_name'] ?? ''));
?>
<div class="app-toolbar mb-3">
    <a href="<?= $base ?>/schools" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left"></i> Schools
    </a>
    <a href="<?= $base ?>/schools/<?= (int) $school['id'] ?>/edit" class="btn btn-sm btn-primary">
      <i class="bi bi-pencil"></i> Edit school
    </a>
  </div>

  <div class="sa-profile card border-0">
    <div class="card-body">
      <div class="sa-profile__main">
        <?php if ($logoUrl !== ''): ?>
          <img src="<?= View::e($logoUrl) ?>" alt="" class="sa-profile__logo">
        <?php else: ?>
          <span class="sa-profile__logo sa-profile__logo--placeholder" aria-hidden="true"><i class="bi bi-building"></i></span>
        <?php endif; ?>
        <div class="sa-profile__body">
          <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
            <h2 class="sa-profile__title mb-0"><?= View::e($school['name']) ?></h2>
            <span class="sa-status <?= $isActive ? 'sa-status--on' : 'sa-status--off' ?>">
              <?= $isActive ? 'Active' : 'Inactive' ?>
            </span>
          </div>
          <?php $motto = trim((string)($school['motto'] ?? '')); if ($motto !== ''): ?>
            <p class="sa-profile__motto mb-2"><?= View::e($motto) ?></p>
          <?php endif; ?>
          <ul class="sa-meta-list">
            <li><span class="sa-meta-list__k">Code</span><code class="sa-code"><?= View::e($school['code']) ?></code></li>
            <?php if ($school['email']): ?>
              <li><span class="sa-meta-list__k">Email</span><a href="mailto:<?= View::e($school['email']) ?>"><?= View::e($school['email']) ?></a></li>
            <?php endif; ?>
            <?php if ($school['phone']): ?>
              <li><span class="sa-meta-list__k">Phone</span><?= View::e($school['phone']) ?></li>
            <?php endif; ?>
            <?php if ($school['address']): ?>
              <li><span class="sa-meta-list__k">Address</span><?= View::e($school['address']) ?></li>
            <?php endif; ?>
          </ul>
        </div>
      </div>

      <?php if ($htName !== '' || $sigUrl !== ''): ?>
        <hr class="my-3">
        <div class="sa-profile__ht row g-3 align-items-center">
          <?php if ($htName !== ''): ?>
            <div class="col-sm-auto">
              <div class="sa-profile__ht-label">Head teacher</div>
              <div class="fw-semibold"><?= View::e($htName) ?></div>
              <div class="small text-muted"><?= View::e(trim((string)($school['headteacher_title'] ?? 'Head Teacher'))) ?></div>
            </div>
          <?php endif; ?>
          <?php if ($sigUrl !== ''): ?>
            <div class="col-sm-auto">
              <div class="sa-profile__ht-label">Signature</div>
              <img src="<?= View::e($sigUrl) ?>" alt="Headteacher signature" class="sa-profile__sig">
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="sa-panel mt-3">
    <div class="sa-panel__head">
      <div>
        <h3 class="sa-panel__title">School admin accounts</h3>
        <p class="sa-panel__sub">Credentials are emailed when an account is created.</p>
      </div>
      <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#addAdminForm" aria-expanded="false">
        <i class="bi bi-plus-lg"></i> Add admin
      </button>
    </div>

    <div class="collapse border-bottom" id="addAdminForm">
      <div class="sa-panel__body">
        <form method="post" action="<?= $base ?>/schools/<?= (int) $school['id'] ?>/admins">
          <input type="hidden" name="_csrf" value="<?= $csrf ?>">
          <div class="row g-3 align-items-end">
            <div class="col-md-5">
              <label class="form-label fw-semibold">Full name <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" required>
            </div>
            <div class="col-md-5">
              <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
              <input type="email" name="email" class="form-control" required>
            </div>
            <div class="col-md-2">
              <button type="submit" class="btn btn-primary w-100">Create</button>
            </div>
          </div>
          <p class="form-text small mb-0 mt-2">
            <i class="bi bi-info-circle"></i> A random password is generated and sent by email.
          </p>
        </form>
      </div>
    </div>

    <div class="sa-table-wrap">
      <table class="table sa-table align-middle mb-0">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Status</th>
            <th class="d-none d-md-table-cell">Created</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($admins)): ?>
            <tr>
              <td colspan="5" class="sa-empty">
                <i class="bi bi-person-gear d-block"></i>
                <p class="mb-0">No school admins yet. Use <strong>Add admin</strong> above.</p>
              </td>
            </tr>
          <?php else: foreach ($admins as $a):
            $adminActive = ($a['status'] ?? '') === 'active';
          ?>
            <tr class="<?= !$adminActive ? 'sa-table__row--muted' : '' ?>">
              <td>
                <div class="fw-semibold"><?= View::e($a['name']) ?></div>
                <span class="sa-pill sa-pill--muted">School admin</span>
              </td>
              <td class="font-monospace small"><?= View::e($a['email']) ?></td>
              <td>
                <span class="sa-status <?= $adminActive ? 'sa-status--on' : 'sa-status--off' ?>">
                  <?= $adminActive ? 'Active' : 'Disabled' ?>
                </span>
              </td>
              <td class="text-muted small d-none d-md-table-cell"><?= View::e(substr((string) $a['created_at'], 0, 10)) ?></td>
              <td class="text-end text-nowrap">
                <form class="d-inline" method="post"
                      action="<?= $base ?>/school-admins/<?= (int) $a['id'] ?>/resend"
                      title="Reset password and resend login">
                  <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                  <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-envelope-arrow-up"></i><span class="d-none d-lg-inline"> Resend</span>
                  </button>
                </form>
                <form class="d-inline" method="post"
                      action="<?= $base ?>/school-admins/<?= (int) $a['id'] ?>/delete"
                      data-confirm="Remove this school admin? They will no longer be able to sign in.">
                  <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
