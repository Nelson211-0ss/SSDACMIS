<?php
use App\Core\View;
/** @var string $base */
/** @var string|null $schoolLogo */
?>
<link rel="icon" type="image/svg+xml" href="<?= View::asset($base, 'assets/icons/favicon.svg') ?>">
<link rel="icon" type="image/png" sizes="32x32" href="<?= View::asset($base, 'assets/icons/favicon-32.png') ?>">
<link rel="apple-touch-icon" href="<?= View::asset($base, 'assets/icons/apple-touch-icon.png') ?>">
<?php if (!empty($schoolLogo)): ?>
  <link rel="alternate icon" type="image/png" href="<?= $base ?>/<?= View::e($schoolLogo) ?>">
<?php endif; ?>
