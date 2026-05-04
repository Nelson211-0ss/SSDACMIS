<?php use App\Core\View; $layout = 'app'; $title = 'Classes'; ?>
<h4 class="mb-3"><i class="bi bi-building"></i> Classes</h4>

<div class="row g-3">
  <?php if ($auth['role'] === 'admin'): ?>
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white d-flex align-items-center">
        <span class="card-header-icon card-header-icon--blue me-2" aria-hidden="true"><i class="bi bi-building-add"></i></span>
        <strong class="mb-0">Add class</strong>
      </div>
      <div class="card-body">
        <form method="post" action="<?= $base ?>/classes">
          <input type="hidden" name="_csrf" value="<?= $csrf ?>">
          <div class="mb-2">
            <label class="form-label">Name</label>
            <input name="name" class="form-control" required placeholder="e.g. Form 1A">
          </div>
          <div class="mb-2">
            <label class="form-label">Level</label>
            <input name="level" class="form-control" placeholder="e.g. Form 1">
          </div>
          <div class="mb-3">
            <label class="form-label">Admission Prefix <span class="text-muted small">(optional)</span></label>
            <input name="admission_prefix" class="form-control text-uppercase" maxlength="10" placeholder="auto from name (e.g. F1A)">
            <div class="form-text">Used to build student admission numbers like <code>F1A001</code>.</div>
          </div>
          <button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Add</button>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="col-lg-<?= $auth['role'] === 'admin' ? 8 : 12 ?>">
    <div class="card border-0 shadow-sm">
      <div class="table-responsive">
        <table class="table mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Name</th><th>Level</th><th>Adm. Prefix</th><th>Students</th><th>Class Teacher</th>
              <?= $auth['role'] === 'admin' ? '<th></th>' : '' ?>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($classes)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No classes yet.</td></tr>
          <?php else: foreach ($classes as $c): ?>
            <tr>
              <td><?= View::e($c['name']) ?></td>
              <td><?= View::e($c['level'] ?: '—') ?></td>
              <td>
                <?php if ($auth['role'] === 'admin'): ?>
                  <form method="post"
                        action="<?= $base ?>/classes/<?= (int) $c['id'] ?>/prefix"
                        class="d-flex gap-1">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                    <input name="admission_prefix" class="form-control form-control-sm text-uppercase font-monospace"
                           style="max-width: 7rem"
                           maxlength="10"
                           value="<?= View::e($c['admission_prefix'] ?? '') ?>">
                    <button class="btn btn-sm btn-outline-primary" title="Save prefix"><i class="bi bi-check2"></i></button>
                  </form>
                <?php else: ?>
                  <code><?= View::e($c['admission_prefix'] ?: '—') ?></code>
                <?php endif; ?>
              </td>
              <td><span class="badge bg-secondary"><?= (int)$c['student_count'] ?></span></td>
              <td>
                <?php if ($auth['role'] === 'admin'): ?>
                  <form method="post"
                        action="<?= $base ?>/classes/<?= (int) $c['id'] ?>/teacher"
                        class="d-flex gap-1">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                    <select name="class_teacher_id" class="form-select form-select-sm">
                      <option value="">— None —</option>
                      <?php foreach ($staff as $st): ?>
                        <option value="<?= (int) $st['id'] ?>"
                          <?= ((int) $c['class_teacher_id']) === ((int) $st['id']) ? 'selected' : '' ?>>
                          <?= View::e(trim($st['first_name'].' '.$st['last_name'])) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-outline-primary" title="Save"><i class="bi bi-check2"></i></button>
                  </form>
                <?php else: ?>
                  <?= View::e(trim(($c['teacher_first'] ?? '').' '.($c['teacher_last'] ?? ''))) ?: '—' ?>
                <?php endif; ?>
              </td>
              <?php if ($auth['role'] === 'admin'): ?>
                <td class="text-end">
                  <form method="post" action="<?= $base ?>/classes/<?= (int)$c['id'] ?>/delete" data-confirm="Delete this class?">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                  </form>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
