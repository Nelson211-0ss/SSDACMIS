<?php
use App\Core\View;
use App\Core\Auth;

/**
 * Per-school ID card color theme picker. Editable by the super admin (any
 * school) and by that school's own school_admin (ownership is enforced in
 * IdCardController, not here).
 *
 * The preview panel below carries its own #idCardPreview-prefixed copy of the
 * .id-card* CSS used by students/id_card.php and students/id_cards_bulk.php —
 * keep the three copies in sync so the preview matches what actually prints.
 *
 * @var array $school  id, name, logo, id_card_theme
 * @var array $themes  Settings::THEMES catalogue
 */
$layout = 'app';
$title  = 'ID Card Theme';

$schoolId  = (int) $school['id'];
$themeKey  = trim((string) ($school['id_card_theme'] ?? '')) ?: 'blue';
$theme     = $themes[$themeKey] ?? $themes['blue'];
$backHref  = Auth::role() === 'admin' ? ($base . '/schools/' . $schoolId) : ($base . '/dashboard');

// Sample data for the live preview — never saved, just illustrates the card.
$student = [
    'first_name'    => 'Jane',
    'last_name'     => 'Doe',
    'admission_no'  => 'F1A/001/2026',
    'class_name'    => 'Sample Class',
    'level'         => '',
    'section'       => 'day',
    'photo_path'    => '',
];

$themesJson = json_encode(
    $themes,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
);
?>

<div class="page-header">
  <div>
    <h2>ID Card Theme</h2>
    <p class="page-header__sub mb-0"><?= View::e($school['name']) ?> &middot; pick the colors printed on this school's student ID cards.</p>
  </div>
  <a class="btn btn-outline-secondary" href="<?= View::e($backHref) ?>">
    <i class="bi bi-arrow-left"></i> Back
  </a>
</div>

<form method="post" action="<?= $base ?>/schools/<?= $schoolId ?>/id-card-theme"
      data-themes='<?= $themesJson ?>' id="idCardThemeForm">
  <input type="hidden" name="_csrf" value="<?= $csrf ?>">

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center">
          <span class="card-header-icon card-header-icon--purple me-2" aria-hidden="true"><i class="bi bi-palette"></i></span>
          <strong class="mb-0">Color theme</strong>
        </div>
        <div class="card-body">
          <p class="text-muted small mb-3">
            This is a separate choice from the dashboard's own color theme —
            each school picks its own ID card colors independently of any
            other school on this system.
          </p>
          <div class="theme-picker">
            <?php foreach ($themes as $key => $t): ?>
              <label class="theme-swatch <?= $key === $themeKey ? 'is-selected' : '' ?>">
                <input type="radio"
                       name="id_card_theme"
                       value="<?= View::e($key) ?>"
                       <?= $key === $themeKey ? 'checked' : '' ?>>
                <span class="theme-swatch__chip" style="background: <?= View::e($t['accent']) ?>"></span>
                <span class="theme-swatch__label"><?= View::e($t['label']) ?></span>
                <span class="theme-swatch__check"><i class="bi bi-check-lg"></i></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center">
          <span class="card-header-icon card-header-icon--teal me-2" aria-hidden="true"><i class="bi bi-eye"></i></span>
          <strong class="mb-0">Live preview</strong>
        </div>
        <div class="card-body d-flex flex-column">
          <div class="id-card-stage">
            <div id="idCardPreview">
              <?php include dirname(__DIR__) . '/students/_id_card_face.php'; ?>
            </div>
          </div>
          <p class="id-card-stage__note mb-0">
            Actual print size &mdash; 85.6 &times; 54 mm. Sample data shown;
            the card updates as you pick a color.
          </p>
        </div>
      </div>
    </div>
  </div>

  <div class="d-flex justify-content-end mt-3 gap-2">
    <a href="<?= View::e($backHref) ?>" class="btn btn-outline-secondary">Cancel</a>
    <button type="submit" class="btn btn-primary">Save changes</button>
  </div>
</form>

<style>
@import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&display=swap');

