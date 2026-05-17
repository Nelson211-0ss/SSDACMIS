<?php
use App\Core\View;
use App\Core\App;
use App\Core\Settings;
$layout     = 'auth';
$title      = 'Reset Password';
$schoolName = Settings::get('school_name') ?: App::config('app.name');
?>
<div class="auth-page-frame">
  <div class="auth-shell" style="justify-content:center;align-items:center;min-height:100vh;">
    <div class="auth-wrap" style="max-width:420px;width:100%;margin:auto;padding:24px;">
      <div class="auth-card auth-card--plain">
        <div class="auth-card__plain-head">
          <span class="auth-card__plain-badge" aria-hidden="true"><i class="bi bi-key"></i></span>
          <h2 class="auth-card__wave-h2">Set New Password</h2>
          <p class="auth-card__wave-sub">Choose a strong password (minimum 8 characters).</p>
        </div>

        <?php foreach (\App\Core\Flash::pull() as $f): ?>
          <div class="alert alert-<?= View::e($f['type']) ?> alert-dismissible fade show" role="alert">
            <?= View::e($f['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endforeach; ?>

        <form method="post" action="<?= $base ?>/reset-password" novalidate>
          <input type="hidden" name="_csrf" value="<?= $csrf ?>">
          <input type="hidden" name="token" value="<?= View::e($token ?? '') ?>">

          <div class="mb-3">
            <label class="form-label fw-semibold" for="rp-password">New Password</label>
            <input id="rp-password" type="password" name="password" class="form-control"
                   placeholder="At least 8 characters" required minlength="8" autocomplete="new-password">
          </div>

          <div class="mb-4">
            <label class="form-label fw-semibold" for="rp-confirm">Confirm Password</label>
            <input id="rp-confirm" type="password" name="password_confirmation" class="form-control"
                   placeholder="Repeat your new password" required autocomplete="new-password">
          </div>

          <button type="submit" class="btn btn-primary w-100 mb-3">
            Update Password
          </button>
        </form>

        <div class="text-center small">
          <a href="<?= $base ?>/login" class="text-muted">
            <i class="bi bi-arrow-left me-1"></i>Back to Sign In
          </a>
        </div>
      </div>
    </div>
  </div>
</div>
