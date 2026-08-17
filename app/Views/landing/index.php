<?php
use App\Core\View;
$layout = 'landing';
$year = date('Y');
?>
<div class="lp-screen">
  <div class="lp-screen__bg" aria-hidden="true"></div>

  <header class="lp-nav">
    <div class="lp-container lp-nav__inner">
      <a class="lp-logo" href="<?= $base ?>/">
        <span class="lp-logo__mark"><i class="bi bi-mortarboard-fill"></i></span>
        <span class="lp-logo__text">SSD-ACMIS</span>
      </a>
      <a class="lp-btn lp-btn--primary lp-btn--sm" href="<?= $base ?>/login">Sign In</a>
    </div>
  </header>

  <main class="lp-screen__main">
    <div class="lp-container lp-hero">
      <div class="lp-hero__lede">
        <p class="lp-eyebrow">School Management System</p>
        <h1 class="lp-screen__title">SSD-ACMIS</h1>
        <p class="lp-screen__tagline">Admissions, academics, exams, and fees — in one place.</p>
      </div>
      <div class="lp-hero__cta">
        <a class="lp-btn lp-btn--primary" href="<?= $base ?>/login"><i class="bi bi-box-arrow-in-right"></i> Sign In</a>
      </div>
      <p class="lp-hero__portals">
        <a href="<?= $base ?>/hod/login">HOD sign in</a> &middot; <a href="<?= $base ?>/bursar/login">Bursar sign in</a>
      </p>

      <div class="lp-cards">
        <?php
        $cards = [
          ['bi-mortarboard-fill', 'Academics', 'Attendance, marks & report cards.'],
          ['bi-people-fill', 'Admissions', 'Add students one by one, or a class at once from CSV.'],
          ['bi-cash-stack', 'Finance', 'Fee structures, payments & balances.'],
          ['bi-shield-lock-fill', 'Access Control', 'Every role sees only its own tools.'],
        ];
        foreach ($cards as $c): ?>
        <article class="lp-card">
          <div class="lp-card__icon"><i class="bi <?= $c[0] ?>"></i></div>
          <h3 class="lp-card__title"><?= View::e($c[1]) ?></h3>
          <p class="lp-card__brief"><?= View::e($c[2]) ?></p>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </main>

  <footer class="lp-screen__footer">
    &copy; <?= (int) $year ?> SSD-ACMIS &middot; Nelson O. Ochan
  </footer>
</div>
