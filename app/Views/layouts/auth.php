<?php
use App\Core\View;
use App\Core\App;
use App\Core\Settings;

$schoolNameSetting = trim((string) Settings::get('school_name'));
$schoolMotto = Settings::get('school_motto') ?? '';
$schoolLogo  = Settings::logoUrl();
$bodyClass   = $authBodyClass ?? 'auth-page auth-page--plain';
$isLoginPage = str_contains($bodyClass, 'auth-page--login');
$pageBrand   = $schoolNameSetting !== ''
    ? $schoolNameSetting
    : ($isLoginPage ? 'SSDACMIS' : App::config('app.name'));
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= View::e($title ?? 'Sign in') ?> &middot; <?= View::e($pageBrand) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <?php if ($isLoginPage): ?>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Manrope:wght@600;700;800&family=Poppins:wght@500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="<?= View::asset($base, 'assets/css/auth-login.css') ?>" rel="stylesheet">
  <?php else: ?>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="<?= View::asset($base, 'assets/css/app.css') ?>" rel="stylesheet">
  <?php endif; ?>
  <?php require __DIR__ . '/../partials/favicon.php'; ?>
</head>
<body class="<?= View::e($bodyClass) ?>">
  <?= $content ?>
  <?php if (empty($hideAuthFooter)): ?>
  <footer class="auth-credit" role="contentinfo">
    &copy; <?= date('Y') ?> <?= View::e($pageBrand) ?> &middot;
    <strong>SSD-ACMIS</strong> by Nelson O. Ochan
    <span class="auth-credit__sep">|</span>
    SSD-iT Solutions
  </footer>
  <?php endif; ?>
  <?php if (!$isLoginPage): ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php endif; ?>
</body>
</html>
