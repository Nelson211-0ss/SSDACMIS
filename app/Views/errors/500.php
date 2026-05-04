<?php $layout = 'auth'; $title = 'Something went wrong'; ?>
<div class="error-page">
  <div class="text-center" style="max-width: 520px;">
    <p class="error-page__code">500</p>
    <h1 class="error-page__title">Something went wrong</h1>
    <p class="error-page__sub">
      The page couldn't be loaded right now. Our team has been notified and
      the issue has been recorded. Please try again in a moment.
    </p>
    <div class="d-flex gap-2 justify-content-center flex-wrap">
      <a class="btn btn-primary" href="<?= htmlspecialchars($base ?? '/', ENT_QUOTES) ?>/dashboard">
        <i class="bi bi-house-door"></i> Back to dashboard
      </a>
      <a class="btn btn-outline-secondary" href="javascript:history.back()">
        <i class="bi bi-arrow-left"></i> Go back
      </a>
      <a class="btn btn-outline-secondary" href="javascript:location.reload()">
        <i class="bi bi-arrow-clockwise"></i> Try again
      </a>
    </div>
  </div>
</div>
