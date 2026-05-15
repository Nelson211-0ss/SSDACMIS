<?php
use App\Core\View;
use App\Core\App;
use App\Core\Settings;
$layout = 'auth';
$title  = 'Sign in';
$schoolName  = Settings::get('school_name') ?: App::config('app.name');
$schoolMotto = Settings::get('school_motto') ?? '';
$schoolLogo  = Settings::logoUrl();
?>
<div class="auth-page-frame auth-page-frame--wave">
  <div class="auth-wave-bg" aria-hidden="true">
    <div class="auth-wave-bg__blob auth-wave-bg__blob--1"></div>
    <div class="auth-wave-bg__blob auth-wave-bg__blob--2"></div>
    <div class="auth-wave-bg__blob auth-wave-bg__blob--3"></div>
    <div class="auth-wave-bg__glow auth-wave-bg__glow--1"></div>
    <div class="auth-wave-bg__glow auth-wave-bg__glow--2"></div>
    <div class="auth-wave-bg__glow auth-wave-bg__glow--3"></div>
    <div class="auth-wave-bg__band"></div>
  </div>

  <div class="auth-shell auth-shell--wave">
    <section class="auth-brand" aria-labelledby="auth-brand-title">
      <a class="auth-brand__mark" href="<?= $base ?>/login">
        <span class="auth-brand__crown" aria-hidden="true">
          <svg width="22" height="18" viewBox="0 0 22 18" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M2 4 L5.5 11 L11 3 L16.5 11 L20 4 L18.5 14 H3.5 Z" fill="currentColor"/>
            <circle cx="2" cy="4" r="1.4" fill="currentColor"/>
            <circle cx="11" cy="3" r="1.4" fill="currentColor"/>
            <circle cx="20" cy="4" r="1.4" fill="currentColor"/>
          </svg>
        </span>
        <span class="auth-brand__mark-text"><?= View::e($schoolName !== '' ? $schoolName : 'Company Logo') ?></span>
      </a>

      <div class="auth-brand__copy">
        <span class="auth-brand__eyebrow">Academic management portal</span>

        <h1 class="auth-brand__h1" id="auth-brand-title">
          <span class="auth-brand__t1">Welcome to</span>
          <span class="auth-brand__t2">
            <span class="auth-brand__t2-l1 auth-brand__t2-l1--smis">
              <span class="auth-brand__t2-line">Students Management</span>
              <span class="auth-brand__t2-line">Information System-<span class="auth-brand__t2-em">SSDACMIS</span></span>
            </span>
          </span>
        </h1>

        <p class="auth-brand__sub">
          From enrolment and class lists to mark entry and term report cards —
          keep <strong><?= View::e($schoolName) ?></strong> running on one
          secure, beautifully simple platform.
        </p>

        <ul class="auth-brand__features" role="list">
          <li>
            <span class="auth-brand__feat-ic" aria-hidden="true"><i class="bi bi-mortarboard-fill"></i></span>
            <span><strong>Students, classes &amp; subjects</strong> organised in one place</span>
          </li>
          <li>
            <span class="auth-brand__feat-ic" aria-hidden="true"><i class="bi bi-clipboard-check-fill"></i></span>
            <span><strong>Mark entry workflows</strong> with built‑in HOD approvals</span>
          </li>
          <li>
            <span class="auth-brand__feat-ic" aria-hidden="true"><i class="bi bi-file-earmark-pdf-fill"></i></span>
            <span><strong>Branded PDF report cards</strong> ready every term</span>
          </li>
          <li>
            <span class="auth-brand__feat-ic" aria-hidden="true"><i class="bi bi-cash-coin"></i></span>
            <span><strong>Financial Module</strong> for fees, payments &amp; balances</span>
          </li>
        </ul>

        <p class="auth-brand__foot">
          <i class="bi bi-shield-check" aria-hidden="true"></i>
          One sign-in for administrators, HODs, bursars, staff &amp; students
        </p>
      </div>
    </section>

    <div class="auth-side auth-side--float">
      <div class="auth-side__inner">
        <div class="auth-wrap auth-wrap--wave">
          <div class="auth-card auth-card--wave auth-card--plain">
            <div class="auth-card__plain-head">
              <span class="auth-card__plain-badge" aria-hidden="true"><i class="bi bi-shield-lock-fill"></i></span>
              <h2 class="auth-card__wave-h2" id="login-title">Sign in</h2>
              <p class="auth-card__wave-sub">Continue to the workspace that matches your role.</p>
            </div>

            <?php if (!empty($error)): ?>
              <div class="auth-alert auth-alert--danger" role="alert">
                <i class="bi bi-exclamation-circle flex-shrink-0"></i>
                <div><?= View::e($error) ?></div>
              </div>
            <?php endif; ?>

            <form class="auth-form auth-form--wave" method="post" action="<?= $base ?>/login" novalidate>
              <input type="hidden" name="_csrf" value="<?= $csrf ?>">

              <div class="auth-field auth-field--labeled">
                <label class="auth-label-plain" for="email">Email</label>
                <div class="auth-input-wave">
                  <span class="auth-input-wave__ic" aria-hidden="true"><i class="bi bi-envelope"></i></span>
                  <input id="email"
                         type="email"
                         name="email"
                         class="form-control auth-input-wave__in"
                         placeholder="you@school.edu"
                         required autofocus
                         autocomplete="email"
                         value="<?= View::e($old['email'] ?? '') ?>">
                </div>
              </div>

              <div class="auth-field auth-field--labeled">
                <label class="auth-label-plain" for="password">Password</label>
                <div class="auth-input-wave">
                  <span class="auth-input-wave__ic" aria-hidden="true"><i class="bi bi-key"></i></span>
                  <input id="password"
                         type="password"
                         name="password"
                         class="form-control auth-input-wave__in"
                         placeholder="Enter your password"
                         required
                         autocomplete="current-password">
                </div>
              </div>

              <div class="auth-form__row d-flex flex-wrap align-items-center justify-content-between gap-2 mb-0">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="remember" value="1" id="auth-remember" <?= !empty($old['remember'] ?? null) ? 'checked' : '' ?>>
                  <label class="form-check-label auth-check-label-plain small" for="auth-remember">Remember me</label>
                </div>
                <a class="auth-forgot" href="#" onclick="return false;" title="Contact your school administrator to reset your password.">Forgot your password?</a>
              </div>

              <button type="submit" class="auth-btn auth-btn--wave" aria-describedby="login-title">
                <span>Sign In</span>
                <i class="bi bi-arrow-right" aria-hidden="true"></i>
              </button>
            </form>

          </div>

          <p class="auth-below">
            <span class="auth-below__hint"><i class="bi bi-life-preserver" aria-hidden="true"></i> Need help signing in?</span>
            <a href="mailto:support@ssd-acmis.local">Contact your administrator</a>
          </p>
        </div>
      </div>
    </div>
  </div>
</div>
