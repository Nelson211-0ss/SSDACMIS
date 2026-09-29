<?php
use App\Core\View;

$layout = 'landing';
$year   = date('Y');

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
      <div class="lp-container">
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
