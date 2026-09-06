<?php
use App\Core\View;

$layout = 'app';
$title  = 'Role permissions';

$catalog      = $catalog ?? [];
$roles        = $roles ?? [];
$matrix       = $matrix ?? [];
$isSuperAdmin = !empty($isSuperAdmin);
$schools      = $schools ?? [];
$schoolId     = $schoolId ?? null;

$scopeLabel = 'your school';
if ($isSuperAdmin) {
    $scopeLabel = 'the global defaults';
    foreach ($schools as $s) {
        if ((int) $s['id'] === (int) $schoolId) {
            $scopeLabel = (string) $s['name'];
            break;
        }
    }
}
?>
<div class="permissions-page">

  <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/users">
      <i class="bi bi-arrow-left"></i> Users
    </a>
    <div class="flex-grow-1">
      <h4 class="mb-0">Role permissions</h4>
      <div class="small text-muted">Editing <strong><?= View::e($scopeLabel) ?></strong></div>
    </div>
  </div>

  <?php if ($isSuperAdmin): ?>
    <form class="card border-0 shadow-sm mb-3" method="get" action="<?= $base ?>/users/permissions">
      <div class="card-body d-flex flex-wrap align-items-end gap-2">
        <div class="flex-grow-1" style="max-width:22rem;">
          <label class="form-label" for="permScope">Apply to</label>
          <select id="permScope" name="school_id" class="form-select form-select-sm">
            <option value="0" <?= $schoolId === null ? 'selected' : '' ?>>Global defaults (all schools)</option>
            <?php foreach ($schools as $s): ?>
              <option value="<?= (int) $s['id'] ?>" <?= (int) $schoolId === (int) $s['id'] ? 'selected' : '' ?>>
                <?= View::e((string) $s['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-repeat"></i> Switch</button>
      </div>
    </form>
  <?php endif; ?>

  <div class="alert alert-light border small">
    <i class="bi bi-info-circle"></i>
    A ticked box means every account with that role can reach the feature. An individual
    account can still be given or refused a permission on its own edit page, which wins
    over this matrix. The super admin always keeps full access.
  </div>

  <form method="post" action="<?= $base ?>/users/permissions">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <?php if ($isSuperAdmin): ?>
      <input type="hidden" name="school_id" value="<?= (int) ($schoolId ?? 0) ?>">
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
      <div class="table-responsive permissions-scroll">
        <table class="table table-sm align-middle mb-0 permissions-table">
          <thead class="table-light">
            <tr>
              <th class="permissions-table__feature">Feature</th>
              <?php foreach ($roles as $role => $label): ?>
                <th class="text-center"><?= View::e($label) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($catalog as $group => $items): ?>
              <tr class="table-light">
                <th class="permissions-table__feature" colspan="<?= count($roles) + 1 ?>">
                  <?= View::e($group) ?>
                </th>
              </tr>
              <?php foreach ($items as $key => $label): ?>
                <tr>
                  <td class="permissions-table__feature">
                    <span class="small"><?= View::e($label) ?></span>
                    <div class="text-muted" style="font-size:.7rem;"><?= View::e($key) ?></div>
                  </td>
                  <?php foreach ($roles as $role => $roleLabel):
                    $checked = !empty($matrix[$role][$key]);
                    $id = 'p_' . $role . '_' . preg_replace('/[^a-z0-9]+/i', '_', $key);
                  ?>
                    <td class="text-center">
                      <input class="form-check-input" type="checkbox"
                             id="<?= $id ?>"
                             name="perm[<?= View::e($role) ?>][<?= View::e($key) ?>]"
                             value="1" <?= $checked ? 'checked' : '' ?>
                             aria-label="<?= View::e($roleLabel . ' — ' . $label) ?>">
                    </td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-4">
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-check2"></i> Save permissions
      </button>
      <a class="btn btn-outline-secondary" href="<?= $base ?>/users">Cancel</a>
    </div>
  </form>
</div>
