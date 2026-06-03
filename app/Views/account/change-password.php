<?php
use App\Core\View;
$layout = 'app';
$title  = 'Change Password';

$pageTitle = 'Change password';
$pageSubtitle = 'Update your login password. You need your current password to confirm.';
$pageIcon = 'bi-key';
include dirname(__DIR__) . '/_partials/app_page_header.php';
?>

<div class="card" style="max-width:480px;">
  <div class="card-body px-4 py-4">
    <form method="post" action="<?= $base ?>/account/password" novalidate>
      <input type="hidden" name="_csrf" value="<?= $csrf ?>">

      <div class="mb-3">
        <label class="form-label fw-semibold" for="cp-current">Current password</label>
        <input id="cp-current" type="password" name="current_password" class="form-control"
               placeholder="Your current password" required autocomplete="current-password">
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold" for="cp-new">New password</label>
        <input id="cp-new" type="password" name="password" class="form-control"
               placeholder="At least 8 characters" required minlength="8" autocomplete="new-password">
      </div>

      <div class="mb-4">
        <label class="form-label fw-semibold" for="cp-confirm">Confirm new password</label>
        <input id="cp-confirm" type="password" name="password_confirmation" class="form-control"
               placeholder="Repeat your new password" required autocomplete="new-password">
      </div>

      <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-lg"></i> Update password
      </button>
    </form>
  </div>
</div>
