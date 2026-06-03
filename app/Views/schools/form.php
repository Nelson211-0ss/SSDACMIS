<?php
use App\Core\View;
$isEdit  = isset($school) && $school !== null;
$layout  = 'app';
$title   = $isEdit ? 'Edit School' : 'New School';
$action  = $isEdit ? $base . '/schools/' . (int) $school['id'] : $base . '/schools';

$base64 = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

// Resolve existing logo / signature paths so we can preview them.
$existingLogo = '';
$existingSig  = '';
if ($isEdit) {
    $logoRel = trim((string) ($school['logo'] ?? ''));
    if ($logoRel !== '' && str_starts_with(ltrim($logoRel, '/'), 'uploads/')) {
        $abs = dirname(__DIR__, 3) . '/public/' . ltrim($logoRel, '/');
        if (is_file($abs)) $existingLogo = $base64 . '/' . ltrim($logoRel, '/');
    }
    $sigRel = trim((string) ($school['headteacher_signature'] ?? ''));
    if ($sigRel !== '' && str_starts_with(ltrim($sigRel, '/'), 'uploads/')) {
        $abs = dirname(__DIR__, 3) . '/public/' . ltrim($sigRel, '/');
        if (is_file($abs)) $existingSig = $base64 . '/' . ltrim($sigRel, '/');
    }
}
?>
<div class="app-toolbar mb-3">
    <a href="<?= $base ?>/schools" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left"></i> Schools
    </a>
</div>

<div class="page-header" style="max-width:760px;">
    <div>
      <h2><?= $isEdit ? 'Edit school' : 'New school' ?></h2>
      <p class="page-header__sub mb-0">
        <?= $isEdit ? View::e($school['name']) . ' — ' : '' ?>
        Used on report cards, receipts, exam permits, and admission letters.
      </p>
    </div>
  </div>

  <div class="card sa-profile border-0" style="max-width:760px;">
  <div class="card-body px-4 pb-4">
    <form method="post" action="<?= $action ?>" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= $csrf ?>">

      <!-- ── Identity ─────────────────────────────────────────────── -->
      <h6 class="text-uppercase text-muted small fw-bold letter-spacing-wide mt-1 mb-3">
        <i class="bi bi-building me-1"></i> School Identity
      </h6>

      <div class="mb-3">
        <label class="form-label fw-semibold">School Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control"
               value="<?= View::e($school['name'] ?? '') ?>" required>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <label class="form-label fw-semibold">School Code <span class="text-danger">*</span></label>
          <?php if ($isEdit): ?>
            <input type="text" name="code" class="form-control text-uppercase" value="<?= View::e($school['code'] ?? '') ?>" disabled maxlength="20">
            <input type="hidden" name="code" value="<?= View::e($school['code'] ?? '') ?>">
          <?php else: ?>
            <input type="text" class="form-control text-uppercase" value="<?= View::e($suggestedCode ?? '') ?>" disabled maxlength="20">
            <input type="hidden" name="code" value="<?= View::e($suggestedCode ?? '') ?>">
          <?php endif; ?>
          <div class="form-text">Unique short code. Stored in UPPERCASE.</div>
        </div>
        <div class="col-md-8">
          <label class="form-label fw-semibold">Motto / Tag-line</label>
          <input type="text" name="motto" class="form-control"
                 value="<?= View::e($school['motto'] ?? '') ?>"
                 placeholder="e.g. Excellence in Education" maxlength="200">
          <div class="form-text">Shown under the school name on report cards and letters.</div>
        </div>
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

      <div class="mb-4">
        <label class="form-label fw-semibold">Address</label>
        <textarea name="address" class="form-control" rows="2"><?= View::e($school['address'] ?? '') ?></textarea>
      </div>

      <!-- ── Branding ──────────────────────────────────────────────── -->
      <h6 class="text-uppercase text-muted small fw-bold letter-spacing-wide mb-3 pt-1 border-top">
        <i class="bi bi-image me-1"></i> School Logo
      </h6>

      <?php if ($existingLogo !== ''): ?>
        <div class="mb-3 d-flex align-items-center gap-3">
          <img src="<?= View::e($existingLogo) ?>" alt="Current logo"
               style="height:72px;width:72px;object-fit:contain;border:1px solid #e5e7eb;border-radius:8px;background:#f9fafb;">
          <div>
            <div class="small fw-semibold mb-1">Current logo</div>
            <label class="form-check-label small text-danger">
              <input type="checkbox" name="remove_logo" value="1" class="form-check-input me-1">
              Remove this logo
            </label>
          </div>
        </div>
      <?php endif; ?>

      <div class="mb-4">
        <label class="form-label fw-semibold"><?= $existingLogo ? 'Replace Logo' : 'Upload Logo' ?></label>
        <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/webp,image/gif">
        <div class="form-text">PNG, JPG or WebP recommended. Max 1.5 MB. Square works best.</div>
      </div>

      <!-- ── Head Teacher ──────────────────────────────────────────── -->
      <h6 class="text-uppercase text-muted small fw-bold letter-spacing-wide mb-3 pt-1 border-top">
        <i class="bi bi-person-badge me-1"></i> Head Teacher
      </h6>

      <div class="row g-3 mb-3">
        <div class="col-md-7">
          <label class="form-label fw-semibold">Head Teacher Name</label>
          <input type="text" name="headteacher_name" class="form-control"
                 value="<?= View::e($school['headteacher_name'] ?? '') ?>"
                 placeholder="e.g. Dr. John Doe" maxlength="150">
        </div>
        <div class="col-md-5">
          <label class="form-label fw-semibold">Title</label>
          <input type="text" name="headteacher_title" class="form-control"
                 value="<?= View::e($school['headteacher_title'] ?? 'Head Teacher') ?>"
                 placeholder="Head Teacher" maxlength="80">
        </div>
      </div>

      <?php if ($existingSig !== ''): ?>
        <div class="mb-3 d-flex align-items-center gap-3">
          <img src="<?= View::e($existingSig) ?>" alt="Current signature"
               style="height:56px;max-width:200px;object-fit:contain;border:1px solid #e5e7eb;border-radius:6px;background:#f9fafb;">
          <div>
            <div class="small fw-semibold mb-1">Current signature</div>
            <label class="form-check-label small text-danger">
              <input type="checkbox" name="remove_signature" value="1" class="form-check-input me-1">
              Remove this signature
            </label>
          </div>
        </div>
      <?php endif; ?>

      <div class="mb-4">
        <label class="form-label fw-semibold"><?= $existingSig ? 'Replace Signature' : 'Upload Signature' ?></label>
        <input type="file" name="headteacher_signature" class="form-control" accept="image/png,image/jpeg,image/webp,image/gif">
        <div class="form-text">Scanned signature image (PNG transparent background works best). Appears on admission letters, exam permits and report cards. Max 1.5 MB.</div>
      </div>

      <!-- ── Status ────────────────────────────────────────────────── -->
      <h6 class="text-uppercase text-muted small fw-bold letter-spacing-wide mb-3 pt-1 border-top">
        <i class="bi bi-toggle-on me-1"></i> Status
      </h6>

      <div class="mb-4">
        <select name="status" class="form-select" style="max-width:220px;">
          <option value="active"   <?= ($school['status'] ?? 'active') === 'active'   ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= ($school['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-check2-circle me-1"></i>
          <?= $isEdit ? 'Save Changes' : 'Create School' ?>
        </button>
        <a href="<?= $base ?>/schools<?= $isEdit ? '/' . (int) $school['id'] : '' ?>"
           class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
