<?php
/**
 * Render a student avatar — passport photo when available, two-letter
 * initials badge as a fallback.
 *
 * Inputs (all in scope when included):
 *   $base         (string)  app base path
 *   $av_photo     (string)  photo_path relative to /public, '' for none
 *   $av_first     (string)
 *   $av_last      (string)
 *   $av_size      (int)     pixel size, default 32
 *   $av_shape     (string)  'circle' (default) or 'square'
 *   $av_class     (string)  optional extra CSS classes for the wrapper
 *
 * Square mode renders a slightly rounded square frame with a stronger
 * border — ideal for ID-card style photos.
 */
use App\Core\View;

$avSize    = (int) ($av_size ?? 32);
$avFirst   = (string) ($av_first ?? '');
$avLast    = (string) ($av_last ?? '');
$avPhoto   = trim((string) ($av_photo ?? ''));
$avExtra   = (string) ($av_class ?? '');
$avShape   = ($av_shape ?? 'circle') === 'square' ? 'square' : 'circle';
$avFontPx  = max(10, (int) round($avSize * 0.42));
$avInitials = mb_strtoupper(
    mb_substr($avFirst, 0, 1, 'UTF-8') . mb_substr($avLast, 0, 1, 'UTF-8'),
    'UTF-8'
);
if ($avInitials === '') $avInitials = '?';

$shapeClass = $avShape === 'square' ? 'rounded-3' : 'rounded-circle';
$borderClass = $avShape === 'square' ? 'border border-2' : 'border';
?>
<?php if ($avPhoto !== ''): ?>
  <img src="<?= View::e(($base ?? '') . '/' . ltrim($avPhoto, '/')) ?>"
       alt=""
       loading="lazy"
       class="<?= $shapeClass ?> <?= $borderClass ?> flex-shrink-0 <?= View::e($avExtra) ?>"
       style="width: <?= $avSize ?>px; height: <?= $avSize ?>px; object-fit: cover;">
<?php else: ?>
  <span class="<?= $shapeClass ?> <?= $borderClass ?> bg-body-secondary text-secondary d-inline-flex align-items-center justify-content-center flex-shrink-0 <?= View::e($avExtra) ?>"
        style="width: <?= $avSize ?>px; height: <?= $avSize ?>px; font-size: <?= $avFontPx ?>px; font-weight: 600;"
        aria-hidden="true">
    <?= View::e($avInitials) ?>
  </span>
<?php endif; ?>
