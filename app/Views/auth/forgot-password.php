<?php
use App\Core\View;
use App\Core\App;
use App\Core\Settings;
$layout     = 'auth';
$title      = 'Forgot Password';
$schoolName = Settings::get('school_name') ?: App::config('app.name');
?>
<div class="auth-page-frame">
  <div class="auth-shell" style="justify-content:center;align-items:center;min-height:100vh;">
    <div class="auth-wrap" style="max-width:420px;width:100%;margin:auto;padding:24px;">
      <div class="auth-card auth-card--plain">
        <div class="auth-card__plain-head">
          <span class="auth-card__plain-badge" aria-hidden="true"><i class="bi bi-envelope-at"></i></span>
          <h2 class="auth-card__wave-h2">Forgot Password</h2>
          <p class="auth-card__wave-sub">Enter your email address and we'll send you a reset link.</p>
        </div>

        <?php foreach (\App\Core\Flash::pull() as $f): ?>
          <div class="alert alert-<?= View::e($f['type']) ?> alert-dismissible fade show" role="alert">
            <?= View::e($f['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endforeach; ?>

        <form method="post" action="<?= $base ?>/forgot-password" novalidate>
          <input type="hidden" name="_csrf" value="<?= $csrf ?>">

          <div class="mb-3">
            <label class="form-label fw-semibold" for="fp-email">Email Address</label>
            <input id="fp-email" type="email" name="email" class="form-control"
                   placeholder="you@school.edu" required autofocus autocomplete="email">
          </div>

          <button type="submit" class="btn btn-primary w-100 mb-3">
            Send Reset Link
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
