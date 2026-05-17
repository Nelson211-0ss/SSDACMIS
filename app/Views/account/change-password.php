<?php
use App\Core\View;
$layout = 'app';
$title  = 'Change Password';
?>
<div class="mb-3">
  <h4 class="mb-1"><i class="bi bi-key"></i> Change Password</h4>
  <p class="text-muted small mb-0">Update your login password. You'll need your current password to confirm.</p>
</div>

<div class="card border-0 shadow-sm" style="max-width:480px;">
  <div class="card-body px-4 py-4">
    <form method="post" action="<?= $base ?>/account/password" novalidate>
      <input type="hidden" name="_csrf" value="<?= $csrf ?>">

      <div class="mb-3">
        <label class="form-label fw-semibold" for="cp-current">Current Password</label>
        <input id="cp-current" type="password" name="current_password" class="form-control"
               placeholder="Your current password" required autocomplete="current-password">
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold" for="cp-new">New Password</label>
        <input id="cp-new" type="password" name="password" class="form-control"
               placeholder="At least 8 characters" required minlength="8" autocomplete="new-password">
      </div>

      <div class="mb-4">
        <label class="form-label fw-semibold" for="cp-confirm">Confirm New Password</label>
        <input id="cp-confirm" type="password" name="password_confirmation" class="form-control"
               placeholder="Repeat your new password" required autocomplete="new-password">
      </div>

      <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-lg me-1"></i>Update Password
      </button>
    </form>
  </div>
</div>
