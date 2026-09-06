<?php
use App\Core\View;

$layout = 'app';
$user   = $user ?? null;
$isEdit = $user !== null;
$title  = $isEdit ? 'Edit user' : 'New user';

$roles        = $roles ?? [];
$isSuperAdmin = !empty($isSuperAdmin);
$schools      = $schools ?? [];
$catalog      = $catalog ?? [];
$overrides    = $overrides ?? [];
$effective    = $effective ?? [];
$isSelf       = !empty($isSelf);

$action = $isEdit ? ($base . '/users/' . (int) $user['id']) : ($base . '/users');
?>
<div class="users-form-page">

  <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/users">
      <i class="bi bi-arrow-left"></i> Back
    </a>
    <h4 class="mb-0 flex-grow-1"><?= $isEdit ? 'Edit user' : 'New user' ?></h4>
  </div>

  <form method="post" action="<?= View::e($action) ?>" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">

    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-transparent"><strong>Account</strong></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-12 col-md-6">
            <label class="form-label" for="uname">Full name</label>
            <input id="uname" name="name" class="form-control" required
                   value="<?= View::e((string) ($user['name'] ?? '')) ?>">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="uemail">Email address</label>
            <input id="uemail" name="email" type="email" class="form-control" required
                   value="<?= View::e((string) ($user['email'] ?? '')) ?>">
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label" for="urole">Role</label>
            <select id="urole" name="role" class="form-select" <?= $isSelf ? 'disabled' : '' ?> required>
              <?php foreach ($roles as $key => $label): ?>
                <option value="<?= View::e((string) $key) ?>"
                        <?= (string) ($user['role'] ?? '') === (string) $key ? 'selected' : '' ?>>
                  <?= View::e($label) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if ($isSelf): ?>
              <div class="form-text">You cannot change your own role.</div>
            <?php endif; ?>
          </div>

          <?php if ($isSuperAdmin): ?>
            <div class="col-12 col-md-6">
              <label class="form-label" for="uschool">School</label>
              <select id="uschool" name="school_id" class="form-select">
                <option value="0">— None (global super admin) —</option>
                <?php foreach ($schools as $s): ?>
                  <option value="<?= (int) $s['id'] ?>"
                          <?= (int) ($user['school_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>>
                    <?= View::e((string) $s['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Super admin accounts belong to no single school.</div>
            </div>
          <?php endif; ?>

          <div class="col-12 col-md-6">
            <label class="form-label" for="udept">Department <span class="text-muted">(optional)</span></label>
            <input id="udept" name="department" class="form-control"
                   value="<?= View::e((string) ($user['department'] ?? '')) ?>"
                   placeholder="e.g. Sciences">
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label" for="upass">
              Password <?= $isEdit ? '<span class="text-muted">(leave blank to keep)</span>' : '' ?>
            </label>
            <input id="upass" name="password" type="password" class="form-control"
                   minlength="8" <?= $isEdit ? '' : 'required' ?> autocomplete="new-password">
            <div class="form-text">At least 8 characters.</div>
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label" for="ustatus">Status</label>
            <select id="ustatus" name="status" class="form-select" <?= $isSelf ? 'disabled' : '' ?>>
              <option value="active"   <?= ($user['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
              <option value="disabled" <?= ($user['status'] ?? '') === 'disabled' ? 'selected' : '' ?>>Disabled</option>
            </select>
            <?php if ($isSelf): ?>
              <div class="form-text">You cannot disable your own account.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Per-user permission overrides -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-transparent d-flex flex-wrap align-items-center gap-2">
        <strong class="me-auto">Permissions for this account</strong>
        <a class="small" href="<?= $base ?>/users/permissions">Edit the role defaults</a>
      </div>
      <div class="card-body">
        <?php if ($isSelf): ?>
          <div class="alert alert-light border small mb-3">
            <i class="bi bi-info-circle"></i>
            These are your own permissions, shown read-only. Denying yourself
            “Manage user accounts” would lock you out of this page, so changes
            here are ignored — ask another administrator instead.
          </div>
        <?php else: ?>
          <p class="text-muted small">
            Each permission follows this account’s role unless you override it here.
            “Allow” grants it even when the role does not have it; “Deny” takes it away
            even when the role does.
          </p>
        <?php endif; ?>
        <?php foreach ($catalog as $group => $items): ?>
          <div class="mb-3">
            <div class="fw-semibold small text-uppercase text-muted mb-2"><?= View::e($group) ?></div>
            <div class="row g-2">
              <?php foreach ($items as $key => $label):
                $current = $overrides[$key] ?? null;   // true / false / null
                $roleHas = !empty($effective[$key]);
                $id      = 'perm_' . preg_replace('/[^a-z0-9]+/i', '_', $key);
              ?>
                <div class="col-12 col-lg-6">
                  <div class="d-flex flex-wrap align-items-center gap-2 border rounded p-2">
                    <div class="flex-grow-1" style="min-width:0;">
                      <div class="small fw-semibold text-truncate"><?= View::e($label) ?></div>
                      <div class="small text-muted">
                        Role default:
                        <span class="<?= $roleHas ? 'text-success' : 'text-secondary' ?>">
                          <?= $roleHas ? 'allowed' : 'not allowed' ?>
                        </span>
                      </div>
                    </div>
                    <div class="btn-group btn-group-sm flex-shrink-0" role="group" aria-label="<?= View::e($label) ?>">
                      <input type="radio" class="btn-check" name="perm[<?= View::e($key) ?>]" <?= $isSelf ? "disabled" : "" ?>
                             id="<?= $id ?>_inherit" value="" <?= $current === null ? 'checked' : '' ?>>
                      <label class="btn btn-outline-secondary" for="<?= $id ?>_inherit">Role</label>

                      <input type="radio" class="btn-check" name="perm[<?= View::e($key) ?>]" <?= $isSelf ? "disabled" : "" ?>
                             id="<?= $id ?>_allow" value="allow" <?= $current === true ? 'checked' : '' ?>>
                      <label class="btn btn-outline-success" for="<?= $id ?>_allow">Allow</label>

                      <input type="radio" class="btn-check" name="perm[<?= View::e($key) ?>]" <?= $isSelf ? "disabled" : "" ?>
                             id="<?= $id ?>_deny" value="deny" <?= $current === false ? 'checked' : '' ?>>
                      <label class="btn btn-outline-danger" for="<?= $id ?>_deny">Deny</label>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-4">
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-check2"></i> <?= $isEdit ? 'Save changes' : 'Create account' ?>
      </button>
      <a class="btn btn-outline-secondary" href="<?= $base ?>/users">Cancel</a>
    </div>
  </form>
</div>
