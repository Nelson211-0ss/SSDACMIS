<?php
/**
 * One ID card face. Included (not rendered via View::render) so it shares
 * the including page's variable scope and its <style> block — this partial
 * emits markup only, never its own <style>, so it can be looped many times
 * on the bulk sheet without duplicating CSS per card.
 *
 * Expects in scope:
 *   $base    (string) app base path
 *   $student (array)  first_name, last_name, admission_no, class_name,
 *                      level, section, photo_path
 *   $school  (array)  name, logo (public-relative path or '')
 *   $theme   (array)  Settings::THEMES entry — label, accent, accent_hover,
 *                      accent_soft, accent_rgb
 */
use App\Core\View;

$icFirst    = (string) ($student['first_name'] ?? '');
$icLast     = (string) ($student['last_name'] ?? '');
$icPhoto    = trim((string) ($student['photo_path'] ?? ''));
$icClass    = trim((string) ($student['class_name'] ?? '')) ?: trim((string) ($student['level'] ?? ''));
$icSection  = strtolower((string) ($student['section'] ?? 'day'));
$icSectionL = $icSection === 'boarding' ? 'Boarding' : 'Day';
$icInitials = mb_strtoupper(
    mb_substr($icFirst, 0, 1, 'UTF-8') . mb_substr($icLast, 0, 1, 'UTF-8'),
    'UTF-8'
);
if ($icInitials === '') $icInitials = '?';

$icSchoolName = (string) ($school['name'] ?? '');
$icSchoolLogo = trim((string) ($school['logo'] ?? ''));
?>
<div class="id-card"
     style="--ic-accent: <?= View::e($theme['accent'] ?? '#2563eb') ?>;
            --ic-accent-hover: <?= View::e($theme['accent_hover'] ?? '#1d4ed8') ?>;
            --ic-accent-soft: <?= View::e($theme['accent_soft'] ?? '#eff4ff') ?>;">
  <div class="id-card__band">
    <?php if ($icSchoolLogo !== ''): ?>
      <img class="id-card__logo" src="<?= $base ?>/<?= View::e($icSchoolLogo) ?>" alt="">
    <?php else: ?>
      <span class="id-card__logo id-card__logo--ph"><i class="bi bi-mortarboard-fill"></i></span>
    <?php endif; ?>
    <span class="id-card__school"><?= View::e(mb_strtoupper($icSchoolName, 'UTF-8')) ?></span>
    <span class="id-card__doctype">STUDENT ID</span>
  </div>
  <div class="id-card__body">
    <div class="id-card__photo">
      <?php if ($icPhoto !== ''): ?>
        <img src="<?= $base ?>/<?= View::e($icPhoto) ?>" alt="">
      <?php else: ?>
        <span class="id-card__initials"><?= View::e($icInitials) ?></span>
      <?php endif; ?>
    </div>
    <div class="id-card__info">
      <div class="id-card__name"><?= View::e(mb_strtoupper(trim($icFirst . ' ' . $icLast), 'UTF-8')) ?></div>
      <div class="id-card__row"><span class="id-card__lbl">Adm No.</span><span class="id-card__val"><?= View::e((string) ($student['admission_no'] ?? '')) ?></span></div>
      <div class="id-card__row"><span class="id-card__lbl">Class</span><span class="id-card__val"><?= View::e($icClass ?: '—') ?></span></div>
      <div class="id-card__row"><span class="id-card__lbl">Section</span><span class="id-card__val"><?= View::e($icSectionL) ?></span></div>
    </div>
  </div>
  <div class="id-card__foot">Property of <?= View::e($icSchoolName) ?> &middot; if found, please return</div>
</div>
