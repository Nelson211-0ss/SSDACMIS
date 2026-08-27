<?php
use App\Core\View;
use App\Core\Settings;

$layout = 'auth';
$title  = 'Parent sign in';
$hideAuthFooter = true;
$authBodyClass = 'auth-page auth-page--login';

$schoolNameSetting = trim((string) Settings::get('school_name'));
$schoolMotto = Settings::get('school_motto') ?? '';
$schoolLogo  = Settings::logoUrl();
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
          <span class="auth-login__card-icon" aria-hidden="true"><i class="bi bi-person-hearts"></i></span>
        <?php endif; ?>
        <h1 class="auth-login__title" id="login-title">Parent sign in</h1>
        <p class="auth-login__sub">
          <?php if ($schoolMotto !== '' || $schoolName !== ''): ?>
            <?php if ($schoolMotto !== ''): ?>
              <?= View::e($schoolMotto) ?><?= $schoolName !== '' ? ' &middot; ' : '' ?>
            <?php endif; ?>
            <?php if ($schoolName !== ''): ?>
              <?= View::e($schoolName) ?><br>
            <?php endif; ?>
          <?php endif; ?>
          Use your child's admission number to sign in.
        </p>
      </div>

      <?php if (!empty($error)): ?>
        <div class="auth-login__alert" role="alert">
          <i class="bi bi-exclamation-circle flex-shrink-0"></i>
          <div><?= View::e($error) ?></div>
        </div>
      <?php endif; ?>

      <form class="auth-login__form" method="post" action="<?= $base ?>/parent/login" novalidate>
        <input type="hidden" name="_csrf" value="<?= $csrf ?>">

        <div class="auth-login__fields">
          <div class="auth-login__field">
            <label class="auth-login__label" for="admission_no">Admission number</label>
            <div class="auth-login__input-wrap">
              <i class="bi bi-person-badge" aria-hidden="true"></i>
              <input id="admission_no"
                     type="text"
                     name="admission_no"
                     class="auth-login__input"
                     placeholder="e.g. F1A001"
                     required
                     autofocus
                     autocomplete="username"
                     value="<?= View::e($old['admissionNo'] ?? '') ?>">
            </div>
          </div>

          <div class="auth-login__field">
            <label class="auth-login__label" for="password">Password</label>
            <div class="auth-login__input-wrap auth-login__input-wrap--password">
              <i class="bi bi-lock-fill" aria-hidden="true"></i>
              <input id="password"
                     type="password"
                     name="password"
                     class="auth-login__input"
                     placeholder="Same as the admission number above"
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
            <p class="auth-login__hint small text-muted mb-0 mt-1">
              Your password is the same admission number, typed again.
            </p>
          </div>
        </div>

        <button type="submit" class="auth-login__submit" aria-describedby="login-title">
          <span>Sign in</span>
          <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </button>
      </form>

      <p class="auth-login__help">
        Not a parent? <a href="<?= $base ?>/login">Staff sign in</a>
      </p>
    </div>

    <footer class="auth-login__footer" role="contentinfo">
      &copy; <?= date('Y') ?><?= $schoolName !== '' ? ' ' . View::e($schoolName) . ' &middot;' : '' ?>
      <strong>SSD-ACMIS</strong> by Nelson O. Ochan
    </footer>
  </main>
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
