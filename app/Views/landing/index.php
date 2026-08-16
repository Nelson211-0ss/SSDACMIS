<?php
use App\Core\View;
$layout = 'landing';
$year = date('Y');
$slides = ['login-slide-1.jpg', 'login-slide-2.jpg', 'login-slide-3.jpg', 'login-slide-4.jpg'];
?>
<div class="lp-screen">
  <div class="lp-screen__bg" aria-hidden="true">
    <?php foreach ($slides as $i => $s): ?>
    <div class="lp-screen__slide<?= $i === 0 ? ' is-active' : '' ?>" style="background-image:url('<?= $base ?>/assets/img/<?= $s ?>')"></div>
    <?php endforeach; ?>
    <div class="lp-screen__overlay"></div>
  </div>

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
    <div class="lp-container">
      <p class="lp-eyebrow">School Management System</p>
      <h1 class="lp-screen__title">SSD-ACMIS</h1>
      <p class="lp-screen__tagline">Admissions, academics, exams, and fees — in one place.</p>
      <div class="lp-hero__cta">
        <a class="lp-btn lp-btn--primary" href="<?= $base ?>/login"><i class="bi bi-box-arrow-in-right"></i> Sign In</a>
      </div>
      <p class="lp-hero__portals">
        <a href="<?= $base ?>/hod/login">HOD sign in</a> &middot; <a href="<?= $base ?>/bursar/login">Bursar sign in</a>
      </p>

      <ul class="lp-strip">
        <li><i class="bi bi-people-fill"></i> Students</li>
        <li><i class="bi bi-calendar-check-fill"></i> Attendance</li>
        <li><i class="bi bi-pencil-square"></i> Marks</li>
        <li><i class="bi bi-file-earmark-text-fill"></i> Reports</li>
        <li><i class="bi bi-cash-stack"></i> Fees</li>
        <li><i class="bi bi-shield-lock-fill"></i> Access Control</li>
      </ul>
    </div>
  </main>

  <footer class="lp-screen__footer">
    &copy; <?= (int) $year ?> SSD-ACMIS &middot; Nelson O. Ochan
  </footer>
</div>
