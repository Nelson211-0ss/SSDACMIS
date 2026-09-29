<?php
use App\Core\View;

$layout = 'landing';
$year   = date('Y');
/** Public-relative student photo paths; empty when switched off or none uploaded. */
$slides = $slides ?? [];

/** What the system covers — one line each, no marketing padding. */
$features = [
    ['bi-mortarboard-fill',  'Academics',   'Attendance, mark entry, computed results and printable report cards.'],
    ['bi-people-fill',       'Admissions',  'Admit students one at a time, or a whole class at once from a CSV file.'],
    ['bi-cash-stack',        'Finance',     'Fee structures, payments, balances, receipts and examination permits.'],
    ['bi-shield-lock-fill',  'Permissions', 'Every role sees only its own tools, and admins decide exactly what that is.'],
];

/**
 * Two real entry points. /hod/login and /bursar/login both redirect to
 * /login (Auth routes each account to its own portal after sign-in), so
 * listing them separately would just be three doors into the same room.
 * Parents are genuinely separate — they sign in with an admission number.
 */
$entries = [
    [
        'href'  => $base . '/login',
        'icon'  => 'bi-box-arrow-in-right',
        'title' => 'Staff sign in',
        'desc'  => 'Administrators, heads of department, teachers and bursars.',
        'primary' => true,
    ],
    [
        'href'  => $base . '/parent/login',
        'icon'  => 'bi-people',
        'title' => 'Parent portal',
        'desc'  => 'Follow your child’s results, attendance and fees.',
        'primary' => false,
    ],
];
?>
<div class="lp">

  <header class="lp-nav">
    <div class="lp-container lp-nav__inner">
      <a class="lp-logo" href="<?= $base ?>/">
        <span class="lp-logo__mark" aria-hidden="true"><i class="bi bi-mortarboard-fill"></i></span>
        <span class="lp-logo__text">SSD-ACMIS</span>
      </a>
      <a class="lp-btn lp-btn--primary lp-btn--sm" href="<?= $base ?>/login">Sign in</a>
    </div>
  </header>

  <main>
    <!-- Hero -->
    <section class="lp-hero">
      <div class="lp-container lp-hero__grid<?= $slides ? '' : ' lp-hero__grid--solo' ?>">
        <div class="lp-hero__copy">
          <p class="lp-eyebrow">School Management System</p>
          <h1 class="lp-hero__title">Run the whole school from one place.</h1>
          <p class="lp-hero__lede">
            SSD-ACMIS keeps admissions, academics, examinations and fees in a single
            record for every student — from admission through to the final report card.
          </p>
          <div class="lp-hero__actions">
            <a class="lp-btn lp-btn--primary" href="<?= $base ?>/login">
              Sign in <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </a>
            <a class="lp-btn lp-btn--ghost" href="<?= $base ?>/parent/login">Parent portal</a>
          </div>
        </div>

        <?php if ($slides): ?>
          <!-- Student photo slider. Decorative: the faces carry no names, so
               it is labelled rather than described, and screen readers get
               one summary instead of N empty images. -->
          <div class="lp-slider" data-lp-slider
               role="group" aria-roledescription="carousel" aria-label="Students at our school">
            <div class="lp-slider__frame">
              <?php foreach ($slides as $i => $photo): ?>
                <?php /* Every slide loads eagerly: the slider advances on a
                         timer, so a lazy image would show as a blank frame
                         when its turn came before the browser fetched it —
                         guaranteed on mobile, where the slider starts below
                         the fold. Only the first one competes for priority. */ ?>
                <figure class="lp-slide<?= $i === 0 ? ' is-active' : '' ?>" data-lp-slide
                        <?= $i === 0 ? '' : 'aria-hidden="true"' ?>>
                  <img src="<?= $base ?>/<?= View::e($photo) ?>" alt=""
                       loading="eager" decoding="async"
                       fetchpriority="<?= $i === 0 ? 'high' : 'low' ?>">
                </figure>
              <?php endforeach; ?>
            </div>

            <?php if (count($slides) > 1): ?>
              <div class="lp-slider__dots" role="tablist" aria-label="Choose a photo">
                <?php foreach ($slides as $i => $_p): ?>
                  <button type="button" class="lp-slider__dot<?= $i === 0 ? ' is-active' : '' ?>"
                          data-lp-dot="<?= $i ?>" role="tab"
                          aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                          aria-label="Photo <?= $i + 1 ?> of <?= count($slides) ?>"></button>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <!-- What it covers -->
    <section class="lp-section">
      <div class="lp-container">
        <h2 class="lp-section__title">What it covers</h2>
        <ul class="lp-features">
          <?php foreach ($features as [$icon, $title, $desc]): ?>
            <li class="lp-feature">
              <span class="lp-feature__icon" aria-hidden="true"><i class="bi <?= $icon ?>"></i></span>
              <h3 class="lp-feature__title"><?= View::e($title) ?></h3>
              <p class="lp-feature__desc"><?= View::e($desc) ?></p>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </section>

    <!-- Ways in -->
    <section class="lp-section lp-section--soft">
      <div class="lp-container">
        <h2 class="lp-section__title">Sign in</h2>
        <div class="lp-entries">
          <?php foreach ($entries as $e): ?>
            <a class="lp-entry <?= !empty($e['primary']) ? 'lp-entry--primary' : '' ?>"
               href="<?= View::e($e['href']) ?>">
              <span class="lp-entry__icon" aria-hidden="true"><i class="bi <?= $e['icon'] ?>"></i></span>
              <span class="lp-entry__text">
                <span class="lp-entry__title"><?= View::e($e['title']) ?></span>
                <span class="lp-entry__desc"><?= View::e($e['desc']) ?></span>
              </span>
              <i class="bi bi-arrow-right lp-entry__arrow" aria-hidden="true"></i>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  </main>

  <footer class="lp-footer">
    <div class="lp-container lp-footer__inner">
      <span>&copy; <?= (int) $year ?> SSD-ACMIS</span>
      <span class="lp-footer__by">Built by Nelson O. Ochan</span>
    </div>
  </footer>

