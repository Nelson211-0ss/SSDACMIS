<?php
use App\Core\View;
$layout = 'app';
$editing = (bool) ($staff ?? null);
$title = $editing ? 'Edit Staff' : 'New Staff';
$action = $editing ? ($base . '/staff/' . (int) $staff['id']) : ($base . '/staff');
$catLabel = ['core'=>'Compulsory Core','science'=>'Science','arts'=>'Arts','optional'=>'Optional'];
$catBadge = ['core'=>'bg-primary-subtle text-primary-emphasis','science'=>'bg-success-subtle text-success-emphasis','arts'=>'bg-warning-subtle text-warning-emphasis','optional'=>'bg-secondary-subtle text-secondary-emphasis'];
$selected = array_flip($staffSubjectIds ?? []);
$grouped = [];
foreach (($subjects ?? []) as $sub) { $grouped[$sub['category']][] = $sub; }
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
      <span class="text-muted small d-none d-md-inline">— profile &amp; teaching load</span>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/staff"><i class="bi bi-arrow-left"></i> Back</a>
  </div>

  <form method="post" action="<?= $action ?>">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">

    <div class="card entity-form__card border-0 shadow-sm">
      <div class="card-body">
        <div class="row entity-form__split g-3 gx-xl-4 gy-3">

          <div class="col-xl-6 entity-form__divider mb-xl-0 mb-3">
            <div class="entity-form__col-title">
              <span class="card-header-icon card-header-icon--blue" style="width: 1.35rem; height: 1.35rem; font-size: 0.75rem;"><i class="bi bi-person-badge"></i></span>
              Profile &amp; account
            </div>

            <div class="row g-2 mb-2">
              <div class="col-md-4">
                <label class="form-label small fw-semibold mb-1">First name <span class="text-danger">*</span></label>
                <input name="first_name" class="form-control form-control-sm shadow-sm" required value="<?= View::e($staff['first_name'] ?? '') ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label small fw-semibold mb-1">Last name</label>
                <input name="last_name" class="form-control form-control-sm shadow-sm" value="<?= View::e($staff['last_name'] ?? '') ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label small fw-semibold mb-1">Phone</label>
                <input name="phone" class="form-control form-control-sm shadow-sm" value="<?= View::e($staff['phone'] ?? '') ?>">
              </div>
            </div>

            <div class="row g-2 mb-2">
              <div class="col-md-7">
                <label class="form-label small fw-semibold mb-1">Email <span class="text-muted fw-normal">(login)</span> <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control form-control-sm shadow-sm" required value="<?= View::e($staff['email'] ?? '') ?>">
              </div>
              <div class="col-md-5">
                <label class="form-label small fw-semibold mb-1">Role</label>
                <select name="role" class="form-select form-select-sm shadow-sm">
                  <?php foreach (['staff','admin'] as $r): ?>
                    <option value="<?= $r ?>" <?= ($staff['role'] ?? 'staff') === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="row g-2 mb-2">
              <div class="col-md-4">
                <label class="form-label small fw-semibold mb-1">Hire date</label>
                <input type="date" name="hire_date" class="form-control form-control-sm shadow-sm" value="<?= View::e($staff['hire_date'] ?? '') ?>">
              </div>
              <div class="col-md-8">
                <label class="form-label small fw-semibold mb-1">Position</label>
                <input name="position" class="form-control form-control-sm shadow-sm" value="<?= View::e($staff['position'] ?? '') ?>" placeholder="e.g. Teacher">
              </div>
            </div>

            <div class="entity-form__panel mb-0">
              <label class="form-label small fw-semibold mb-1">
                Password <?= $editing ? '<span class="text-muted fw-normal">(leave blank to keep)</span>' : '<span class="text-danger">*</span>' ?>
              </label>
              <input type="password" name="password" class="form-control form-control-sm shadow-sm" <?= $editing ? '' : 'required' ?> autocomplete="new-password">
            </div>
          </div>

          <div class="col-xl-6">
            <div class="entity-form__col-title">
              <span class="card-header-icon card-header-icon--orange" style="width: 1.35rem; height: 1.35rem; font-size: 0.75rem;"><i class="bi bi-book-half"></i></span>
              Subjects taught
            </div>
            <div class="d-flex justify-content-end gap-2 mb-2 small">
              <a href="#" class="text-decoration-none" data-subject-toggle="all">Select all</a>
              <span class="text-muted">·</span>
              <a href="#" class="text-decoration-none" data-subject-toggle="none">Clear</a>
            </div>
            <p class="small text-muted mb-2">Tick subjects this staff teaches — used by HODs for oversight.</p>

            <?php if (empty($grouped)): ?>
              <p class="text-muted small mb-0">
                No subjects yet. <a href="<?= $base ?>/subjects">Add subjects first</a>.
              </p>
            <?php else: ?>
              <div class="row g-2">
                <?php foreach ($grouped as $cat => $list):
                  $label = $catLabel[$cat] ?? ucfirst($cat);
                  $badge = $catBadge[$cat] ?? 'bg-secondary';
                ?>
                  <div class="col-md-6">
                    <div class="entity-form__subject-pick">
                      <div class="d-flex justify-content-between align-items-center mb-2 gap-2">
                        <span class="badge text-capitalize <?= $badge ?>"><?= View::e($label) ?></span>
                        <a class="small text-decoration-none" href="#" data-subject-toggle="cat:<?= View::e($cat) ?>">Toggle</a>
                      </div>
                      <?php foreach ($list as $sub):
                        $sid = (int) $sub['id'];
                        $isOn = isset($selected[$sid]);
                      ?>
                        <div class="form-check">
                          <input class="form-check-input subject-checkbox"
                                 data-cat="<?= View::e($cat) ?>"
                                 type="checkbox"
                                 id="sub_<?= $sid ?>"
                                 name="subject_ids[]"
                                 value="<?= $sid ?>"
                                 <?= $isOn ? 'checked' : '' ?>>
                          <label class="form-check-label small" for="sub_<?= $sid ?>">
                            <?= View::e($sub['name']) ?>
                            <?php if (!empty($sub['code'])): ?>
                              <span class="text-muted font-monospace"><?= View::e($sub['code']) ?></span>
                            <?php endif; ?>
                          </label>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

        </div>
      </div>

      <div class="card-footer py-2 px-3 bg-body-secondary bg-opacity-25 border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="small text-muted mb-0"><span class="text-danger">*</span> Required fields</span>
        <div class="d-flex flex-wrap gap-2 ms-auto">
          <a href="<?= $base ?>/staff" class="btn btn-outline-secondary btn-sm px-3">Cancel</a>
          <button type="submit" class="btn btn-primary btn-sm px-4"><i class="bi bi-check-lg me-1"></i><?= $editing ? 'Save' : 'Save staff' ?></button>
        </div>
      </div>
    </div>
  </form>
</div>

<script>
(function () {
  function set(check, on) { check.checked = on; }
  document.querySelectorAll('[data-subject-toggle]').forEach(function (link) {
    link.addEventListener('click', function (e) {
      e.preventDefault();
      var spec = link.dataset.subjectToggle;
      var boxes = document.querySelectorAll('.subject-checkbox');
      if (spec === 'all')   { boxes.forEach(function (b) { set(b, true); }); return; }
      if (spec === 'none')  { boxes.forEach(function (b) { set(b, false); }); return; }
      if (spec.startsWith('cat:')) {
        var cat = spec.slice(4);
        var group = document.querySelectorAll('.subject-checkbox[data-cat="' + cat + '"]');
        var allOn = Array.from(group).every(function (b) { return b.checked; });
        group.forEach(function (b) { set(b, !allOn); });
      }
    });
  });
})();
</script>
