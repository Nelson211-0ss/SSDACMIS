<?php
use App\Core\View;
$layout  = 'app';
$editing = (bool) ($bursar ?? null);
$title   = $editing ? 'Edit Bursar' : 'New Bursar';
$action  = $editing
    ? ($base . '/bursars/' . (int) $bursar['id'])
    : ($base . '/bursars');
?>
<div class="entity-form">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <?php if (!$editing): ?>
        <span class="badge rounded-pill bg-success-subtle text-success-emphasis" style="font-size: 0.65rem; font-weight: 700; letter-spacing: 0.06em;">NEW</span>
      <?php else: ?>
        <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis" style="font-size: 0.65rem; font-weight: 700; letter-spacing: 0.06em;">EDIT</span>
      <?php endif; ?>
      <h2 class="h5 mb-0"><?= View::e($title) ?></h2>
      <span class="text-muted small d-none d-md-inline">— Fees Management account</span>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/bursars"><i class="bi bi-arrow-left"></i> Back</a>
  </div>

  <form method="post" action="<?= $action ?>">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">

    <div class="card entity-form__card border-0 shadow-sm">
      <div class="card-body">
        <div class="row entity-form__split g-3 gx-xl-4 gy-3">

          <div class="col-xl-7 entity-form__divider mb-xl-0 mb-3">
            <div class="entity-form__col-title">
              <span class="card-header-icon card-header-icon--green" style="width: 1.35rem; height: 1.35rem; font-size: 0.75rem;"><i class="bi bi-cash-coin"></i></span>
              Bursar profile
            </div>

            <div class="row g-2 mb-2">
              <div class="col-md-12">
                <label class="form-label small fw-semibold mb-1">Full name <span class="text-danger">*</span></label>
                <input name="name" class="form-control form-control-sm shadow-sm" required
                       placeholder="e.g. James Otieno"
                       value="<?= View::e($bursar['name'] ?? '') ?>">
              </div>
            </div>

            <div class="row g-2 mb-2">
              <div class="col-md-7">
                <label class="form-label small fw-semibold mb-1">Email <span class="text-muted fw-normal">(sign-in)</span> <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control form-control-sm shadow-sm" required
                       placeholder="bursar@school.local"
                       autocomplete="username"
                       value="<?= View::e($bursar['email'] ?? '') ?>">
              </div>
              <div class="col-md-5">
                <label class="form-label small fw-semibold mb-1">Status</label>
                <select name="status" class="form-select form-select-sm shadow-sm">
                  <?php $st = $bursar['status'] ?? 'active'; ?>
                  <option value="active"   <?= $st === 'active'   ? 'selected' : '' ?>>Active</option>
                  <option value="disabled" <?= $st === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                </select>
              </div>
            </div>

            <div class="entity-form__panel mb-0">
              <label class="form-label small fw-semibold mb-1">
                Password <?= $editing ? '<span class="text-muted fw-normal">(leave blank to keep)</span>' : '<span class="text-danger">*</span>' ?>
              </label>
              <input type="password" name="password" class="form-control form-control-sm shadow-sm"
                     minlength="6" autocomplete="new-password"
                     <?= $editing ? '' : 'required' ?>
                     placeholder="<?= $editing ? 'Unchanged' : 'Min. 6 characters' ?>">
            </div>
          </div>

          <div class="col-xl-5">
            <div class="entity-form__col-title">
              <span class="card-header-icon card-header-icon--teal" style="width: 1.35rem; height: 1.35rem; font-size: 0.75rem;"><i class="bi bi-door-open"></i></span>
              Bursar portal
            </div>
            <div class="entity-form__panel small mb-2">
              <p class="mb-2"><strong class="text-body">Sign-in URL</strong><br>
                <code class="small"><?= $base ?>/bursar/login</code>
              </p>
              <p class="mb-0 text-muted">
                Bursars set fees per Form &amp; section, record student payments,
                and print/export reports per class. Share email + password
                privately after saving.
              </p>
            </div>
          </div>

        </div>
      </div>

      <div class="card-footer py-2 px-3 bg-body-secondary bg-opacity-25 border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="small text-muted mb-0"><span class="text-danger">*</span> Required · min. 6 characters for new passwords</span>
        <div class="d-flex flex-wrap gap-2 ms-auto">
          <a href="<?= $base ?>/bursars" class="btn btn-outline-secondary btn-sm px-3">Cancel</a>
          <button type="submit" class="btn btn-primary btn-sm px-4"><i class="bi bi-check-lg me-1"></i><?= $editing ? 'Save changes' : 'Create Bursar' ?></button>
        </div>
      </div>
    </div>
  </form>
</div>
