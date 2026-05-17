<?php
use App\Core\View;
$isEdit  = isset($school) && $school !== null;
$layout  = 'app';
$title   = $isEdit ? 'Edit School' : 'New School';
$action  = $isEdit ? $base . '/schools/' . (int) $school['id'] : $base . '/schools';
?>
<div class="mb-3">
  <a href="<?= $base ?>/schools" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left"></i> Back to Schools
  </a>
</div>

<div class="card border-0 shadow-sm" style="max-width:640px;">
  <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4">
    <h5 class="mb-0">
      <i class="bi bi-building"></i>
      <?= $isEdit ? 'Edit School' : 'Add New School' ?>
    </h5>
  </div>
  <div class="card-body px-4 pb-4">
    <form method="post" action="<?= $action ?>">
      <input type="hidden" name="_csrf" value="<?= $csrf ?>">

      <div class="mb-3">
        <label class="form-label fw-semibold">School Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control"
               value="<?= View::e($school['name'] ?? '') ?>" required>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">School Code <span class="text-danger">*</span></label>
        <input type="text" name="code" class="form-control text-uppercase"
               value="<?= View::e($school['code'] ?? '') ?>"
               placeholder="e.g. SCH001" required maxlength="20">
        <div class="form-text">Unique short code (letters and numbers). Will be stored in UPPERCASE.</div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label fw-semibold">Email</label>
          <input type="email" name="email" class="form-control"
                 value="<?= View::e($school['email'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Phone</label>
          <input type="text" name="phone" class="form-control"
                 value="<?= View::e($school['phone'] ?? '') ?>">
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Address</label>
        <textarea name="address" class="form-control" rows="2"><?= View::e($school['address'] ?? '') ?></textarea>
      </div>

      <div class="mb-4">
        <label class="form-label fw-semibold">Status</label>
        <select name="status" class="form-select">
          <option value="active"   <?= ($school['status'] ?? 'active') === 'active'   ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= ($school['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
          <?= $isEdit ? 'Save Changes' : 'Create School' ?>
        </button>
        <a href="<?= $base ?>/schools<?= $isEdit ? '/' . (int) $school['id'] : '' ?>"
           class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
