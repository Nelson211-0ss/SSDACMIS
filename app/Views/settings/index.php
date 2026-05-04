<?php
use App\Core\View;
use App\Core\App;
use App\Core\Settings;
$layout = 'app';
$title  = 'Settings';

$current     = $settings ?? [];
$themeKey    = $current['theme_accent'] ?? 'blue';
$schoolName  = $current['school_name'] ?? '';
$schoolMotto = $current['school_motto'] ?? '';
$logoPath    = $current['school_logo'] ?? '';
$schoolPhone   = $current['school_phone']   ?? '';
$schoolEmail   = $current['school_email']   ?? '';
$schoolAddress = $current['school_address'] ?? '';
$htName  = $current['school_headteacher_name']  ?? '';
$htTitle = $current['school_headteacher_title'] ?? 'Head Teacher';
$htSignaturePath = $current['school_headteacher_signature'] ?? '';
$displayName = $schoolName !== '' ? $schoolName : App::config('app.name');
$defaultName = (string) App::config('app.name');

// Inline-safe palette map for the JS live preview.
$themesJson = json_encode(
    $themes,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
);
?>

<div class="page-header">
  <div>
    <h2>Customization</h2>
    <p class="page-header__sub mb-0">School identity and color theme. Changes update the preview below instantly.</p>
  </div>
</div>

