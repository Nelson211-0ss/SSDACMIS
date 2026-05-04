<?php $layout = 'auth'; $title = 'Page not found'; ?>
<div class="error-page">
  <div class="text-center" style="max-width: 480px;">
    <p class="error-page__code">404</p>
    <h1 class="error-page__title">Page not found</h1>
    <p class="error-page__sub">
      The page you're looking for doesn't exist, or it may have been moved.
    </p>
    <div class="d-flex gap-2 justify-content-center">
      <a class="btn btn-primary" href="<?= $base ?>/dashboard">
        <i class="bi bi-house-door"></i> Back to dashboard
      </a>
      <a class="btn btn-outline-secondary" href="javascript:history.back()">
        <i class="bi bi-arrow-left"></i> Go back
      </a>
    </div>
  </div>
</div>