.theme-picker {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: .5rem;
}
.theme-swatch {
  position: relative;
  display: flex;
  align-items: center;
  gap: .65rem;
  padding: .65rem .8rem;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  cursor: pointer;
  background: var(--surface);
  transition: border-color .12s ease, background .12s ease;
}
.theme-swatch:hover { background: var(--hover); }
.theme-swatch input { position: absolute; opacity: 0; pointer-events: none; }
.theme-swatch__chip {
  width: 20px; height: 20px; border-radius: 6px; flex-shrink: 0;
  box-shadow: inset 0 0 0 1px rgba(0,0,0,.06);
}
.theme-swatch__label { font-size: .85rem; font-weight: 500; }
.theme-swatch__check {
  margin-left: auto;
  opacity: 0;
  color: var(--accent);
  transition: opacity .12s ease;
}
.theme-swatch.is-selected {
  border-color: var(--accent);
  background: var(--accent-soft);
}
.theme-swatch.is-selected .theme-swatch__check { opacity: 1; }

/* Neutral stage so the card's own white face reads correctly. */
.id-card-stage {
  display: flex;
  justify-content: center;
  padding: 1.5rem 1rem;
  background: #e4e9ef;
  border: 1px solid #d7dee7;
  border-radius: 12px;
  overflow-x: auto;
}
.id-card-stage .id-card { box-shadow: 0 10px 26px rgba(15, 23, 42, .16); }
.id-card-stage__note {
  margin-top: .75rem;
  font-size: .78rem;
  line-height: 1.5;
  color: var(--muted, #6b7280);
  text-align: center;
}

/* ===== ID card face =========================================================
   ID-1 / credit-card proportions (85.6 x 54mm). Restrained institutional
   design language shared with the exam permit and admission letter: one
   accent rule, hairline dividers, uppercase micro-labels above values,
   a dominant name, and a faint diagonal security texture in the school's
   own colour.

   All colour is driven by the three custom properties set inline on .id-card
   by students/_id_card_face.php (--ic-accent, --ic-accent-hover,
   --ic-accent-soft), so every palette in Settings::THEMES works — and the
   theme picker's live preview stays accurate, since those are exactly the
   properties its applyTheme() swaps. Rule of thumb kept below: accent fills
   and hairlines use --ic-accent, accent TEXT uses the darker
   --ic-accent-hover (readable even for amber/lime/orange), and white text is
   never placed on an accent. Tints come from element opacity rather than
   rgba(), so no separate RGB channel variable is needed.

   This block is duplicated verbatim in the three pages that render a card
   face — keep them in sync:
     app/Views/students/id_card.php
     app/Views/students/id_cards_bulk.php
     app/Views/schools/id_card_theme.php   (#idCardPreview-prefixed copy)
   ========================================================================= */
#idCardPreview .id-card {
  --ic-accent: #2563eb;
  --ic-accent-hover: #1d4ed8;
  --ic-accent-soft: #eff4ff;
  --ic-ink: #0f172a;
  --ic-muted: #6b7280;
  --ic-soft: #9ca3af;
  --ic-hair: #e5e7eb;
  position: relative;
  width: 85.6mm;
  height: 54mm;
  border-radius: 2.4mm;
  overflow: hidden;
  background: #fff;
  border: 0.2mm solid var(--ic-hair);
  color: var(--ic-ink);
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
  font-feature-settings: "kern", "liga", "tnum";
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}
#idCardPreview .id-card__rule {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 1.2mm;
  background: var(--ic-accent);
}
#idCardPreview .id-card__guard {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: repeating-linear-gradient(
    135deg,
    var(--ic-accent) 0,
    var(--ic-accent) 0.28mm,
    transparent 0.28mm,
    transparent 2.2mm
  );
  opacity: .06;
  pointer-events: none;
}
#idCardPreview .id-card__inner {
  position: relative;
  z-index: 1;
  height: 100%;
  padding-top: 1.2mm;
  display: flex;
  flex-direction: column;
}
#idCardPreview .id-card__head {
  display: flex;
  align-items: center;
  gap: 2.2mm;
  padding: 2.2mm 3.2mm 1.9mm;
  border-bottom: 0.2mm solid var(--ic-hair);
}
#idCardPreview .id-card__logo {
  width: 7mm;
  height: 7mm;
  object-fit: contain;
  flex-shrink: 0;
}
#idCardPreview .id-card__logo--ph {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 0.2mm solid var(--ic-hair);
  border-radius: 1mm;
  background: #fff;
  color: var(--ic-accent-hover);
  font-size: 3.6mm;
  line-height: 1;
}
#idCardPreview .id-card__brand {
  flex: 1 1 auto;
  min-width: 0;
}
#idCardPreview .id-card__school {
  font-size: 2.5mm;
  font-weight: 700;
  letter-spacing: 0.04em;
  line-height: 1.16;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
