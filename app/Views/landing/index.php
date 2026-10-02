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
    <div class="lp-container lp-copy">
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

