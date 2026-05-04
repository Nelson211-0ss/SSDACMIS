<?php
use App\Core\View;
$layout = 'app';
$editing = (bool) ($student ?? null);
$title = $editing ? 'Edit Student' : 'New Student';
$action = $editing ? ($base . '/students/' . (int) $student['id']) : ($base . '/students');
$currentClassId = (int) ($student['class_id'] ?? 0);
$selectedSection = $student['section'] ?? 'day';
$selectedStream  = $student['stream']  ?? 'none';
$currentLevel = '';
foreach ($classes as $c) {
    if ((int) $c['id'] === $currentClassId) { $currentLevel = trim((string) ($c['level'] ?? '')); break; }
}
$streamRequired = ($currentLevel === 'Form 3' || $currentLevel === 'Form 4');
$dobMinAttr = date('Y-m-d', strtotime('-100 years'));
$dobMaxAttr = date('Y-m-d');
?>
<div class="entity-form">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <?php if (!$editing): ?>
        <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis" style="font-size: 0.65rem; font-weight: 700; letter-spacing: 0.06em;">NEW</span>
      <?php else: ?>
        <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis" style="font-size: 0.65rem; font-weight: 700; letter-spacing: 0.06em;">EDIT</span>
      <?php endif; ?>
      <h2 class="h5 mb-0"><?= View::e($title) ?></h2>
      <span class="text-muted small d-none d-md-inline">— all fields below</span>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/students"><i class="bi bi-arrow-left"></i> Back</a>
  </div>

  <form id="studentEnrollmentForm" method="post" action="<?= $action ?>" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">

    <div class="card entity-form__card border-0 shadow-sm">
      <div class="card-body">
        <div class="row entity-form__split g-3 gx-xl-4 gy-3">

          <div class="col-xl-4 entity-form__divider mb-2 mb-xl-0">
            <div class="entity-form__col-title">
              <span class="card-header-icon card-header-icon--blue" style="width: 1.35rem; height: 1.35rem; font-size: 0.75rem;"><i class="bi bi-diagram-3"></i></span>
              Enrollment
            </div>

            <div class="mb-2">
              <label class="form-label small fw-semibold mb-1" for="classSelect">Class <span class="text-danger">*</span></label>
              <select name="class_id" id="classSelect" class="form-select form-select-sm shadow-sm" required>
                <option value="">Choose…</option>
                <?php foreach ($classes as $c): ?>
                  <option value="<?= (int)$c['id'] ?>"
                          data-prefix="<?= View::e($c['admission_prefix'] ?? '') ?>"
                          data-level="<?= View::e($c['level'] ?? '') ?>"
                          <?= $currentClassId === (int)$c['id'] ? 'selected' : '' ?>>
                    <?= View::e(mb_strtoupper((string) ($c['name'] ?? ''), 'UTF-8')) ?><?php if (!empty($c['admission_prefix'])): ?> · <?= View::e(mb_strtoupper((string) ($c['admission_prefix'] ?? ''), 'UTF-8')) ?><?php endif; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-2">
              <span class="form-label small fw-semibold mb-1 d-block">Section <span class="text-danger">*</span></span>
              <div class="btn-group w-100" role="group" aria-label="Day or boarding">
                <input type="radio" class="btn-check" name="section" id="studentSectionDay" value="day" autocomplete="off"
                       <?= $selectedSection === 'day' ? 'checked' : '' ?> required>
                <label class="btn btn-outline-secondary btn-sm py-2 text-uppercase fw-semibold small" style="letter-spacing: .06em;" for="studentSectionDay"><i class="bi bi-sun-fill text-warning me-1"></i>Day</label>
                <input type="radio" class="btn-check" name="section" id="studentSectionBoarding" value="boarding" autocomplete="off"
                       <?= $selectedSection === 'boarding' ? 'checked' : '' ?>>
                <label class="btn btn-outline-secondary btn-sm py-2 text-uppercase fw-semibold small" style="letter-spacing: .06em;" for="studentSectionBoarding"><i class="bi bi-building text-info me-1"></i>Boarding</label>
              </div>
            </div>

            <div class="entity-form__preview mb-2">
              <div class="entity-form__preview-label"><i class="bi bi-hash"></i> Admission</div>
              <?php if ($editing): ?>
                <input class="form-control form-control-sm font-monospace fw-semibold py-1 shadow-none" value="<?= View::e($student['admission_no']) ?>" readonly>
              <?php else: ?>
                <input id="admissionPreview" class="form-control form-control-sm font-monospace text-muted py-1 shadow-none" value="Assigned on save" readonly aria-live="polite">
              <?php endif; ?>
            </div>

            <div id="streamWrap" class="<?= $streamRequired ? '' : 'd-none' ?>">
              <div class="entity-form__panel">
                <label class="form-label small fw-semibold mb-1" for="streamSelect">
                  Stream <span class="text-danger">*</span>
                  <span class="badge bg-secondary-subtle text-secondary-emphasis fw-normal" style="font-size: 0.65rem;">F3–F4</span>
                </label>
                <select name="stream" id="streamSelect" class="form-select form-select-sm shadow-sm text-uppercase fw-semibold" style="letter-spacing: .04em;" <?= $streamRequired ? 'required' : '' ?>>
                  <option value="none" <?= $selectedStream === 'none' ? 'selected' : '' ?>>N/A · FORMS 1–2</option>
                  <option value="science" <?= $selectedStream === 'science' ? 'selected' : '' ?>>SCIENCE</option>
                  <option value="arts" <?= $selectedStream === 'arts' ? 'selected' : '' ?>>ARTS</option>
                </select>
              </div>
            </div>
          </div>

          <div class="col-xl-4 entity-form__divider mb-2 mb-xl-0">
            <div class="entity-form__col-title">
              <span class="card-header-icon card-header-icon--purple" style="width: 1.35rem; height: 1.35rem; font-size: 0.75rem;"><i class="bi bi-person-fill"></i></span>
              Learner
            </div>

            <div class="row g-2 mb-2">
              <div class="col-sm-6">
                <label class="form-label small fw-semibold mb-1" for="studentFirstName">First name <span class="text-danger">*</span></label>
                <input id="studentFirstName" name="first_name" class="form-control form-control-sm js-upper shadow-sm" required
                       autocapitalize="characters" autocomplete="off" spellcheck="false"
                       placeholder="JOHN"
                       value="<?= View::e($student['first_name'] ?? '') ?>">
              </div>
              <div class="col-sm-6">
                <label class="form-label small fw-semibold mb-1" for="studentLastName">Last name <span class="text-danger">*</span></label>
                <input id="studentLastName" name="last_name" class="form-control form-control-sm js-upper shadow-sm" required
                       autocapitalize="characters" autocomplete="off" spellcheck="false"
                       placeholder="DOE"
                       value="<?= View::e($student['last_name'] ?? '') ?>">
              </div>
            </div>

            <div class="row g-2">
              <div class="col-sm-6">
                <label class="form-label small fw-semibold mb-1" for="studentGender">Gender</label>
                <select id="studentGender" name="gender" class="form-select form-select-sm shadow-sm text-uppercase fw-semibold" style="letter-spacing: .04em;">
                  <?php foreach (['male','female','other'] as $g): ?>
                    <option value="<?= $g ?>" <?= ($student['gender'] ?? '') === $g ? 'selected' : '' ?>><?= View::e(mb_strtoupper($g, 'UTF-8')) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-sm-6">
                <label class="form-label small fw-semibold mb-1" for="studentDob">Date of birth <span class="text-danger">*</span></label>
                <input id="studentDob" type="date" name="dob" class="form-control form-control-sm shadow-sm" required
                       min="<?= View::e($dobMinAttr) ?>" max="<?= View::e($dobMaxAttr) ?>"
                       value="<?= View::e($student['dob'] ?? '') ?>">
              </div>
            </div>
          </div>

          <div class="col-xl-4">
            <div class="entity-form__col-title">
              <span class="card-header-icon card-header-icon--teal" style="width: 1.35rem; height: 1.35rem; font-size: 0.75rem;"><i class="bi bi-people-fill"></i></span>
              Guardian
            </div>

            <div class="mb-2">
              <label class="form-label small fw-semibold mb-1" for="guardianName">Guardian name</label>
              <div class="input-group input-group-sm shadow-sm">
                <span class="input-group-text bg-body-secondary border-end-0 py-1"><i class="bi bi-person text-muted small"></i></span>
                <input id="guardianName" name="guardian_name" class="form-control js-upper border-start-0 ps-2 py-1"
                       autocapitalize="characters" autocomplete="off" spellcheck="false"
                       placeholder="FULL NAME"
                       value="<?= View::e($student['guardian_name'] ?? '') ?>">
              </div>
            </div>

            <div class="mb-2">
              <label class="form-label small fw-semibold mb-1" for="guardianPhone">Guardian phone</label>
              <div class="input-group input-group-sm shadow-sm">
                <span class="input-group-text bg-body-secondary border-end-0 py-1"><i class="bi bi-telephone text-muted small"></i></span>
                <input id="guardianPhone" name="guardian_phone" type="tel" class="form-control font-monospace border-start-0 ps-2 py-1" placeholder="+211 …"
                       value="<?= View::e($student['guardian_phone'] ?? '') ?>">
              </div>
            </div>

            <div class="mb-0">
              <label class="form-label small fw-semibold mb-1" for="studentAddress">Address</label>
              <textarea id="studentAddress" name="address" class="form-control form-control-sm js-upper shadow-sm" rows="2"
                        autocapitalize="characters" spellcheck="false"
                        placeholder="Street, town"><?= View::e($student['address'] ?? '') ?></textarea>
            </div>
          </div>

        </div>
      </div>

      <div class="card-footer py-2 px-3 bg-body-secondary bg-opacity-25 border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="small text-muted mb-0"><span class="text-danger">*</span> Required · Typed names &amp; address save in CAPITAL LETTERS · Gender, section &amp; stream shown in CAPS</span>
        <div class="d-flex flex-wrap gap-2 ms-auto">
          <a href="<?= $base ?>/students" class="btn btn-outline-secondary btn-sm px-3">Cancel</a>
          <button type="submit" class="btn btn-primary btn-sm px-4"><i class="bi bi-check-lg me-1"></i><?= $editing ? 'Save' : 'Save student' ?></button>
        </div>
      </div>
    </div>

    <?php
      $existingPhoto = $editing ? trim((string) ($student['photo_path'] ?? '')) : '';
      $existingPhotoUrl = $existingPhoto !== '' ? ($base . '/' . $existingPhoto) : '';
    ?>
    <div class="card entity-form__card border-0 shadow-sm mt-3" id="studentPhotoCard">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
          <div class="entity-form__col-title m-0">
            <span class="card-header-icon card-header-icon--orange" style="width: 1.35rem; height: 1.35rem; font-size: 0.75rem;"><i class="bi bi-person-badge"></i></span>
            Passport photo
            <span class="badge bg-light text-secondary border ms-1 fw-normal" style="font-size: 0.65rem;">OPTIONAL</span>
          </div>
          <div class="btn-group btn-group-sm" role="group" aria-label="Photo source">
            <input type="radio" class="btn-check" name="photo_mode" id="photoModeUpload" value="upload" autocomplete="off" checked>
            <label class="btn btn-outline-secondary" for="photoModeUpload"><i class="bi bi-upload me-1"></i>Upload</label>
            <input type="radio" class="btn-check" name="photo_mode" id="photoModeCapture" value="capture" autocomplete="off">
            <label class="btn btn-outline-secondary" for="photoModeCapture"><i class="bi bi-camera-video me-1"></i>Take photo</label>
          </div>
        </div>

        <div class="row g-3 align-items-start">
          <!-- Live preview / current photo -->
          <div class="col-md-4 col-lg-3 text-center">
            <div id="studentPhotoFrame"
                 class="rounded border bg-body-secondary bg-opacity-25 d-flex align-items-center justify-content-center mx-auto position-relative overflow-hidden"
                 style="width: 100%; max-width: 200px; aspect-ratio: 3 / 4;">
              <?php if ($existingPhotoUrl !== ''): ?>
                <img id="studentPhotoPreview" src="<?= View::e($existingPhotoUrl) ?>" alt="Passport photo"
                     style="width:100%; height:100%; object-fit:cover;">
              <?php else: ?>
                <img id="studentPhotoPreview" src="" alt="" class="d-none"
                     style="width:100%; height:100%; object-fit:cover;">
                <div id="studentPhotoPlaceholder" class="text-muted small text-center px-2">
                  <i class="bi bi-person-bounding-box d-block" style="font-size: 2.4rem; opacity: 0.5;"></i>
                  No photo yet
                </div>
              <?php endif; ?>
              <video id="studentPhotoVideo" class="d-none" autoplay playsinline muted
                     style="width:100%; height:100%; object-fit:cover; background:#000;"></video>
              <canvas id="studentPhotoCanvas" class="d-none"></canvas>
            </div>
            <div class="small text-muted mt-2">JPG, PNG or WebP · max 5 MB</div>
          </div>

          <!-- Controls -->
          <div class="col-md-8 col-lg-9">
            <!-- Upload pane -->
            <div id="photoUploadPane" class="entity-form__panel">
              <label class="form-label small fw-semibold mb-1" for="studentPhotoFile">
                Choose a passport photo from this device
              </label>
              <input type="file" id="studentPhotoFile" name="photo_file"
                     class="form-control form-control-sm shadow-sm"
                     accept="image/jpeg,image/png,image/webp">
              <div class="form-text small">
                Tip: face centred, plain background. The image is stored as-is — crop it before uploading if you need a tighter frame.
              </div>
            </div>

            <!-- Capture pane -->
            <div id="photoCapturePane" class="entity-form__panel d-none">
              <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                <button type="button" class="btn btn-primary btn-sm" id="photoStartCam">
                  <i class="bi bi-camera-video"></i> Start camera
                </button>
                <button type="button" class="btn btn-success btn-sm d-none" id="photoSnap">
                  <i class="bi bi-camera-fill"></i> Capture
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="photoRetake">
                  <i class="bi bi-arrow-counterclockwise"></i> Retake
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm d-none" id="photoStopCam">
                  <i class="bi bi-x-circle"></i> Stop camera
                </button>
                <span id="photoCamStatus" class="small text-muted ms-1"></span>
              </div>
              <div id="photoCamError" class="alert alert-warning small py-2 px-3 mb-0 d-none" role="alert"></div>
              <div class="form-text small mb-0">
                Your browser will ask for camera permission. The photo is taken on this device — nothing is sent until you save the form.
              </div>
            </div>

            <!-- Hidden field that carries the captured snapshot to the server -->
            <input type="hidden" id="studentPhotoData" name="photo_data" value="">

            <?php if ($editing && $existingPhoto !== ''): ?>
              <div class="mt-3 d-flex flex-wrap gap-2 align-items-center">
                <span class="small text-muted">Current photo on record.</span>
                <button type="submit"
                        formaction="<?= $base ?>/students/<?= (int) $student['id'] ?>/photo/delete"
                        formmethod="post"
                        formnovalidate
                        class="btn btn-sm btn-outline-danger"
                        data-confirm="Remove this student's passport photo?">
                  <i class="bi bi-trash"></i> Remove photo
                </button>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </form>

  <div class="modal fade" id="studentFormErrorModal" tabindex="-1" aria-labelledby="studentFormErrorModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
        <div class="modal-body p-4">
          <div class="d-flex gap-3 align-items-start">
            <div class="flex-shrink-0 rounded-circle bg-danger-subtle text-danger d-flex align-items-center justify-content-center shadow-sm"
                 style="width: 3rem; height: 3rem;">
              <i class="bi bi-exclamation-circle fs-4" aria-hidden="true"></i>
            </div>
            <div class="flex-grow-1 min-w-0 pt-0">
              <h3 class="h6 fw-bold mb-2" id="studentFormErrorModalTitle">Cannot save student</h3>
              <p class="small text-muted mb-2 mb-lg-3">Fix the items below, then try again.</p>
              <ul id="studentFormErrorList" class="small mb-0 ps-3 text-body"></ul>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0 bg-body-secondary bg-opacity-25 px-4 py-3">
          <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">OK</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const form = document.getElementById('studentEnrollmentForm');
  const sel = document.getElementById('classSelect');
  const pv = document.getElementById('admissionPreview');
  const wrap = document.getElementById('streamWrap');
  const stream = document.getElementById('streamSelect');
  const modalEl = document.getElementById('studentFormErrorModal');
  const errList = document.getElementById('studentFormErrorList');
  const upperFields = document.querySelectorAll('.js-upper');

  function upperElement(el) {
    if (!el) return;
    var start = el.selectionStart;
    var end = el.selectionEnd;
    var v = el.value.toUpperCase();
    if (v !== el.value) {
      el.value = v;
      try { el.setSelectionRange(start, end); } catch (_) {}
    }
  }

  function applyUppercase() {
    upperFields.forEach(upperElement);
  }

  upperFields.forEach(function (el) {
    upperElement(el);
    el.addEventListener('input', function () { upperElement(el); });
    el.addEventListener('blur', function () { upperElement(el); });
  });

  function syncStream() {
    if (!sel) return;
    var opt = sel.options[sel.selectedIndex];
    var lvl = (opt && opt.dataset.level) || '';
    var upper = (lvl === 'Form 3' || lvl === 'Form 4');
    if (wrap) wrap.classList.toggle('d-none', !upper);
    if (stream) {
      stream.required = upper;
      if (!upper) stream.value = 'none';
      else if (stream.value === 'none') stream.value = '';
    }
  }
  function syncAdmissionPreview() {
    if (!sel || !pv) return;
    var opt = sel.options[sel.selectedIndex];
    var prefix = (opt && opt.dataset.prefix) || '';
    pv.value = prefix ? prefix + '### (on save)' : 'Assigned on save';
  }
  if (sel) {
    sel.addEventListener('change', function () { syncStream(); syncAdmissionPreview(); });
    syncStream();
    syncAdmissionPreview();
  }

  function validateStudentForm() {
    var errs = [];
    if (sel && (!sel.value || sel.value === '')) {
      errs.push('Select a class.');
    }
    if (!document.querySelector('input[name="section"]:checked')) {
      errs.push('Choose Day or Boarding section.');
    }
    var fn = document.getElementById('studentFirstName');
    var ln = document.getElementById('studentLastName');
    if (fn && !String(fn.value || '').trim()) errs.push('First name is required.');
    if (ln && !String(ln.value || '').trim()) errs.push('Last name is required.');
    var dobEl = document.getElementById('studentDob');
    if (dobEl) {
      var dv = String(dobEl.value || '').trim();
      if (!dv) {
        errs.push('Date of birth is required.');
      } else {
        var ok = /^\d{4}-\d{2}-\d{2}$/.test(dv);
        if (ok) {
          var d = new Date(dv + 'T12:00:00');
          var today = new Date();
          today.setHours(23, 59, 59, 999);
          if (isNaN(d.getTime()) || d > today) ok = false;
        }
        if (!ok) errs.push('Enter a valid date of birth (not in the future).');
      }
    }
    if (wrap && !wrap.classList.contains('d-none') && stream) {
      var sv = stream.value || '';
      if (sv !== 'science' && sv !== 'arts') {
        errs.push('Select Science or Arts stream for Form 3 / Form 4.');
      }
    }
    return errs;
  }

  function showValidationModal(messages) {
    if (!modalEl || !errList) return;
    errList.innerHTML = '';
    messages.forEach(function (m) {
      var li = document.createElement('li');
      li.className = 'mb-1';
      li.textContent = m;
      errList.appendChild(li);
    });
    try {
      var Modal = bootstrap.Modal.getOrCreateInstance(modalEl);
      Modal.show();
    } catch (_) {
      alert(messages.join('\n'));
    }
  }

  if (form) {
    form.addEventListener('submit', function (e) {
      // Skip core validation when this is the "Remove photo" submit — that
      // button uses formaction to post to /photo/delete and only needs the
      // CSRF token to ride along.
      var t = e.submitter;
      if (t && t.getAttribute('formaction')) return;

      applyUppercase();
      var errs = validateStudentForm();
      if (errs.length) {
        e.preventDefault();
        showValidationModal(errs);
      }
    });
  }

  /* -----------------------------------------------------------------
   * Passport photo: upload preview + webcam capture
   * ----------------------------------------------------------------- */
  (function setupPhoto() {
    var modeUpload   = document.getElementById('photoModeUpload');
    var modeCapture  = document.getElementById('photoModeCapture');
    var paneUpload   = document.getElementById('photoUploadPane');
    var paneCapture  = document.getElementById('photoCapturePane');
    var fileInput    = document.getElementById('studentPhotoFile');
    var dataInput    = document.getElementById('studentPhotoData');
    var preview      = document.getElementById('studentPhotoPreview');
    var placeholder  = document.getElementById('studentPhotoPlaceholder');
    var video        = document.getElementById('studentPhotoVideo');
    var canvas       = document.getElementById('studentPhotoCanvas');
    var btnStart     = document.getElementById('photoStartCam');
    var btnSnap      = document.getElementById('photoSnap');
    var btnRetake    = document.getElementById('photoRetake');
    var btnStop      = document.getElementById('photoStopCam');
    var camStatus    = document.getElementById('photoCamStatus');
    var camError     = document.getElementById('photoCamError');

    if (!modeUpload || !modeCapture || !fileInput || !dataInput) return;

    var stream = null;
    var MAX_BYTES = 5 * 1024 * 1024;

    function showPreviewSrc(src) {
      if (!preview) return;
      preview.src = src || '';
      preview.classList.toggle('d-none', !src);
      if (placeholder) placeholder.classList.toggle('d-none', !!src);
      if (video) video.classList.add('d-none');
    }
    function showVideo() {
      if (!video) return;
      video.classList.remove('d-none');
      if (preview) preview.classList.add('d-none');
      if (placeholder) placeholder.classList.add('d-none');
    }
    function setCamError(msg) {
      if (!camError) return;
      camError.textContent = msg || '';
      camError.classList.toggle('d-none', !msg);
    }

    /* ---- Mode toggle ---- */
    function applyMode() {
      var capture = modeCapture.checked;
      if (paneUpload)  paneUpload.classList.toggle('d-none', capture);
      if (paneCapture) paneCapture.classList.toggle('d-none', !capture);

      if (capture) {
        // Switching INTO capture mode: clear the file picker so its file
        // doesn't override the snapshot the user is about to take.
        if (fileInput) fileInput.value = '';
      } else {
        // Switching OUT of capture mode: stop the camera and drop any
        // pending captured snapshot.
        stopCamera();
        if (dataInput) dataInput.value = '';
      }
    }
    modeUpload.addEventListener('change', applyMode);
    modeCapture.addEventListener('change', applyMode);

    /* ---- Upload preview ----
     * Phone cameras routinely produce 3–5 MB photos which is bigger than
     * PHP's default upload_max_filesize (often 2 MB). To make uploads work
     * out of the box we downscale and re-encode the picked image to a
     * sensible JPEG before submit. The downscaled file replaces the file
     * in <input type="file">, so the browser submits the small version.
     */
    var MAX_DIM = 1024;          // longest edge in pixels
    var TARGET_BYTES = 900 * 1024; // aim for ≲ 900 KB so we're well under 2 MB
    var JPEG_QUALITY = 0.85;

    function shrinkImage(file, cb) {
      // Only shrink raster images we can decode; everything else we hand back as-is.
      if (!/^image\/(jpeg|png|webp)$/i.test(file.type)) { cb(null, file); return; }

      var reader = new FileReader();
      reader.onerror = function () { cb('Could not read the picked file.', null); };
      reader.onload = function () {
        var img = new Image();
        img.onerror = function () { cb('That file does not look like a valid image.', null); };
        img.onload = function () {
          var w = img.naturalWidth, h = img.naturalHeight;
          if (!w || !h) { cb('That file does not look like a valid image.', null); return; }

          // If already small (pixels AND bytes), don't recompress — keep
          // the original quality.
          if (w <= MAX_DIM && h <= MAX_DIM && file.size <= TARGET_BYTES) {
            cb(null, file);
            return;
          }
          var scale = Math.min(1, MAX_DIM / Math.max(w, h));
          var dw = Math.max(1, Math.round(w * scale));
          var dh = Math.max(1, Math.round(h * scale));
          var c = document.createElement('canvas');
          c.width = dw; c.height = dh;
          var cx = c.getContext('2d');
          cx.imageSmoothingQuality = 'high';
          cx.drawImage(img, 0, 0, dw, dh);
          c.toBlob(function (blob) {
            if (!blob) { cb(null, file); return; }
            // Wrap the blob in a File so the form keeps a real filename.
            var name = (file.name || 'photo').replace(/\.[^.]+$/, '') + '.jpg';
            try {
              var out = new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() });
              cb(null, out);
            } catch (_) {
              // Older Safari: File constructor missing — fall back to blob.
              blob.name = name;
              cb(null, blob);
            }
          }, 'image/jpeg', JPEG_QUALITY);
        };
        img.src = String(reader.result || '');
      };
      reader.readAsDataURL(file);
    }

    function replaceInputFile(input, fileLike) {
      try {
        var dt = new DataTransfer();
        dt.items.add(fileLike);
        input.files = dt.files;
        return true;
      } catch (_) {
        return false; // very old browsers — submit the raw original
      }
    }

    fileInput.addEventListener('change', function () {
      var f = fileInput.files && fileInput.files[0];
      if (!f) return;
      // File-mode wins on the server, so any captured webcam data is now stale.
      dataInput.value = '';

      shrinkImage(f, function (err, out) {
        if (err) {
          alert(err);
          fileInput.value = '';
          showPreviewSrc('');
          return;
        }
        var finalFile = out || f;
        if (finalFile.size > MAX_BYTES) {
          alert('Photo is too large. Max 5 MB.');
          fileInput.value = '';
          showPreviewSrc('');
          return;
        }
        // Swap the (possibly shrunk) file back into the <input> so the form
        // submits the smaller version.
        if (out && out !== f) replaceInputFile(fileInput, out);
        var reader2 = new FileReader();
        reader2.onload = function (ev) { showPreviewSrc(String(ev.target.result || '')); };
        reader2.readAsDataURL(finalFile);
      });
    });

    /* ---- Webcam ---- */
    function stopCamera() {
      if (stream) {
        try { stream.getTracks().forEach(function (t) { t.stop(); }); } catch (_) {}
      }
      stream = null;
      if (video) { video.srcObject = null; video.classList.add('d-none'); }
      if (btnStart)  btnStart.classList.remove('d-none');
      if (btnSnap)   btnSnap.classList.add('d-none');
      if (btnStop)   btnStop.classList.add('d-none');
      if (btnRetake) btnRetake.classList.add('d-none');
      if (camStatus) camStatus.textContent = '';
    }

    if (btnStart) {
      btnStart.addEventListener('click', function () {
        setCamError('');
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
          setCamError('This browser does not support camera access. Use Upload instead.');
          return;
        }
        camStatus.textContent = 'Starting camera…';
        navigator.mediaDevices.getUserMedia({
          video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
          audio: false
        }).then(function (s) {
          stream = s;
          video.srcObject = s;
          showVideo();
          btnStart.classList.add('d-none');
          btnSnap.classList.remove('d-none');
          btnStop.classList.remove('d-none');
          btnRetake.classList.add('d-none');
          camStatus.textContent = 'Live · click Capture when ready';
          // Keep preview/file empty so a stale image doesn't get submitted.
          if (preview) preview.src = '';
          if (fileInput) fileInput.value = '';
          dataInput.value = '';
        }).catch(function (err) {
          camStatus.textContent = '';
          var msg = (err && err.name === 'NotAllowedError')
            ? 'Camera permission was denied. Allow it in the browser address bar, or use Upload instead.'
            : 'Could not start the camera (' + (err && err.message ? err.message : 'unknown error') + ').';
          setCamError(msg);
        });
      });
    }

    if (btnSnap) {
      btnSnap.addEventListener('click', function () {
        if (!video || !canvas || !video.videoWidth) return;
        var w = video.videoWidth, h = video.videoHeight;
        canvas.width = w;
        canvas.height = h;
        var ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, w, h);
        // JPEG to keep size sensible; quality 0.92 ≈ visually lossless for ID photos.
        var dataUrl = canvas.toDataURL('image/jpeg', 0.92);
        dataInput.value = dataUrl;
        if (fileInput) fileInput.value = ''; // capture wins
        showPreviewSrc(dataUrl);
        // Stop the live stream once we have a frame; the user can hit Retake.
        try { stream.getTracks().forEach(function (t) { t.stop(); }); } catch (_) {}
        stream = null;
        if (video) video.srcObject = null;
        btnSnap.classList.add('d-none');
        btnRetake.classList.remove('d-none');
        btnStop.classList.add('d-none');
        camStatus.textContent = 'Captured · Save the form to upload';
      });
    }

    if (btnRetake) {
      btnRetake.addEventListener('click', function () {
        dataInput.value = '';
        showPreviewSrc('');
        // Re-trigger the start flow so the user can take another shot.
        btnRetake.classList.add('d-none');
        btnStart.click();
      });
    }

    if (btnStop) {
      btnStop.addEventListener('click', function () {
        stopCamera();
      });
    }

    // Stop the camera if the user navigates away or submits.
    window.addEventListener('beforeunload', stopCamera);
    if (form) form.addEventListener('submit', function () {
      // Stop the live preview but DON'T clear dataInput — its value is the
      // captured snapshot the server will save.
      if (stream) {
        try { stream.getTracks().forEach(function (t) { t.stop(); }); } catch (_) {}
        stream = null;
      }
    });
  })();
})();
</script>
