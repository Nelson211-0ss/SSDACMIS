<?php
use App\Core\View;
use App\Core\Auth;

/**
 * Per-school ID card color theme picker. Editable by the super admin (any
 * school) and by that school's own school_admin (ownership is enforced in
 * IdCardController, not here).
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
        <div class="card-body d-flex flex-column align-items-center justify-content-center">
          <div id="idCardPreview">
            <?php include dirname(__DIR__) . '/students/_id_card_face.php'; ?>
          </div>
          <p class="text-muted small mt-3 mb-0 text-center">Sample data shown &mdash; updates as you pick a color.</p>
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

/* ----- ID card face preview (same proportions as the printable cards) ----- */
#idCardPreview .id-card {
  width: 85.6mm;
  height: 54mm;
  border-radius: 3mm;
  overflow: hidden;
  background: #fff;
  border: 1px solid #e2e8f0;
  box-shadow: 0 8px 20px rgba(15, 23, 42, .14);
  display: flex;
  flex-direction: column;
}
#idCardPreview .id-card__band {
  background: var(--ic-accent-hover);
  color: #fff;
  display: flex;
  align-items: center;
  gap: 2mm;
  padding: 2mm 3mm;
}
#idCardPreview .id-card__logo {
  width: 7mm; height: 7mm;
  object-fit: contain;
  background: #fff;
  border-radius: 1mm;
  padding: 0.5mm;
  flex-shrink: 0;
}
#idCardPreview .id-card__logo--ph {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: var(--ic-accent-hover);
  font-size: 4mm;
}
#idCardPreview .id-card__school {
  font-size: 2.6mm;
  font-weight: 700;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  line-height: 1.15;
  flex: 1 1 auto;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
#idCardPreview .id-card__doctype {
  font-size: 1.9mm;
  font-weight: 600;
  letter-spacing: 0.12em;
  background: rgba(255, 255, 255, .18);
  padding: 0.6mm 1.4mm;
  border-radius: 1mm;
  white-space: nowrap;
  flex-shrink: 0;
}
#idCardPreview .id-card__body {
  flex: 1 1 auto;
  display: flex;
  gap: 2.5mm;
  padding: 2.5mm 3mm;
  background: var(--ic-accent-soft);
}
#idCardPreview .id-card__photo {
  width: 16mm; height: 20mm;
  border-radius: 1.5mm;
  overflow: hidden;
  background: #fff;
  border: 0.4mm solid var(--ic-accent);
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}
#idCardPreview .id-card__photo img { width: 100%; height: 100%; object-fit: cover; }
#idCardPreview .id-card__initials { font-size: 6mm; font-weight: 700; color: var(--ic-accent); }
#idCardPreview .id-card__info {
  flex: 1 1 auto;
  min-width: 0;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 1mm;
}
#idCardPreview .id-card__name {
  font-size: 3.4mm;
  font-weight: 700;
  color: #111827;
  line-height: 1.15;
  margin-bottom: 0.5mm;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
#idCardPreview .id-card__row { display: flex; gap: 1.5mm; font-size: 2.3mm; line-height: 1.3; }
#idCardPreview .id-card__lbl {
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  width: 13mm;
  flex-shrink: 0;
  font-weight: 600;
}
#idCardPreview .id-card__val { color: #111827; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
#idCardPreview .id-card__foot {
  background: var(--ic-accent);
  color: #fff;
  font-size: 1.7mm;
  text-align: center;
  padding: 1mm;
  letter-spacing: 0.03em;
}
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
