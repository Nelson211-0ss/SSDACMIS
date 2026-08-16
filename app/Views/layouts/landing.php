<?php
use App\Core\View;
$pageTitle = $title ?? 'SSDACMIS';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="SSD-ACMIS — a school management system for admissions, academics, exams, report cards, and fees.">
  <title><?= View::e($pageTitle) ?></title>
  <?php $schoolLogo = null; require __DIR__ . '/../partials/favicon.php'; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="<?= View::asset($base, 'assets/css/landing.css') ?>" rel="stylesheet">
</head>
<body class="landing-page">
  <?= $content ?>
</body>
</html>
