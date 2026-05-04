<?php
use App\Core\View;
$layout = 'app';
$title = 'Teaching Assignments';
$catLabel = ['core'=>'Compulsory Core','science'=>'Science','arts'=>'Arts','optional'=>'Optional'];
$catBadge = ['core'=>'bg-primary-subtle text-primary-emphasis','science'=>'bg-success-subtle text-success-emphasis','arts'=>'bg-warning-subtle text-warning-emphasis','optional'=>'bg-secondary-subtle text-secondary-emphasis'];
?>
<h4 class="mb-3"><i class="bi bi-diagram-3"></i> Teaching Assignments</h4>
<p class="text-muted small mb-3">
  Use this page to (a) appoint <strong>Heads of Department</strong> who upload
  marks for an entire department across every class, and (b) optionally assign
  individual teachers to a single subject in a single class.
</p>

<!-- Department Heads ====================================================== -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white d-flex align-items-center">
    <span class="card-header-icon card-header-icon--purple me-2" aria-hidden="true"><i class="bi bi-mortarboard-fill"></i></span>
    <strong>Heads of Department</strong>
    <span class="text-muted small ms-2">— upload marks for every subject in their department, across all classes.</span>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-lg-5">
        <form method="post" action="<?= $base ?>/teaching/heads" class="row g-2 align-items-end">
          <input type="hidden" name="_csrf" value="<?= $csrf ?>">
          <div class="col-sm-5">
            <label class="form-label small mb-1">Teacher</label>
            <select name="staff_id" class="form-select form-select-sm" required>
              <option value="">— Select —</option>
              <?php foreach ($staff as $s): ?>
                <option value="<?= (int) $s['id'] ?>"><?= View::e(trim($s['first_name'].' '.$s['last_name'])) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-sm-5">
            <label class="form-label small mb-1">Department</label>
            <select name="category" class="form-select form-select-sm" required>
              <option value="">— Select —</option>
              <?php foreach ($catLabel as $cat => $label): ?>
                <option value="<?= $cat ?>"><?= View::e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-sm-2">
            <button class="btn btn-primary btn-sm w-100"><i class="bi bi-plus-lg"></i></button>
          </div>
        </form>
      </div>
      <div class="col-lg-7">
        <?php if (empty($heads)): ?>
          <p class="text-muted small mb-0">No department heads appointed yet.</p>
        <?php else: ?>
          <div class="d-flex flex-wrap gap-2">
            <?php foreach ($heads as $h): ?>
              <div class="d-inline-flex align-items-center border rounded-pill ps-3 pe-1 py-1 bg-light">
                <span class="me-2"><?= View::e(trim($h['first_name'].' '.$h['last_name'])) ?></span>
                <span class="badge text-capitalize me-2 <?= $catBadge[$h['category']] ?? 'bg-secondary' ?>">
                  <?= View::e($catLabel[$h['category']] ?? $h['category']) ?>
                </span>
                <form method="post" action="<?= $base ?>/teaching/heads/delete" class="d-inline" data-confirm="Remove this department head?">
                  <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                  <input type="hidden" name="staff_id" value="<?= (int) $h['staff_id'] ?>">
                  <input type="hidden" name="category" value="<?= View::e($h['category']) ?>">
                  <button class="btn btn-sm btn-link text-danger p-0 px-1" title="Remove"><i class="bi bi-x-lg"></i></button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<h5 class="mb-2 text-muted"><i class="bi bi-person-vcard"></i> Per-subject teachers <span class="small fw-normal">(optional — for staff who are not HODs)</span></h5>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white d-flex align-items-center">
        <span class="card-header-icon card-header-icon--green me-2" aria-hidden="true"><i class="bi bi-person-plus"></i></span>
        <strong class="mb-0">Assign teacher</strong>
      </div>
      <div class="card-body">
        <?php if (empty($staff) || empty($classes) || empty($subjects)): ?>
          <p class="text-muted small mb-0">
            You need at least one
            <?php if (empty($staff)): ?> <a href="<?= $base ?>/staff">staff member</a><?php endif; ?>
            <?php if (empty($classes)): ?> <a href="<?= $base ?>/classes">class</a><?php endif; ?>
            <?php if (empty($subjects)): ?> <a href="<?= $base ?>/subjects">subject</a><?php endif; ?>
            before you can create an assignment.
          </p>
        <?php else: ?>
          <form method="post" action="<?= $base ?>/teaching">
            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
            <div class="mb-2">
              <label class="form-label">Teacher</label>
              <select name="staff_id" class="form-select" required>
                <option value="">— Select —</option>
                <?php foreach ($staff as $s): ?>
                  <option value="<?= (int) $s['id'] ?>">
                    <?= View::e(trim($s['first_name'] . ' ' . $s['last_name'])) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-2">
              <label class="form-label">Class</label>
              <select name="class_id" class="form-select" required>
                <option value="">— Select —</option>
                <?php foreach ($classes as $c): ?>
                  <option value="<?= (int) $c['id'] ?>"><?= View::e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Subject</label>
              <select name="subject_id" class="form-select" required>
                <option value="">— Select —</option>
                <?php
                $catLabel = ['core'=>'Compulsory Core','science'=>'Science','arts'=>'Arts','optional'=>'Optional'];
                $grouped = [];
                foreach ($subjects as $sub) { $grouped[$sub['category']][] = $sub; }
                foreach ($grouped as $cat => $list):
                ?>
                  <optgroup label="<?= View::e($catLabel[$cat] ?? ucfirst($cat)) ?>">
                    <?php foreach ($list as $sub): ?>
                      <option value="<?= (int) $sub['id'] ?>"><?= View::e($sub['name']) ?></option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endforeach; ?>
              </select>
            </div>
            <button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Assign</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="table-responsive">
        <table class="table mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Class</th><th>Subject</th><th>Teacher</th><th></th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($assignments)): ?>
            <tr><td colspan="4" class="text-center text-muted py-4">No teaching assignments yet.</td></tr>
          <?php else: foreach ($assignments as $a): ?>
            <tr>
              <td><strong><?= View::e($a['class_name']) ?></strong></td>
              <td>
                <?= View::e($a['subject_name']) ?>
                <span class="badge bg-light text-dark border ms-1 small text-capitalize"><?= View::e($a['category']) ?></span>
              </td>
              <td><?= View::e(trim($a['first_name'] . ' ' . $a['last_name'])) ?></td>
              <td class="text-end">
                <form method="post" action="<?= $base ?>/teaching/<?= (int) $a['id'] ?>/delete"
                      data-confirm="Remove this teaching assignment?">
                  <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                  <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
