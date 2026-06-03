<?php
use App\Core\View;
/**
 * Modern page header for app layout pages.
 * Vars: $pageTitle (required), $pageSubtitle (optional), $pageIcon (optional bi-* class suffix),
 *       $pageActionsHtml (optional raw HTML for action buttons).
 */
?>
<div class="page-header">
  <div>
    <h2 class="page-header__title">
      <?php if (!empty($pageIcon)): ?>
        <i class="bi <?= View::e($pageIcon) ?> page-header__icon" aria-hidden="true"></i>
      <?php endif; ?>
      <?= View::e((string) $pageTitle) ?>
    </h2>
    <?php if (!empty($pageSubtitle)): ?>
      <p class="page-header__sub mb-0"><?= $pageSubtitle ?></p>
    <?php endif; ?>
  </div>
  <?php if (!empty($pageActionsHtml)): ?>
    <div class="page-header__actions"><?= $pageActionsHtml ?></div>
  <?php endif; ?>
</div>