<form method="post" action="<?= $base ?>/settings" enctype="multipart/form-data" novalidate
      data-themes='<?= $themesJson ?>'
      data-default-name="<?= View::e($defaultName) ?>"
      id="settingsForm">
  <input type="hidden" name="_csrf" value="<?= $csrf ?>">

  <div class="row g-3">

    <!-- ===================== Identity ===================== -->
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center">
          <span class="card-header-icon card-header-icon--blue me-2" aria-hidden="true"><i class="bi bi-building"></i></span>
          <strong class="mb-0">School identity</strong>
        </div>
        <div class="card-body">

          <div class="mb-3">
            <label class="form-label" for="school_name">School name</label>
            <input id="school_name"
                   name="school_name"
                   class="form-control"
                   maxlength="120"
                   placeholder="<?= View::e(App::config('app.name')) ?>"
                   value="<?= View::e($schoolName) ?>">
            <div class="small text-muted mt-1">
              Shown in the sidebar, login page and browser tab. Leave blank to use the default.
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="school_motto">School motto / tag-line</label>
            <input id="school_motto"
                   name="school_motto"
                   class="form-control"
                   maxlength="160"
                   placeholder="e.g. Knowledge is Power"
                   value="<?= View::e($schoolMotto) ?>">
            <div class="small text-muted mt-1">
              Appears on the login page and at the top of every printable report card.
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="school_logo">Logo</label>

            <div class="logo-preview mb-2">
              <?php if ($logoPath): ?>
                <img src="<?= $base ?>/<?= View::e($logoPath) ?>?v=<?= time() ?>"
                     alt="Current school logo">
              <?php else: ?>
                <span class="logo-preview__placeholder">
                  <i class="bi bi-image"></i>
                  <span>No logo uploaded</span>
                </span>
              <?php endif; ?>
            </div>

            <input id="school_logo"
                   type="file"
                   name="school_logo"
                   class="form-control"
                   accept="image/png,image/jpeg,image/webp,image/gif">
            <div class="small text-muted mt-1">
              PNG, JPG, WebP or GIF. Max 1.5&nbsp;MB. Square images look best.
            </div>

            <?php if ($logoPath): ?>
              <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="remove_logo">
                <label class="form-check-label small" for="remove_logo">
                  Remove the current logo
                </label>
              </div>
            <?php endif; ?>
          </div>

        </div>
      </div>
    </div>

    <!-- ===================== Contact & headship ===================== -->
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center">
          <span class="card-header-icon card-header-icon--green me-2" aria-hidden="true"><i class="bi bi-telephone"></i></span>
          <strong class="mb-0">Contact &amp; headship</strong>
        </div>
        <div class="card-body">

          <p class="text-muted small mb-3">
            These details appear on the letterhead of every official document
            the system prints — admission letters, examination permits and
            fee receipts. Leave any field blank to omit it from the header.
          </p>

          <div class="row g-2 mb-2">
            <div class="col-md-6">
              <label class="form-label small fw-semibold mb-1" for="school_phone">Telephone</label>
              <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                <input id="school_phone" name="school_phone" class="form-control"
                       maxlength="60" placeholder="+211 ..."
                       value="<?= View::e($schoolPhone) ?>">
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold mb-1" for="school_email">Email</label>
              <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                <input id="school_email" name="school_email" type="email" class="form-control"
                       maxlength="120" placeholder="info@school.edu"
                       value="<?= View::e($schoolEmail) ?>">
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold mb-1" for="school_address">Postal / physical address</label>
            <textarea id="school_address" name="school_address" class="form-control form-control-sm"
                      rows="2" maxlength="250"
                      placeholder="P.O. Box 0000, Town · Country"><?= View::e($schoolAddress) ?></textarea>
          </div>

          <hr class="my-3">

          <p class="text-muted small mb-2">
            Used to sign admission letters and to validate examination permits.
          </p>

          <div class="row g-2">
            <div class="col-md-7">
              <label class="form-label small fw-semibold mb-1" for="school_headteacher_name">Head teacher name</label>
              <input id="school_headteacher_name" name="school_headteacher_name"
                     class="form-control form-control-sm" maxlength="120"
                     placeholder="e.g. Mr. John Doe"
                     value="<?= View::e($htName) ?>">
            </div>
            <div class="col-md-5">
              <label class="form-label small fw-semibold mb-1" for="school_headteacher_title">Title</label>
              <input id="school_headteacher_title" name="school_headteacher_title"
                     class="form-control form-control-sm" maxlength="60"
                     placeholder="Head Teacher"
                     value="<?= View::e($htTitle) ?>">
            </div>
          </div>

          <div class="mt-3">
            <label class="form-label small fw-semibold mb-1" for="school_headteacher_signature">
              Scanned signature <span class="text-muted fw-normal">(optional)</span>
            </label>

            <div class="signature-preview mb-2">
              <?php if ($htSignaturePath): ?>
                <img src="<?= $base ?>/<?= View::e($htSignaturePath) ?>?v=<?= time() ?>"
                     alt="Head teacher signature">
              <?php else: ?>
                <span class="signature-preview__placeholder">
                  <i class="bi bi-pen"></i>
                  <span>No signature uploaded</span>
                </span>
              <?php endif; ?>
            </div>

            <input id="school_headteacher_signature"
                   type="file"
                   name="school_headteacher_signature"
                   class="form-control form-control-sm"
                   accept="image/png,image/jpeg,image/webp,image/gif">
            <div class="small text-muted mt-1">
              PNG with a transparent background works best. Max 1.5&nbsp;MB.
              The image appears above the head teacher's signature line on
              admission letters, exam permits and report cards.
            </div>

            <?php if ($htSignaturePath): ?>
              <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="remove_signature" value="1" id="remove_signature">
                <label class="form-check-label small" for="remove_signature">
                  Remove the current signature
                </label>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- ===================== Theme ===================== -->
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center">
          <span class="card-header-icon card-header-icon--purple me-2" aria-hidden="true"><i class="bi bi-palette"></i></span>
          <strong class="mb-0">Color theme</strong>
        </div>
        <div class="card-body">

          <p class="text-muted small mb-3">
            Pick an accent color. The sidebar tint, buttons, links and active
            navigation will all match.
          </p>

          <div class="theme-picker">
            <?php foreach ($themes as $key => $t): ?>
              <label class="theme-swatch <?= $key === $themeKey ? 'is-selected' : '' ?>">
                <input type="radio"
                       name="theme_accent"
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

  </div>

  <!-- ===================== Live preview ===================== -->
  <div class="card mt-3">
    <div class="card-header d-flex align-items-center">
      <span class="card-header-icon card-header-icon--teal me-2" aria-hidden="true"><i class="bi bi-eye"></i></span>
      <strong class="mb-0">Live preview</strong>
      <span class="ms-auto small text-muted">Updates as you type. Save to apply.</span>
    </div>
    <div class="card-body">
      <div class="settings-preview" id="settingsPreview">
        <div class="settings-preview__sidebar">
          <div class="settings-preview__brand">
            <span class="settings-preview__brand-icon" id="previewBrandIcon"
                  <?= $logoPath ? 'hidden' : '' ?>>
              <i class="bi bi-mortarboard-fill"></i>
            </span>
            <img id="previewBrandImg"
                 src="<?= $logoPath ? $base . '/' . View::e($logoPath) . '?v=' . time() : '' ?>"
                 alt=""
                 <?= $logoPath ? '' : 'hidden' ?>>
            <span class="settings-preview__brand-text">
              <span class="d-block" id="previewName"><?= View::e($displayName) ?></span>
              <small class="d-block text-white-50 fst-italic" id="previewMotto"
                     style="font-size:.7rem; font-weight:400;"
                     <?= $schoolMotto === '' ? 'hidden' : '' ?>>
                <?= View::e($schoolMotto) ?>
              </small>
            </span>
          </div>
          <div class="settings-preview__nav">
            <span class="settings-preview__link is-active">
              <i class="bi bi-speedometer2"></i> Dashboard
            </span>
            <span class="settings-preview__link">
              <i class="bi bi-people"></i> Students
            </span>
            <span class="settings-preview__link">
              <i class="bi bi-calendar-check"></i> Attendance
            </span>
            <span class="settings-preview__link">
              <i class="bi bi-file-earmark-bar-graph"></i> Reports
            </span>
          </div>
        </div>
        <div class="settings-preview__main">
          <div class="settings-preview__topbar">
            <span class="settings-preview__crumb"><i class="bi bi-house"></i> <span id="previewName2"><?= View::e($displayName) ?></span></span>
            <span class="settings-preview__chip"><i class="bi bi-bell"></i></span>
          </div>
          <div class="settings-preview__content">
            <div class="settings-preview__card">
              <div class="settings-preview__card-title">Sample card</div>
              <p class="settings-preview__card-body">
                Buttons, links and active navigation match the chosen accent.
                <a href="#" onclick="return false;">Sample link</a>.
              </p>
              <div class="settings-preview__buttons">
                <button type="button" class="btn btn-primary btn-sm">Primary</button>
                <button type="button" class="btn btn-outline-primary btn-sm">Outline</button>
                <span class="badge bg-primary">Active</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="d-flex justify-content-end mt-3 gap-2">
    <a href="<?= $base ?>/dashboard" class="btn btn-outline-secondary">Cancel</a>
    <button type="submit" class="btn btn-primary">Save changes</button>
  </div>