</div>

<?php if (count($slides) > 1): ?>
<script>
(function () {
  'use strict';
  var root = document.querySelector('[data-lp-slider]');
  if (!root) return;

  var slides = Array.prototype.slice.call(root.querySelectorAll('[data-lp-slide]'));
  var dots   = Array.prototype.slice.call(root.querySelectorAll('[data-lp-dot]'));
  if (slides.length < 2) return;

  var INTERVAL = 4500;
  var index = 0;
  var timer = null;

  function show(next) {
    index = (next + slides.length) % slides.length;
    slides.forEach(function (s, i) {
      var on = i === index;
      s.classList.toggle('is-active', on);
      // aria-hidden rather than display:none so the cross-fade still works.
      if (on) { s.removeAttribute('aria-hidden'); } else { s.setAttribute('aria-hidden', 'true'); }
    });
    dots.forEach(function (d, i) {
      var on = i === index;
      d.classList.toggle('is-active', on);
      d.setAttribute('aria-selected', on ? 'true' : 'false');
    });
  }

  function start() { stop(); timer = window.setInterval(function () { show(index + 1); }, INTERVAL); }
  function stop()  { if (timer) { window.clearInterval(timer); timer = null; } }

  dots.forEach(function (d, i) {
    d.addEventListener('click', function () { show(i); start(); });
  });

  // Don't move the photos under someone who is looking at or tabbing
  // through them, and don't run at all in a background tab.
  root.addEventListener('mouseenter', stop);
  root.addEventListener('mouseleave', start);
  root.addEventListener('focusin', stop);
  root.addEventListener('focusout', start);
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) { stop(); } else { start(); }
  });

  // Auto-advance is motion; honour the OS setting and leave it on slide 1.
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)');
  if (reduce && reduce.matches) return;
  start();
})();
</script>
<?php endif; ?>
