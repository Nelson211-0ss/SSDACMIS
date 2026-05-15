<?php /** Admin operations KPI cards — included inside dash-kpi-grid with At a glance. */ ?>
<div class="dash-kpi-grid__item">
  <a href="<?= $base ?>/hods" class="kpi-card kpi-card--compact">
    <div class="kpi-card__icon kpi-card__icon--purple"><i class="bi bi-mortarboard-fill"></i></div>
    <div class="kpi-card__body">
      <div class="kpi-card__label">Heads of Department</div>
      <div class="kpi-card__value"><?= number_format($hodCount) ?></div>
      <div class="kpi-card__delta kpi-card__delta--flat">
        <i class="bi bi-bookmark-star"></i> Department leads
      </div>
    </div>
  </a>
</div>
<div class="dash-kpi-grid__item">
  <a href="<?= $base ?>/bursars" class="kpi-card kpi-card--compact">
    <div class="kpi-card__icon kpi-card__icon--teal"><i class="bi bi-cash-coin"></i></div>
    <div class="kpi-card__body">
      <div class="kpi-card__label">Bursars</div>
      <div class="kpi-card__value"><?= number_format($bursarCount) ?></div>
      <div class="kpi-card__delta kpi-card__delta--flat">
        <i class="bi bi-wallet2"></i> Fees module
      </div>
    </div>
  </a>
</div>
<div class="dash-kpi-grid__item">
  <a href="<?= $base ?>/attendance" class="kpi-card kpi-card--compact">
    <div class="kpi-card__icon kpi-card__icon--info"><i class="bi bi-calendar-check"></i></div>
    <div class="kpi-card__body">
      <div class="kpi-card__label">Attendance today</div>
      <div class="kpi-card__value"><?= $attRate !== null ? $attRate . '%' : '—' ?></div>
      <div class="kpi-card__delta kpi-card__delta--<?= $attTotal > 0 ? 'up' : 'flat' ?>">
        <i class="bi bi-check2"></i>
        <?= number_format($attPresent) ?> present
        <?php if ($attAbsent > 0): ?>
          &middot; <?= number_format($attAbsent) ?> absent
        <?php endif; ?>
      </div>
    </div>
  </a>
</div>
<div class="dash-kpi-grid__item">
  <a href="<?= $base ?>/teaching" class="kpi-card kpi-card--compact">
    <div class="kpi-card__icon kpi-card__icon--warning"><i class="bi bi-diagram-3"></i></div>
    <div class="kpi-card__body">
      <div class="kpi-card__label">Teaching slots</div>
      <div class="kpi-card__value"><?= number_format($teachingCount) ?></div>
      <div class="kpi-card__delta kpi-card__delta--<?= $unassignedCount > 0 ? 'down' : 'flat' ?>">
        <?php if ($unassignedCount > 0): ?>
          <i class="bi bi-exclamation-circle"></i>
          <?= number_format($unassignedCount) ?> unassigned
        <?php else: ?>
          <i class="bi bi-check2-circle"></i> All students placed
        <?php endif; ?>
      </div>
    </div>
  </a>
</div>
