<?php
/**
 * One ID card face. Included (not rendered via View::render) so it shares
 * the including page's variable scope and its <style> block — this partial
 * emits markup only, never its own <style>, so it can be looped many times
 * on the bulk sheet without duplicating CSS per card.
 *
 * Design: ID-1 proportions, one accent rule at the top edge, hairline
 * dividers, uppercase micro-labels above their values, the student name as
 * the dominant element, and a faint diagonal security texture drawn in the
 * school's own accent colour. All colour comes from the three custom
 * properties set inline below, so every Settings::THEMES palette works and
 * the theme picker's live preview stays accurate.
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
  <span class="id-card__rule" aria-hidden="true"></span>
  <span class="id-card__guard" aria-hidden="true"></span>

  <div class="id-card__inner">
    <div class="id-card__head">
      <?php if ($icSchoolLogo !== ''): ?>
        <img class="id-card__logo" src="<?= $base ?>/<?= View::e($icSchoolLogo) ?>" alt="">
      <?php else: ?>
        <span class="id-card__logo id-card__logo--ph" aria-hidden="true"><i class="bi bi-mortarboard-fill"></i></span>
      <?php endif; ?>
      <div class="id-card__brand">
        <div class="id-card__school"><?= View::e(mb_strtoupper($icSchoolName, 'UTF-8')) ?></div>
        <span class="id-card__doctype">Student Identity Card</span>
      </div>
      <i class="bi bi-person-vcard id-card__mark" aria-hidden="true"></i>
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

        <div class="id-card__field">
          <span class="id-card__lbl">Admission No.</span>
          <span class="id-card__adm"><?= View::e(trim((string) ($student['admission_no'] ?? '')) ?: '—') ?></span>
        </div>

        <div class="id-card__grid">
          <div class="id-card__cell">
            <span class="id-card__lbl">Class</span>
            <span class="id-card__val"><?= View::e($icClass ?: '—') ?></span>
          </div>
          <div class="id-card__cell">
            <span class="id-card__lbl">Section</span>
            <span class="id-card__val"><?= View::e($icSectionL) ?></span>
          </div>
        </div>
      </div>
    </div>

    <div class="id-card__foot">
      <span class="id-card__foot-own">Property of <?= View::e($icSchoolName) ?></span>
      <span class="id-card__foot-note">If found, please return</span>
    </div>
  </div>
</div>
