<?php $layout = 'auth'; $title = 'Forbidden'; ?>
<div class="error-page">
  <div class="text-center" style="max-width: 480px;">
    <p class="error-page__code">403</p>
    <h1 class="error-page__title">Access denied</h1>
    <p class="error-page__sub">
      You don't have permission to view this page. If you believe this is a
      mistake, contact your administrator.
    </p>
    <div class="d-flex gap-2 justify-content-center">
      <a class="btn btn-primary" href="<?= $base ?>/dashboard">
        <i class="bi bi-house-door"></i> Back to dashboard
      </a>
      <a class="btn btn-outline-secondary" href="<?= $base ?>/logout">
        <i class="bi bi-box-arrow-right"></i> Sign out
      </a>
    </div>
  </div>
</div>
