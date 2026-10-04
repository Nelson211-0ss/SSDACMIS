<?php
use App\Core\View;

$layout = 'landing';
$year   = date('Y');

/**
 * Backdrop slider. Classroom photographs that ship with the project, so
 * there is nothing to upload and no learner's face on a page that needs
 * no sign-in. Filtered against the filesystem because a missing file
 * would cross-fade to a blank frame rather than fail loudly.
 */
$backdrops = array_values(array_filter([
    'assets/img/login-slide-1.jpg',
    'assets/img/login-slide-2.jpg',
    'assets/img/login-slide-3.jpg',
    'assets/img/login-slide-4.jpg',
    'assets/img/login-hero.jpg',
], static fn (string $rel): bool => is_file(dirname(__DIR__, 3) . '/public/' . $rel)));

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
        'log-in'      => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>',
        'users'       => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'arrow-right' => '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
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

?>
<div class="lp">

  <?php if ($backdrops): ?>
    <?php /* Decorative only — the page says nothing that depends on them,
             so the whole thing is hidden from assistive tech and every
             image carries an empty alt. */ ?>
    <div class="lp-bg" data-lp-bg aria-hidden="true">
      <?php /* Painted as background-image rather than <img>: a backdrop has
               no content role, and background-size:cover does the same job
               as object-fit without needing a replaced element sized to the
               viewport. The URL rides in a custom property so the only
               inline style is data, not presentation. */ ?>
      <?php foreach ($backdrops as $i => $src): ?>
        <div class="lp-bg__img<?= $i === 0 ? ' is-active' : '' ?>" data-lp-bg-img
             style="--lp-bg-src: url('<?= View::e($base . '/' . $src) ?>')"></div>
      <?php endforeach; ?>
      <div class="lp-bg__scrim"></div>
    </div>
  <?php endif; ?>

  <main class="lp-main">
    <div class="lp-container lp-card">
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
  </main>

  <footer class="lp-footer">
    <div class="lp-container lp-footer__inner">
      <span>&copy; <?= (int) $year ?> SSD-ACMIS</span>
      <span class="lp-footer__by">Built by Nelson O. Ochan</span>
    </div>
  </footer>

</div>

<?php if (count($backdrops) > 1): ?>
<script>
(function () {
  'use strict';
  var bg = document.querySelector('[data-lp-bg]');
  if (!bg) return;

  var imgs = Array.prototype.slice.call(bg.querySelectorAll('[data-lp-bg-img]'));
  if (imgs.length < 2) return;

  // Backdrops are ambient, so they move slower than a content carousel
  // would and there are no controls to interrupt.
  var INTERVAL = 6000;
  var index = 0;
  var timer = null;

  function show(next) {
    index = (next + imgs.length) % imgs.length;
    imgs.forEach(function (img, i) { img.classList.toggle('is-active', i === index); });
  }

  function start() { stop(); timer = window.setInterval(function () { show(index + 1); }, INTERVAL); }
  function stop()  { if (timer) { window.clearInterval(timer); timer = null; } }

  // No sense burning a timer on a tab nobody is looking at.
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) { stop(); } else { start(); }
  });

  // Auto-advance is motion; honour the OS setting and hold the first frame.
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)');
  if (reduce && reduce.matches) return;
  start();
})();
</script>
<?php endif; ?>

