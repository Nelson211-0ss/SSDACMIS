<?php
use App\Core\View;
use App\Core\Settings;

$layout = 'auth';
$title  = 'Sign in';
$hideAuthFooter = true;
$authBodyClass = 'auth-page auth-page--login';

$schoolNameSetting = trim((string) Settings::get('school_name'));
$schoolMotto = Settings::get('school_motto') ?? '';
$schoolLogo  = Settings::logoUrl();
// On the login page, only show a configured school name — never the global
// APP_NAME fallback ("SSD-ACMIS — School Management System").
$schoolName  = $schoolNameSetting;
?>
<div class="auth-login">
  <div class="auth-login__bg" aria-hidden="true"></div>

  <header class="auth-login__nav">
    <a class="auth-login__logo" href="<?= $base ?>/">
      <span class="auth-login__logo-mark"><i class="bi bi-mortarboard-fill"></i></span>
      <span class="auth-login__logo-text">SSDA<span class="auth-login__logo-accent">CMIS</span></span>
    </a>
    <a class="auth-login__nav-link" href="<?= $base ?>/"><i class="bi bi-arrow-left"></i> Back to website</a>
  </header>

  <main class="auth-login__main">
    <div class="auth-login__card">
      <div class="auth-login__card-head">
        <?php if ($schoolLogo): ?>
          <img src="<?= $base ?>/<?= View::e($schoolLogo) ?>" alt="" class="auth-login__school-logo">
        <?php else: ?>
          <span class="auth-login__card-icon" aria-hidden="true"><i class="bi bi-shield-lock-fill"></i></span>
        <?php endif; ?>
        <p class="auth-login__eyebrow">Academic Management Portal</p>
        <h1 class="auth-login__title" id="login-title">Sign in</h1>
        <?php if ($schoolMotto !== '' || $schoolName !== ''): ?>
          <p class="auth-login__sub">
            <?php if ($schoolMotto !== ''): ?>
              <?= View::e($schoolMotto) ?><?= $schoolName !== '' ? ' &middot; ' : '' ?>
            <?php endif; ?>
            <?php if ($schoolName !== ''): ?>
              <?= View::e($schoolName) ?>
            <?php endif; ?>
          </p>
        <?php endif; ?>
      </div>

      <?php if (!empty($error)): ?>
        <div class="auth-login__alert" role="alert">
          <i class="bi bi-exclamation-circle flex-shrink-0"></i>
          <div><?= View::e($error) ?></div>
        </div>
      <?php endif; ?>

      <form class="auth-login__form" method="post" action="<?= $base ?>/login" novalidate>
        <input type="hidden" name="_csrf" value="<?= $csrf ?>">

        <div class="auth-login__fields">
          <div class="auth-login__field">
            <label class="auth-login__label" for="email">Email address</label>
            <div class="auth-login__input-wrap">
              <i class="bi bi-envelope" aria-hidden="true"></i>
              <input id="email"
                     type="email"
                     name="email"
                     class="auth-login__input"
                     placeholder="you@school.edu"
                     required
                     autofocus
                     autocomplete="email"
                     value="<?= View::e($old['email'] ?? '') ?>">
            </div>
          </div>

          <div class="auth-login__field">
            <div class="auth-login__label-row">
              <label class="auth-login__label" for="password">Password</label>
              <a class="auth-login__forgot auth-login__forgot--inline" href="<?= $base ?>/forgot-password">Forgot?</a>
            </div>
            <div class="auth-login__input-wrap auth-login__input-wrap--password">
              <i class="bi bi-lock-fill" aria-hidden="true"></i>
              <input id="password"
                     type="password"
                     name="password"
                     class="auth-login__input"
                     placeholder="Enter your password"
                     required
                     autocomplete="current-password">
              <button type="button"
                      class="auth-login__toggle-pw"
                      data-password-toggle
                      aria-label="Show password"
                      aria-pressed="false"
                      title="Show password">
                <i class="bi bi-eye" aria-hidden="true"></i>
              </button>
            </div>
          </div>
        </div>

        <div class="auth-login__row">
          <label class="auth-login__remember">
            <input type="checkbox" name="remember" value="1" id="auth-remember" <?= !empty($old['remember'] ?? null) ? 'checked' : '' ?>>
            <span>Remember me on this device</span>
          </label>
        </div>

        <button type="submit" class="auth-login__submit" aria-describedby="login-title">
          <span>Sign in</span>
          <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </button>
      </form>

      <p class="auth-login__help">
        <i class="bi bi-life-preserver" aria-hidden="true"></i>
        Need help? <a href="mailto:support@ssd-acmis.local">Contact your administrator</a>
      </p>
    </div>
  </main>

  <footer class="auth-login__footer" role="contentinfo">
    &copy; <?= date('Y') ?><?= $schoolName !== '' ? ' ' . View::e($schoolName) . ' &middot;' : '' ?>
    <strong>SSDACMIS</strong> by SSD IT Solutions
  </footer>
</div>
<script>
(function () {
  var btn = document.querySelector('[data-password-toggle]');
  var input = document.getElementById('password');
  if (!btn || !input) return;

  btn.addEventListener('click', function () {
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    btn.setAttribute('aria-pressed', show ? 'true' : 'false');
    btn.title = show ? 'Hide password' : 'Show password';
    var icon = btn.querySelector('i');
    if (icon) {
      icon.classList.toggle('bi-eye', !show);
      icon.classList.toggle('bi-eye-slash', show);
    }
    input.focus();
  });
})();
</script>
