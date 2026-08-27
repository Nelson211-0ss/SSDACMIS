<?php
use App\Core\View;
$editing  = (bool) ($parentAccount ?? null);
$layout   = 'app';
$title    = $editing ? 'Edit Parent' : 'New Parent';
$action   = $editing
    ? ($base . '/parents/' . (int) $parentAccount['id'])
    : ($base . '/parents');
$schools    = $schools ?? [];
$students   = $students ?? [];
$linkedIds  = $linkedIds ?? [];
$linkedSet  = array_flip($linkedIds);
$schoolId   = (int) ($schoolId ?? 0);
$primaryId  = (int) ($primaryId ?? 0);
$needsSchoolPicker = !$editing && !empty($schools);
?>
<div class="entity-form">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <?php if (!$editing): ?>
        <span class="badge rounded-pill bg-info-subtle text-info-emphasis" style="font-size: 0.65rem; font-weight: 700; letter-spacing: 0.06em;">NEW</span>
      <?php else: ?>
        <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis" style="font-size: 0.65rem; font-weight: 700; letter-spacing: 0.06em;">EDIT</span>
      <?php endif; ?>
      <h2 class="h5 mb-0"><?= View::e($title) ?></h2>
      <span class="text-muted small d-none d-md-inline">— Parent Portal account</span>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/parents"><i class="bi bi-arrow-left"></i> Back</a>
  </div>

  <?php if ($needsSchoolPicker): ?>
  <!-- Super admin: pick the school first (reloads via GET) so the children
       picker below lists that school's students, not every school's. -->
  <form method="get" action="<?= $base ?>/parents/create" class="card border-0 shadow-sm mb-3">
    <div class="card-body d-flex flex-wrap align-items-end gap-2">
      <div class="flex-grow-1" style="min-width: 220px;">
        <label class="form-label small fw-semibold mb-1">School</label>
        <select name="school_id" class="form-select form-select-sm shadow-sm">
          <?php foreach ($schools as $sc): ?>
            <option value="<?= (int) $sc['id'] ?>" <?= (int) $sc['id'] === $schoolId ? 'selected' : '' ?>><?= View::e($sc['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-repeat"></i> Load school's students</button>
    </div>
  </form>
  <?php endif; ?>

  <form method="post" action="<?= $action ?>">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <?php if ($needsSchoolPicker): ?>
      <input type="hidden" name="school_id" value="<?= $schoolId ?>">
    <?php endif; ?>

    <div class="card entity-form__card border-0 shadow-sm mb-3">
      <div class="card-body">
        <div class="row entity-form__split g-3 gx-xl-4 gy-3">

          <div class="col-xl-7 entity-form__divider mb-xl-0 mb-3">
            <div class="entity-form__col-title">
              <span class="card-header-icon card-header-icon--green" style="width: 1.35rem; height: 1.35rem; font-size: 0.75rem;"><i class="bi bi-person-hearts"></i></span>
              Parent profile
            </div>

            <div class="row g-2 mb-2">
              <div class="col-md-12">
                <label class="form-label small fw-semibold mb-1">Full name <span class="text-danger">*</span></label>
                <input name="name" class="form-control form-control-sm shadow-sm" required
                       placeholder="e.g. Grace Adut"
                       value="<?= View::e($parentAccount['name'] ?? '') ?>">
              </div>
            </div>

            <div class="row g-2 mb-2">
              <div class="col-md-7">
                <label class="form-label small fw-semibold mb-1">Email <span class="text-muted fw-normal">(sign-in)</span> <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control form-control-sm shadow-sm" required
                       placeholder="parent@example.com"
                       autocomplete="username"
                       value="<?= View::e($parentAccount['email'] ?? '') ?>">
              </div>
              <div class="col-md-5">
                <label class="form-label small fw-semibold mb-1">Status</label>
                <select name="status" class="form-select form-select-sm shadow-sm">
                  <?php $st = $parentAccount['status'] ?? 'active'; ?>
                  <option value="active"   <?= $st === 'active'   ? 'selected' : '' ?>>Active</option>
                  <option value="disabled" <?= $st === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                </select>
              </div>
            </div>

            <div class="entity-form__panel mb-0">
              <p class="small text-muted mb-0"><i class="bi bi-shield-lock me-1"></i>No password to set — a parent signs in with their <strong>primary</strong> linked child's admission number (pick which one below).</p>
            </div>
          </div>

          <div class="col-xl-5">
            <div class="entity-form__col-title">
              <span class="card-header-icon card-header-icon--teal" style="width: 1.35rem; height: 1.35rem; font-size: 0.75rem;"><i class="bi bi-door-open"></i></span>
              Parent portal
            </div>
            <div class="entity-form__panel small mb-2">
              <p class="mb-2"><strong class="text-body">Sign-in URL</strong><br>
                <code class="small"><?= $base ?>/parent/login</code>
              </p>
              <p class="mb-0 text-muted">
                Parents see report cards, fee balances and attendance for
                only the child(ren) linked below. Sign-in is the
                <strong>primary</strong> child's admission number, entered
                as both username and password — no email or password to
                share.
              </p>
            </div>
          </div>

        </div>
      </div>
    </div>

    <div class="card entity-form__card border-0 shadow-sm mb-3">
      <div class="card-body">
        <div class="entity-form__col-title mb-2">
          <span class="card-header-icon card-header-icon--blue" style="width: 1.35rem; height: 1.35rem; font-size: 0.75rem;"><i class="bi bi-people"></i></span>
          Linked children
        </div>
        <p class="small text-muted mb-2">
          Check every child this parent may view. Use the <i class="bi bi-key-fill"></i> radio to mark
          which one's admission number they sign in with.
        </p>

        <?php if (empty($students)): ?>
          <p class="small text-muted mb-0">
            <?= $needsSchoolPicker ? 'No students found for the selected school yet.' : 'No students found for this school yet.' ?>
          </p>
        <?php else: ?>
          <div class="compact-search compact-search--reports mb-2" style="max-width: 320px;">
            <div class="input-group input-group-sm rounded-2 overflow-hidden">
              <span class="input-group-text py-1"><i class="bi bi-search"></i></span>
              <input type="search" id="parentChildSearch" class="form-control py-1"
                     placeholder="Filter by name or admission no." autocomplete="off">
            </div>
          </div>
          <div class="row g-2" id="parentChildList" style="max-height: 320px; overflow-y: auto;">
            <?php foreach ($students as $s):
              $sid   = (int) $s['id'];
              $hay   = strtolower(trim($s['admission_no'] . ' ' . $s['first_name'] . ' ' . $s['last_name']));
            ?>
              <div class="col-md-6" data-child-row data-search="<?= View::e($hay) ?>">
                <label class="d-flex align-items-center gap-2 border rounded-2 px-2 py-1 mb-0 small">
                  <input type="checkbox" class="form-check-input flex-shrink-0" name="student_ids[]" value="<?= $sid ?>"
                         data-child-check
                         <?= isset($linkedSet[$sid]) ? 'checked' : '' ?>>
                  <span class="flex-grow-1">
                    <span class="fw-semibold"><?= View::e(trim($s['first_name'] . ' ' . $s['last_name'])) ?></span>
                    <span class="text-muted"> — <?= View::e($s['admission_no']) ?><?= $s['class_name'] ? ' · ' . View::e($s['class_name']) : '' ?></span>
                  </span>
                  <input type="radio" class="form-check-input flex-shrink-0" name="primary_student_id" value="<?= $sid ?>"
                         data-child-primary
                         title="Sign in with this child's admission number"
                         <?= $primaryId === $sid ? 'checked' : '' ?>>
                  <i class="bi bi-key-fill text-muted flex-shrink-0" aria-hidden="true" title="Sign-in child"></i>
                </label>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="card-footer py-2 px-3 bg-body-secondary bg-opacity-25 border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="small text-muted mb-0"><span class="text-danger">*</span> Required · link at least one child and mark one as the sign-in child</span>
        <div class="d-flex flex-wrap gap-2 ms-auto">
          <a href="<?= $base ?>/parents" class="btn btn-outline-secondary btn-sm px-3">Cancel</a>
          <button type="submit" class="btn btn-primary btn-sm px-4"><i class="bi bi-check-lg me-1"></i><?= $editing ? 'Save changes' : 'Create Parent' ?></button>
        </div>
      </div>
    </div>
  </form>
</div>

<script>
(function () {
  var input = document.getElementById('parentChildSearch');
  var rows  = Array.prototype.slice.call(document.querySelectorAll('[data-child-row]'));
  if (input && rows.length) {
    input.addEventListener('input', function () {
      var q = input.value.toLowerCase().trim();
      rows.forEach(function (row) {
        row.hidden = q !== '' && (row.getAttribute('data-search') || '').indexOf(q) === -1;
      });
    });
  }

  // Keep the "linked" checkbox and "sign-in child" radio consistent: picking
  // a child as the sign-in child implies linking them too, and unlinking the
  // current sign-in child clears the radio (an admin must pick a new one).
  var checks = Array.prototype.slice.call(document.querySelectorAll('[data-child-check]'));
  var radios = Array.prototype.slice.call(document.querySelectorAll('[data-child-primary]'));

  radios.forEach(function (radio) {
    radio.addEventListener('change', function () {
      if (!radio.checked) return;
      var check = checks.find(function (c) { return c.value === radio.value; });
      if (check) check.checked = true;
    });
  });

  checks.forEach(function (check) {
    check.addEventListener('change', function () {
      if (check.checked) return;
      var radio = radios.find(function (r) { return r.value === check.value; });
      if (radio && radio.checked) radio.checked = false;
    });
  });
})();
</script>
