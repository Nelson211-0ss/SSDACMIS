<?php
use App\Core\View;

$layout = 'landing';
$year   = date('Y');

/**
 * Feather icons (feathericons.com, MIT), inlined.
 *
 * Inline rather than the feather JS or an icon font: this page has to
 * paint in one screen with nothing below the fold, so it carries no
 * render-blocking icon stylesheet and no script that rewrites the DOM
 * after load. Every glyph is a 24×24 stroke path that takes its colour
 * from the surrounding text.
 */
$feather = static function (string $name, string $class = ''): string {
    $paths = [
        'book-open'   => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
        'log-in'      => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>',
        'users'       => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'arrow-right' => '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
        'file-text'   => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>',
        'edit-3'      => '<path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>',
        'bar-chart-2' => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
        'credit-card' => '<rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
        'calendar'    => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
    ];
    if (!isset($paths[$name])) {
        return '';
    }

    return '<svg class="ft' . ($class !== '' ? ' ' . $class : '') . '" viewBox="0 0 24 24"'
         . ' fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"'
         . ' stroke-linejoin="round" aria-hidden="true" focusable="false">'
         . $paths[$name] . '</svg>';
};

/**
 * The two real ways in, and the page's only call to action.
 *
 * /hod/login and /bursar/login both redirect to /login (Auth routes each
 * account to its own portal after sign-in), so listing them separately
 * would be three doors into the same room. Parents are genuinely separate
 * — they sign in with an admission number.
 */
$doors = [
    [
        'href'    => $base . '/login',
        'icon'    => 'log-in',
        'title'   => 'Staff sign in',
        'desc'    => 'Administrators, heads of department, teachers and bursars.',
        'primary' => true,
    ],
    [
        'href'    => $base . '/parent/login',
        'icon'    => 'users',
        'title'   => 'Parent portal',
        'desc'    => 'Your child’s results, attendance and fees.',
        'primary' => false,
    ],
];

/**
 * What the system does, shown one at a time beside the hero. This replaces
 * the old "What it covers" band — the same list, in the space the page
 * already had, so everything still fits on one screen.
 */
$slides = [
    ['file-text',   'Report cards', 'Termly results, graded and ready to print.'],
    ['edit-3',      'Mark entry',   'Mid-term and end-of-term, subject by subject.'],
    ['bar-chart-2', 'Analytics',    'Subject performance, ranked per class and school-wide.'],
    ['credit-card', 'Fees',         'Structures, payments, balances and receipts.'],
    ['calendar',    'Attendance',   'Daily registers that roll up into every report.'],
];
?>
<div class="lp">

  <header class="lp-nav">
    <div class="lp-container lp-nav__inner">
      <a class="lp-logo" href="<?= $base ?>/">
        <span class="lp-logo__mark"><?= $feather('book-open') ?></span>
        <span class="lp-logo__text">SSD-ACMIS</span>
      </a>
      <a class="lp-btn lp-btn--primary" href="<?= $base ?>/login">Sign in</a>
    </div>
  </header>

  <main class="lp-main">
    <div class="lp-container lp-grid">

      <div class="lp-copy">
        <p class="lp-eyebrow">School Management System</p>
        <h1 class="lp-title">Run the whole school from one place.</h1>
        <p class="lp-lede">
          Admissions, academics, examinations and fees — one record per student,
          from the day they join to their final report card.
        </p>

        <h2 class="lp-doors__heading" id="lp-doors-heading">Choose how you sign in</h2>
        <div class="lp-doors" role="list" aria-labelledby="lp-doors-heading">
          <?php foreach ($doors as $d): ?>
            <a class="lp-door <?= !empty($d['primary']) ? 'lp-door--primary' : '' ?>"
               role="listitem" href="<?= View::e($d['href']) ?>">
              <span class="lp-door__icon"><?= $feather($d['icon']) ?></span>
              <span class="lp-door__text">
                <span class="lp-door__title"><?= View::e($d['title']) ?></span>
                <span class="lp-door__desc"><?= View::e($d['desc']) ?></span>
              </span>
              <?= $feather('arrow-right', 'lp-door__arrow') ?>
            </a>
          <?php endforeach; ?>
        </div>
        <p class="lp-hint">
          Not sure? <strong>Staff sign in</strong> covers everyone who works at the school.
        </p>
      </div>

      <div class="lp-slider" data-lp-slider
           role="group" aria-roledescription="carousel" aria-label="What the system does">
        <div class="lp-slider__frame">
          <?php foreach ($slides as $i => [$sIcon, $sTitle, $sDesc]): ?>
            <figure class="lp-slide<?= $i === 0 ? ' is-active' : '' ?>" data-lp-slide
                    <?= $i === 0 ? '' : 'aria-hidden="true"' ?>>
              <span class="lp-slide__icon"><?= $feather($sIcon) ?></span>
              <figcaption class="lp-slide__caption">
                <span class="lp-slide__title"><?= View::e($sTitle) ?></span>
                <span class="lp-slide__desc"><?= View::e($sDesc) ?></span>
              </figcaption>
            </figure>
          <?php endforeach; ?>
        </div>

        <div class="lp-slider__dots" role="tablist" aria-label="Choose a slide">
          <?php foreach ($slides as $i => $_s): ?>
            <button type="button" class="lp-slider__dot<?= $i === 0 ? ' is-active' : '' ?>"
                    data-lp-dot="<?= $i ?>" role="tab"
                    aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                    aria-label="Slide <?= $i + 1 ?> of <?= count($slides) ?>"></button>
          <?php endforeach; ?>
        </div>
      </div>

    </div>
  </main>

  <footer class="lp-footer">
    <div class="lp-container lp-footer__inner">
      <span>&copy; <?= (int) $year ?> SSD-ACMIS</span>
      <span class="lp-footer__by">Built by Nelson O. Ochan</span>
    </div>
  </footer>

</div>

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

  // Don't move things under someone who is reading or tabbing through
  // them, and don't run at all in a background tab.
  root.addEventListener('mouseenter', stop);
  root.addEventListener('mouseleave', start);
  root.addEventListener('focusin', stop);
  root.addEventListener('focusout', start);
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) { stop(); } else { start(); }
  });

  // Auto-advance is motion; honour the OS setting and stay on slide 1.
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)');
  if (reduce && reduce.matches) return;
  start();
})();
</script>
