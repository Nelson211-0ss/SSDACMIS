<?php
/**
 * Reusable school letterhead block — used by admission letters, exam
 * permits and other official printable documents. Pulls everything from
 * Settings so the same header is shown wherever official paperwork is
 * produced.
 *
 * Inputs (in scope when included):
 *   $base (string)  app base path
 *
 * Optional overrides:
 *   $lh_subtitle (string)  small caption rendered below the school name,
 *                          e.g. "OFFICE OF THE HEAD TEACHER" or
 *                          "FEES OFFICE — EXAMINATION PERMIT".
 *
 * Output is fully self-contained HTML — no Bootstrap/utility classes —
 * so it renders identically when the print views set their own
 * standalone CSS.
 */
use App\Core\View;
use App\Core\App;
use App\Core\Settings;

$lh_school   = Settings::get('school_name')  ?: (string) App::config('app.name');
$lh_motto    = (string) (Settings::get('school_motto') ?? '');
$lh_logo     = Settings::logoUrl();
$lh_phone    = (string) (Settings::get('school_phone')   ?? '');
$lh_email    = (string) (Settings::get('school_email')   ?? '');
$lh_address  = (string) (Settings::get('school_address') ?? '');
$lh_subtitle = isset($lh_subtitle) ? (string) $lh_subtitle : '';

// Compose a single-line meta strip with whichever contact pieces were
// provided. Each piece is dot-separated so a school that only fills in a
// phone number gets a clean header (no dangling separators).
$lh_meta = array_values(array_filter([
    $lh_address !== '' ? str_replace(["\r\n", "\r"], "\n", $lh_address) : null,
]));
?>
<div class="letterhead">
  <?php if ($lh_logo): ?>
    <img class="letterhead__logo" src="<?= View::e(($base ?? '') . '/' . $lh_logo) ?>" alt="">
  <?php else: ?>
    <span class="letterhead__logo letterhead__logo--placeholder" aria-hidden="true">★</span>
  <?php endif; ?>

  <div class="letterhead__text">
    <div class="letterhead__name"><?= View::e(strtoupper($lh_school)) ?></div>
    <?php if ($lh_motto !== ''): ?>
      <div class="letterhead__motto"><em><?= View::e($lh_motto) ?></em></div>
    <?php endif; ?>

    <?php if ($lh_address !== ''): ?>
      <div class="letterhead__addr"><?= nl2br(View::e($lh_address)) ?></div>
    <?php endif; ?>

    <?php if ($lh_phone !== '' || $lh_email !== ''): ?>
      <div class="letterhead__contact">
        <?php if ($lh_phone !== ''): ?>
          <span><strong>Tel:</strong> <?= View::e($lh_phone) ?></span>
        <?php endif; ?>
        <?php if ($lh_phone !== '' && $lh_email !== ''): ?>
          <span class="letterhead__sep">·</span>
        <?php endif; ?>
        <?php if ($lh_email !== ''): ?>
          <span><strong>Email:</strong> <?= View::e($lh_email) ?></span>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($lh_subtitle !== ''): ?>
      <div class="letterhead__subtitle"><?= View::e($lh_subtitle) ?></div>
    <?php endif; ?>
  </div>
</div>

<style>
  .letterhead {
    display: flex;
    align-items: center;
    gap: 22px;
    border-bottom: 3px double #1f2937;
    padding-bottom: 14px;
    margin-bottom: 22px;
  }
  .letterhead__logo {
    flex-shrink: 0;
    width: 92px;
    height: 92px;
    object-fit: contain;
    border-radius: 6px;
    background: #fff;
  }
  .letterhead__logo--placeholder {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #eef2ff;
    color: #1e3a8a;
    font-size: 2.6rem;
    font-weight: 700;
    border-radius: 50%;
  }
  .letterhead__text { flex: 1; line-height: 1.3; text-align: center; }
  .letterhead__name {
    font-size: 1.6rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    color: #1e3a8a;
    margin-bottom: 2px;
  }
  .letterhead__motto {
    color: #4b5563;
    font-size: 0.95rem;
    margin-bottom: 4px;
  }
  .letterhead__addr {
    color: #374151;
    font-size: 0.85rem;
    margin-bottom: 2px;
  }
  .letterhead__contact {
    color: #374151;
    font-size: 0.85rem;
  }
  .letterhead__sep { color: #94a3b8; padding: 0 4px; }
  .letterhead__subtitle {
    margin-top: 8px;
    font-size: 0.85rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    color: #1f2937;
    border-top: 1px solid #e5e7eb;
    padding-top: 6px;
    text-transform: uppercase;
  }
</style>
