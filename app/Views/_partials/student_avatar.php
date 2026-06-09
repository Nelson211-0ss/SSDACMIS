<?php
/**
 * Render a student avatar — passport photo when available, two-letter
 * initials badge as a fallback (WhatsApp-style square + color).
 *
 * Inputs (all in scope when included):
 *   $base         (string)  app base path
 *   $av_photo     (string)  photo_path relative to /public, '' for none
 *   $av_first     (string)
 *   $av_last      (string)
 *   $av_size      (int)     pixel size, default 32
 *   $av_shape     (string)  'square' (default) or 'circle'
 *   $av_class     (string)  optional extra CSS classes for the wrapper
 */
use App\Core\View;

$avSize    = (int) ($av_size ?? 32);
$avFirst   = (string) ($av_first ?? '');
$avLast    = (string) ($av_last ?? '');
$avPhoto   = trim((string) ($av_photo ?? ''));
$avExtra   = (string) ($av_class ?? '');
$avShape   = ($av_shape ?? 'square') === 'circle' ? 'circle' : 'square';
$avFontPx  = max(10, (int) round($avSize * 0.38));
$avInitials = mb_strtoupper(
    mb_substr($avFirst, 0, 1, 'UTF-8') . mb_substr($avLast, 0, 1, 'UTF-8'),
    'UTF-8'
);
if ($avInitials === '') {
    $avInitials = '?';
}

$colorClass = View::studentAvatarColorClass($avFirst, $avLast);
$shapeClass = $avShape === 'circle' ? 'stu-avatar--circle' : 'stu-avatar--square';
$baseClass  = 'stu-avatar ' . $shapeClass . ' ' . $colorClass;
?>
<?php if ($avPhoto !== ''): ?>
  <img src="<?= View::e(($base ?? '') . '/' . ltrim($avPhoto, '/')) ?>"
       alt=""
       loading="lazy"
       class="<?= $baseClass ?> <?= View::e($avExtra) ?>"
       style="width: <?= $avSize ?>px; height: <?= $avSize ?>px;">
<?php else: ?>
  <span class="<?= $baseClass ?> <?= View::e($avExtra) ?>"
        style="width: <?= $avSize ?>px; height: <?= $avSize ?>px; font-size: <?= $avFontPx ?>px;"
        aria-hidden="true"
        title="<?= View::e(trim($avFirst . ' ' . $avLast)) ?>">
    <?= View::e($avInitials) ?>
  </span>
<?php endif; ?>