</form>

<style>
.logo-preview {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 110px;
  border: 1px dashed var(--border-strong);
  border-radius: var(--radius);
  background: var(--surface-2);
  overflow: hidden;
}
.logo-preview img { max-height: 90px; max-width: 100%; object-fit: contain; }
.logo-preview__placeholder {
  display: flex; flex-direction: column; align-items: center; gap: .35rem;
  color: var(--text-subtle); font-size: .85rem;
}
.logo-preview__placeholder i { font-size: 1.6rem; }

.signature-preview {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 90px;
  border: 1px dashed var(--border-strong);
  border-radius: var(--radius);
  background: #fff;
  overflow: hidden;
}
.signature-preview img { max-height: 76px; max-width: 100%; object-fit: contain; }
.signature-preview__placeholder {
  display: flex; flex-direction: column; align-items: center; gap: .35rem;
  color: var(--text-subtle); font-size: .85rem;
}
.signature-preview__placeholder i { font-size: 1.5rem; }

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

/* ---------- Live preview shell ---------- */
.settings-preview {
  display: grid;
  grid-template-columns: 220px 1fr;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
  min-height: 240px;
  /* Local override channels: JS sets these only on this block, so the
     surrounding admin chrome is unaffected until the user saves. */
  --p-accent:        var(--accent);
  --p-accent-hover:  var(--accent-hover);
  --p-accent-soft:   var(--accent-soft);
  --p-accent-rgb:    var(--accent-rgb);
  --p-sidebar-bg:    var(--sidebar-bg);
}
.settings-preview__sidebar {
  background: var(--p-sidebar-bg);
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: .75rem;
}
.settings-preview__brand {
  display: flex; align-items: center; gap: .55rem;
  font-weight: 600;
  color: #fff;
  font-size: .875rem;
  padding-bottom: .75rem;
  border-bottom: 1px solid rgba(255,255,255,.12);
}
.settings-preview__brand-icon i { color: #fff; font-size: 1.15rem; }
.settings-preview__brand img {
  height: 28px; width: 28px; object-fit: contain;
  background: rgba(255,255,255,.95); padding: 2px; border-radius: 6px;
  display: block;
}
.settings-preview__nav { display: flex; flex-direction: column; gap: 2px; }
.settings-preview__link {
  display: flex; align-items: center; gap: .55rem;
  padding: .4rem .65rem;
  border-radius: var(--radius-sm);
  font-size: .8rem;
  color: rgba(255,255,255,.78);
}
.settings-preview__link i { color: rgba(255,255,255,.65); font-size: .9rem; }
.settings-preview__link.is-active {
  background: #fff;
  color: var(--p-sidebar-bg);
  font-weight: 600;
}
.settings-preview__link.is-active i { color: var(--p-sidebar-bg); }

.settings-preview__main {
  background: var(--surface-2, #f8fafc);
  display: flex; flex-direction: column;
}
.settings-preview__topbar {
  display: flex; align-items: center; justify-content: space-between;
  padding: .65rem 1rem;
  background: #fff;
  border-bottom: 1px solid var(--border);
  font-size: .8rem;
  color: var(--text-subtle);
}
.settings-preview__crumb i { color: var(--p-accent); margin-right: .25rem; }
.settings-preview__chip {
  width: 28px; height: 28px;
  display: inline-flex; align-items: center; justify-content: center;
  border-radius: 50%;
  background: var(--p-accent-soft);
  color: var(--p-accent);
}
.settings-preview__content { padding: 1rem; }
.settings-preview__card {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: .9rem 1rem;
}
.settings-preview__card-title {
  font-weight: 600; margin-bottom: .35rem; color: var(--text);
}
.settings-preview__card-body {
  font-size: .8rem; color: var(--text-subtle); margin-bottom: .65rem;
}
.settings-preview__card-body a { color: var(--p-accent); }
.settings-preview__card-body a:hover { color: var(--p-accent-hover); }
.settings-preview__buttons { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
.settings-preview .btn-primary {
  background: var(--p-accent); border-color: var(--p-accent);
}
.settings-preview .btn-primary:hover {
  background: var(--p-accent-hover); border-color: var(--p-accent-hover);
}
.settings-preview .btn-outline-primary {
  color: var(--p-accent); border-color: var(--p-accent);
  background: transparent;
}
.settings-preview .btn-outline-primary:hover {
  background: var(--p-accent); color: #fff;
}
.settings-preview .badge.bg-primary {
  background: var(--p-accent) !important;
}

@media (max-width: 575.98px) {
  .settings-preview { grid-template-columns: 1fr; }
}
</style>

<script>
(function () {
  var form = document.getElementById('settingsForm');
  if (!form) return;

  var themes;
  try {
    themes = JSON.parse(form.getAttribute('data-themes') || '{}');
  } catch (e) {
    themes = {};
  }
  var defaultName = form.getAttribute('data-default-name') || '';

  var preview     = document.getElementById('settingsPreview');
  var nameEls     = [document.getElementById('previewName'), document.getElementById('previewName2')];
  var mottoEl     = document.getElementById('previewMotto');
  var brandIconEl = document.getElementById('previewBrandIcon');
  var brandImgEl  = document.getElementById('previewBrandImg');

  var nameInput   = document.getElementById('school_name');
  var mottoInput  = document.getElementById('school_motto');
  var logoInput   = document.getElementById('school_logo');
  var removeInput = document.getElementById('remove_logo');
  var swatches    = document.querySelectorAll('.theme-swatch');

  // Track the original logo src so unchecking "remove" restores it.
  var originalLogoSrc = brandImgEl ? brandImgEl.getAttribute('src') : '';
  var pendingObjectUrl = null;

  function applyTheme(key) {
    var t = themes[key];
    if (!t || !preview) return;
    preview.style.setProperty('--p-accent',       t.accent);
    preview.style.setProperty('--p-accent-hover', t.accent_hover);
    preview.style.setProperty('--p-accent-soft',  t.accent_soft);
    preview.style.setProperty('--p-accent-rgb',   t.accent_rgb);
    preview.style.setProperty('--p-sidebar-bg',   t.sidebar_bg);
  }

  function setName() {
    var v = (nameInput && nameInput.value.trim()) || defaultName;
    nameEls.forEach(function (el) { if (el) el.textContent = v; });
  }

  function setMotto() {
    if (!mottoEl) return;
    var v = mottoInput ? mottoInput.value.trim() : '';
    if (v === '') {
      mottoEl.hidden = true;
      mottoEl.textContent = '';
    } else {
      mottoEl.hidden = false;
      mottoEl.textContent = v;
    }
  }

  function showLogo(src) {
    if (!brandImgEl || !brandIconEl) return;
    if (src) {
      brandImgEl.src = src;
      brandImgEl.hidden = false;
      brandIconEl.hidden = true;
    } else {
      brandImgEl.removeAttribute('src');
      brandImgEl.hidden = true;
      brandIconEl.hidden = false;
    }
  }

  function setLogoFromFile() {
    if (!logoInput || !logoInput.files || !logoInput.files[0]) return;
    if (pendingObjectUrl) {
      URL.revokeObjectURL(pendingObjectUrl);
    }
    pendingObjectUrl = URL.createObjectURL(logoInput.files[0]);
    showLogo(pendingObjectUrl);
    if (removeInput) removeInput.checked = false;
  }

  // Theme picker --------------------------------------------------
  swatches.forEach(function (sw) {
    var input = sw.querySelector('input[type="radio"]');
    if (!input) return;
    input.addEventListener('change', function () {
      swatches.forEach(function (s) { s.classList.remove('is-selected'); });
      sw.classList.add('is-selected');
      applyTheme(input.value);
    });
  });

  // Identity ------------------------------------------------------
  if (nameInput)  nameInput.addEventListener('input', setName);
  if (mottoInput) mottoInput.addEventListener('input', setMotto);

  // Logo ----------------------------------------------------------
  if (logoInput) logoInput.addEventListener('change', setLogoFromFile);
  if (removeInput) {
    removeInput.addEventListener('change', function () {
      if (removeInput.checked) {
        if (logoInput) logoInput.value = '';
        if (pendingObjectUrl) {
          URL.revokeObjectURL(pendingObjectUrl);
          pendingObjectUrl = null;
        }
        showLogo('');
      } else {
        showLogo(originalLogoSrc);
      }
    });
  }

  // Initial state --------------------------------------------------
  var initial = document.querySelector('.theme-swatch input:checked');
  if (initial) applyTheme(initial.value);
})();
</script>
