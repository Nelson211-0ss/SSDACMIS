<?php
use App\Core\View;
use App\Core\App;
use App\Core\Settings;

$schoolName  = Settings::get('school_name') ?: App::config('app.name');
$schoolMotto = Settings::get('school_motto') ?? '';
$schoolLogo  = Settings::logoUrl();
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= View::e($title ?? 'Sign in') ?> &middot; <?= View::e($schoolName) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="<?= View::asset($base, 'assets/css/app.css') ?>" rel="stylesheet">
  <?php if ($schoolLogo): ?>
    <link rel="icon" type="image/png" href="<?= $base ?>/<?= View::e($schoolLogo) ?>">
  <?php endif; ?>
</head>
<body class="auth-page auth-page--plain">
  <?= $content ?>
  <footer class="auth-credit" role="contentinfo">
    &copy; <?= date('Y') ?> <?= View::e($schoolName) ?> &middot;
    <strong>SSD-ACMIS</strong> by Nelson O. Ochan
    <span class="auth-credit__sep">|</span>
    SSD-iT Solutions
  </footer>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
