<?php
use App\Core\View;

$layout = 'app';
$title  = 'Import students';
$isAdmin = !empty($isAdmin);
$schools = $schools ?? [];
$classes = $classes ?? [];
$prefillClassId = (int) ($prefillClassId ?? 0);

// Super admin: if we arrived with a class already picked (e.g. "Import" link
// from the Classes page), preselect that class's school too.
$selectedSchoolId = 0;
if ($isAdmin && $prefillClassId > 0) {
    foreach ($classes as $c) {
        if ((int) $c['id'] === $prefillClassId) { $selectedSchoolId = (int) ($c['school_id'] ?? 0); break; }
    }
}
?>

<div class="page-header mb-4">
  <div>
    <nav aria-label="breadcrumb" class="mb-2">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= $base ?>/students">Students</a></li>
        <li class="breadcrumb-item active" aria-current="page">Import</li>
      </ol>
    </nav>
    <h2 class="h4 mb-1"><i class="bi bi-file-earmark-spreadsheet"></i> Import students from CSV</h2>
    <p class="page-header__sub mb-0">Admit many students to one class at once from a spreadsheet.</p>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-lg-8">

    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <h3 class="h6 fw-bold mb-2"><i class="bi bi-info-circle"></i> How it works</h3>
        <ol class="small mb-3 ps-3">
          <li>Download the template below and fill in one row per student — keep the first (header) row as it is.</li>
          <li>In Excel: <strong>File &rarr; Save As &rarr; CSV (Comma delimited) (*.csv)</strong>, then upload that .csv file here.</li>
          <li>Pick the class every student in the file belongs to — admission numbers are generated automatically from that class's prefix.</li>
        </ol>
        <div class="table-responsive">
          <table class="table table-sm small mb-3">
            <thead><tr><th>Column</th><th>Required</th><th>Notes</th></tr></thead>
            <tbody>
              <tr><td class="font-monospace">first_name</td><td>Yes</td><td>&mdash;</td></tr>
              <tr><td class="font-monospace">last_name</td><td>Yes</td><td>&mdash;</td></tr>
              <tr><td class="font-monospace">gender</td><td>No</td><td>male / female / other (default male)</td></tr>
              <tr><td class="font-monospace">dob</td><td>No</td><td>YYYY-MM-DD, not in the future</td></tr>
              <tr><td class="font-monospace">section</td><td>No</td><td>day / boarding (default day)</td></tr>
              <tr><td class="font-monospace">stream</td><td>Form 3/4 only</td><td>science / arts</td></tr>
              <tr><td class="font-monospace">guardian_name</td><td>No</td><td>&mdash;</td></tr>
              <tr><td class="font-monospace">guardian_phone</td><td>No</td><td>&mdash;</td></tr>
              <tr><td class="font-monospace">address</td><td>No</td><td>&mdash;</td></tr>
            </tbody>
          </table>
        </div>
        <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/students/import/template">
          <i class="bi bi-download"></i> Download CSV template
        </a>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <form method="post" action="<?= $base ?>/students/import" enctype="multipart/form-data" id="studentImportForm">
          <input type="hidden" name="_csrf" value="<?= $csrf ?>">

          <?php if ($isAdmin && !empty($schools)): ?>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="importSchool">School <span class="text-danger">*</span></label>
            <select name="school_id" id="importSchool" class="form-select" required>
              <option value="">— Choose school first —</option>
              <?php foreach ($schools as $sch): ?>
                <option value="<?= (int) $sch['id'] ?>" <?= $selectedSchoolId === (int) $sch['id'] ? 'selected' : '' ?>><?= View::e((string) $sch['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>

          <div class="mb-3">
            <label class="form-label fw-semibold" for="importClass">Class <span class="text-danger">*</span></label>
            <select name="class_id" id="importClass" class="form-select" required <?= $isAdmin && !empty($schools) ? 'disabled' : '' ?>>
              <option value=""><?= $isAdmin ? 'Select school first…' : 'Choose…' ?></option>
              <?php foreach ($classes as $c): ?>
                <option value="<?= (int) $c['id'] ?>"
                        data-school="<?= (int) ($c['school_id'] ?? 0) ?>"
                        <?= $prefillClassId === (int) $c['id'] ? 'selected' : '' ?>>
                  <?= View::e(mb_strtoupper((string) ($c['name'] ?? ''), 'UTF-8')) ?><?php if (!empty($c['admission_prefix'])): ?> · <?= View::e(mb_strtoupper((string) $c['admission_prefix'], 'UTF-8')) ?><?php endif; ?>
                </option>
              <?php endforeach; ?>
            </select>
            <p class="form-text mb-0">Every student in the file is admitted into this class.</p>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold" for="importFile">CSV file <span class="text-danger">*</span></label>
            <input type="file" id="importFile" name="csv_file" class="form-control" accept=".csv,text/csv" required disabled>
            <p class="form-text mb-0" id="importFileHint">Choose a class above first.</p>
          </div>

          <div class="d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Import students</button>
            <a href="<?= $base ?>/students" class="btn btn-outline-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>

  </div>
</div>

<?php if ($isAdmin && !empty($schools)): ?>
<script>
(function () {
  var schoolSel = document.getElementById('importSchool');
  var classSel  = document.getElementById('importClass');
  if (!schoolSel || !classSel) return;
  if (!classSel._allOptions) classSel._allOptions = Array.from(classSel.options);

  function filter() {
    var chosen = schoolSel.value;
    var prevVal = classSel.value;
    while (classSel.options.length > 0) classSel.remove(0);
    classSel._allOptions.forEach(function (opt) {
      if (opt.value === '' || opt.dataset.school === chosen || chosen === '') {
        classSel.appendChild(opt.cloneNode(true));
      }
    });
    classSel.value = prevVal;
    if (!classSel.value && classSel.options.length > 0) classSel.options[0].selected = true;
    // Setting .value programmatically doesn't fire 'change' — dispatch it so
    // the file-picker gating below stays in sync when the class list is
    // rebuilt (e.g. it resets to "— choose —" after switching school).
    classSel.dispatchEvent(new Event('change'));
  }

  schoolSel.addEventListener('change', function () {
    classSel.disabled = !schoolSel.value;
    filter();
  });

  if (schoolSel.value) {
    classSel.disabled = false;
    filter();
  }
})();
</script>
<?php endif; ?>

<script>
(function () {
  // The file picker stays disabled until a class is chosen, so the upload
  // is always tied to a class rather than left to fill in at submit time.
  var classSel  = document.getElementById('importClass');
  var fileInput = document.getElementById('importFile');
  var hint      = document.getElementById('importFileHint');
  if (!classSel || !fileInput) return;

  function syncFileEnabled() {
    var enabled = !!classSel.value;
    fileInput.disabled = !enabled;
    if (!enabled) fileInput.value = '';
    if (hint) hint.classList.toggle('d-none', enabled);
  }

  classSel.addEventListener('change', syncFileEnabled);
  syncFileEnabled();
})();
</script>