#idCardPreview .id-card__doctype {
  display: block;
  margin-top: 0.5mm;
  font-size: 1.7mm;
  font-weight: 600;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--ic-accent-hover);
  line-height: 1.3;
}
#idCardPreview .id-card__mark {
  flex-shrink: 0;
  font-size: 4.8mm;
  line-height: 1;
  color: var(--ic-accent);
}
#idCardPreview .id-card__body {
  flex: 1 1 auto;
  min-height: 0;
  display: flex;
  align-items: stretch;
  gap: 3.2mm;
  padding: 2.6mm 3.2mm 2.4mm;
}
#idCardPreview .id-card__photo {
  width: 22.5mm;
  min-height: 24mm;
  align-self: stretch;
  flex-shrink: 0;
  border-radius: 1.2mm;
  overflow: hidden;
  background: var(--ic-accent-soft);
  border: 0.25mm solid var(--ic-accent);
  display: flex;
  align-items: center;
  justify-content: center;
}
#idCardPreview .id-card__photo img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}
#idCardPreview .id-card__initials {
  font-size: 7mm;
  font-weight: 700;
  letter-spacing: 0.02em;
  line-height: 1;
  color: var(--ic-accent-hover);
}
#idCardPreview .id-card__info {
  flex: 1 1 auto;
  min-width: 0;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}
#idCardPreview .id-card__name {
  font-size: 4mm;
  font-weight: 700;
  letter-spacing: 0.005em;
  line-height: 1.12;
  text-transform: uppercase;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
#idCardPreview .id-card__field {
  margin-top: 1.2mm;
}
#idCardPreview .id-card__lbl {
  display: block;
  font-size: 1.7mm;
  font-weight: 600;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--ic-soft);
  line-height: 1.4;
}
#idCardPreview .id-card__adm {
  display: block;
  font-family: 'JetBrains Mono', 'SFMono-Regular', Menlo, Consolas, monospace;
  font-size: 2.9mm;
  font-weight: 600;
  letter-spacing: 0.01em;
  line-height: 1.3;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
#idCardPreview .id-card__grid {
  margin-top: 1.2mm;
  padding-top: 1.7mm;
  border-top: 0.2mm solid var(--ic-hair);
  display: grid;
  grid-template-columns: 1fr 1fr;
  column-gap: 2.4mm;
}
#idCardPreview .id-card__cell {
  min-width: 0;
}
#idCardPreview .id-card__val {
  display: block;
  font-size: 2.5mm;
  font-weight: 600;
  line-height: 1.3;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
#idCardPreview .id-card__foot {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 2mm;
  padding: 1.4mm 3.2mm;
  background: var(--ic-accent-soft);
  border-top: 0.2mm solid var(--ic-hair);
  font-size: 1.7mm;
  letter-spacing: 0.04em;
  color: var(--ic-muted);
}
#idCardPreview .id-card__foot-own {
  min-width: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
#idCardPreview .id-card__foot-note {
  flex-shrink: 0;
  color: var(--ic-soft);
  text-transform: uppercase;
  letter-spacing: 0.12em;
  font-size: 1.5mm;
  font-weight: 600;
}
/* ===== end ID card face ================================================== */
</style>

<script>
(function () {
  var form = document.getElementById('idCardThemeForm');
  if (!form) return;

  var themes;
  try {
    themes = JSON.parse(form.getAttribute('data-themes') || '{}');
  } catch (e) {
    themes = {};
  }

  var card = document.querySelector('#idCardPreview .id-card');
  var swatches = document.querySelectorAll('.theme-swatch');

  function applyTheme(key) {
    var t = themes[key];
    if (!t || !card) return;
    card.style.setProperty('--ic-accent', t.accent);
    card.style.setProperty('--ic-accent-hover', t.accent_hover);
    card.style.setProperty('--ic-accent-soft', t.accent_soft);
  }

  swatches.forEach(function (sw) {
    var input = sw.querySelector('input[type="radio"]');
    if (!input) return;
    input.addEventListener('change', function () {
      swatches.forEach(function (s) { s.classList.remove('is-selected'); });
      sw.classList.add('is-selected');
      applyTheme(input.value);
    });
  });
})();
</script>
