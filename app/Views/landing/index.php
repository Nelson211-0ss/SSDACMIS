<?php
use App\Core\View;
$layout = 'landing';
$year = date('Y');
?>
<header class="lp-nav" id="top">
  <div class="lp-container lp-nav__inner">
    <a class="lp-logo" href="<?= $base ?>/">
      <span class="lp-logo__mark"><i class="bi bi-mortarboard-fill"></i></span>
      <span class="lp-logo__text">SSD-ACMIS</span>
    </a>
    <button type="button" class="lp-nav__toggle" id="lpNavToggle" aria-label="Open menu" aria-expanded="false">
      <i class="bi bi-list"></i>
    </button>
    <nav class="lp-nav__links" id="lpNavLinks" aria-label="Primary">
      <a href="#modules">What it does</a>
      <a class="lp-btn lp-btn--primary lp-btn--sm" href="<?= $base ?>/login">Sign In</a>
    </nav>
  </div>
</header>

<main>
  <section class="lp-hero">
    <div class="lp-container lp-hero__inner reveal">
      <p class="lp-eyebrow">School Management System</p>
      <h1 class="lp-hero__title">SSD-ACMIS</h1>
      <p class="lp-hero__sub">
        Admissions, academics, exams, report cards, and fees — kept in one
        place instead of scattered registers and spreadsheets.
      </p>
      <div class="lp-hero__cta">
        <a class="lp-btn lp-btn--primary" href="<?= $base ?>/login"><i class="bi bi-box-arrow-in-right"></i> Sign In</a>
        <a class="lp-btn lp-btn--ghost" href="#modules">What it does</a>
      </div>
      <p class="lp-hero__portals">
        Head of Department? <a href="<?= $base ?>/hod/login">Sign in here</a>.
        Bursar? <a href="<?= $base ?>/bursar/login">Sign in here</a>.
      </p>
    </div>
  </section>

  <section class="lp-section" id="modules">
    <div class="lp-container">
      <h2 class="lp-section__title">What it does</h2>
      <div class="lp-features">
        <?php
        $features = [
          ['bi-people-fill', 'Students & Admissions', 'Admit one learner at a time, or a whole class at once from a CSV file.'],
          ['bi-calendar-check-fill', 'Attendance', 'Daily attendance taken per class by the class\'s own teacher.'],
          ['bi-pencil-square', 'Marks & Results', 'Teachers enter marks for what they teach; results roll up automatically.'],
          ['bi-file-earmark-text-fill', 'Report Cards', 'One student at a time, or a full printable booklet for the whole school.'],
          ['bi-cash-stack', 'Fees & Bursar', 'Fee structures, payments, receipts, balances, and exam permits.'],
          ['bi-shield-lock-fill', 'Role-Based Access', 'Admin, staff, HOD, bursar, and student each see only their own tools.'],
        ];
        foreach ($features as $f): ?>
        <article class="lp-feature-card reveal">
          <div class="lp-feature-card__icon"><i class="bi <?= $f[0] ?>"></i></div>
          <h3><?= View::e($f[1]) ?></h3>
          <p><?= View::e($f[2]) ?></p>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</main>

<footer class="lp-footer">
  <div class="lp-container lp-footer__inner">
    <p><strong>SSD-ACMIS</strong> &middot; School Management System</p>
    <p class="lp-footer__meta">&copy; <?= (int) $year ?> &middot; Built by Nelson O. Ochan &middot; <a href="<?= $base ?>/login">Sign in</a></p>
  </div>
</footer>
