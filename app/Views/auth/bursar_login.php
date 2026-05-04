<?php
use App\Core\View;
use App\Core\App;
use App\Core\Settings;
$layout = 'auth';
$title  = 'Bursar sign in';
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
    <section class="auth-brand" aria-labelledby="auth-brand-bursar-title">
      <a class="auth-brand__mark" href="<?= $base ?>/bursar/login">
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
        <span class="auth-brand__eyebrow">Fees Management portal</span>

        <h1 class="auth-brand__h1" id="auth-brand-bursar-title">
          <span class="auth-brand__t1">Welcome,</span>
          <span class="auth-brand__t2">
            <span class="auth-brand__t2-l1">School</span>
            <span class="auth-brand__t2-l2">Bursar</span>
          </span>
        </h1>

        <p class="auth-brand__sub">
          A focused workspace for the bursar at <strong><?= View::e($schoolName) ?></strong> —
          set fees, record payments, watch balances update in real time, and
          generate per-class financial reports.
        </p>

        <ul class="auth-brand__features" role="list">
          <li>
            <span class="auth-brand__feat-ic" aria-hidden="true"><i class="bi bi-cash-coin"></i></span>
            <span><strong>Auto‑assigned fees</strong> by class &amp; section</span>
          </li>
          <li>
            <span class="auth-brand__feat-ic" aria-hidden="true"><i class="bi bi-receipt"></i></span>
            <span><strong>Receipts &amp; transaction history</strong> with overpayment guard</span>
          </li>
          <li>
            <span class="auth-brand__feat-ic" aria-hidden="true"><i class="bi bi-graph-up"></i></span>
            <span><strong>Paid / Balance reports</strong> per Form 1–4 · CSV &amp; print</span>
          </li>
        </ul>

        <p class="auth-brand__foot">
          <i class="bi bi-shield-check" aria-hidden="true"></i>
          Access restricted to bursars created by the system administrator
        </p>
      </div>
    </section>

    <div class="auth-side auth-side--float">
      <div class="auth-side__inner">
        <div class="auth-wrap auth-wrap--wave">
          <div class="auth-card auth-card--wave">
            <h2 class="auth-card__wave-h2" id="bursar-login-title">Bursar Login</h2>
            <p class="auth-card__wave-sub">Sign in to the Fees Management module</p>

            <?php if (!empty($error)): ?>
              <div class="auth-alert auth-alert--danger" role="alert">
                <i class="bi bi-exclamation-circle flex-shrink-0"></i>
                <div>
                  <?= View::e($error) ?>
                  <?php if (!empty($mainHint)): ?>
                    <div class="mt-2">
                      <a class="auth-card__link auth-card__link--wave" href="<?= $base ?>/login">Main school sign in <i class="bi bi-arrow-right small"></i></a>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($hodHint)): ?>
                    <div class="mt-2">
                      <a class="auth-card__link auth-card__link--wave" href="<?= $base ?>/hod/login">HOD portal <i class="bi bi-arrow-right small"></i></a>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endif; ?>

            <form class="auth-form auth-form--wave" method="post" action="<?= $base ?>/bursar/login" novalidate>
              <input type="hidden" name="_csrf" value="<?= $csrf ?>">

              <div class="auth-field">
                <label class="visually-hidden" for="bursar-email">Bursar email</label>
                <div class="auth-input-wave">
                  <span class="auth-input-wave__ic" aria-hidden="true"><i class="bi bi-person"></i></span>
                  <input id="bursar-email"
                         type="email"
                         name="email"
                         class="form-control auth-input-wave__in"
                         placeholder="Bursar email"
                         required autofocus
                         autocomplete="email"
                         value="<?= View::e($old['email'] ?? '') ?>">
                </div>
              </div>

              <div class="auth-field">
                <label class="visually-hidden" for="bursar-password">Password</label>
                <div class="auth-input-wave">
                  <span class="auth-input-wave__ic" aria-hidden="true"><i class="bi bi-lock"></i></span>
                  <input id="bursar-password"
                         type="password"
                         name="password"
                         class="form-control auth-input-wave__in"
                         placeholder="Password"
                         required
                         autocomplete="current-password">
                </div>
              </div>

              <div class="auth-form__row d-flex flex-wrap align-items-center justify-content-between gap-2 mb-0">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="remember" value="1" id="bursar-auth-remember" <?= !empty($old['remember'] ?? null) ? 'checked' : '' ?>>
                  <label class="form-check-label text-secondary small" for="bursar-auth-remember">Remember me</label>
                </div>
                <a class="auth-forgot" href="#" onclick="return false;" title="Contact your school administrator.">Forgot your password?</a>
              </div>

              <button type="submit" class="auth-btn auth-btn--wave" aria-describedby="bursar-login-title">
                <span>Sign In</span>
                <i class="bi bi-arrow-right" aria-hidden="true"></i>
              </button>
            </form>

            <div class="auth-card__foot auth-card__foot--wave">
              <p class="auth-foot-note">
                <i class="bi bi-buildings-fill" aria-hidden="true"></i>
                Not a bursar?
                <a class="auth-card__link auth-card__link--wave" href="<?= $base ?>/login">Main school sign in</a>
              </p>
            </div>
          </div>

          <p class="auth-below">
            <span class="auth-below__hint"><i class="bi bi-life-preserver" aria-hidden="true"></i> Need access?</span>
            <a href="mailto:support@ssd-acmis.local">Contact your administrator</a>
          </p>
        </div>
      </div>
    </div>
  </div>
</div>
