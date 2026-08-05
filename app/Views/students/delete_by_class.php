<?php
use App\Core\View;

$layout = 'app';
$title  = 'Delete students by class';

$isAdmin          = !empty($isAdmin);
$classes          = $classes ?? [];
$schools          = $schools ?? [];
$selectedSchoolId = $selectedSchoolId ?? null;
$selectedClass    = $selectedClass ?? null;
$stream           = $stream ?? 'all';
$isUpperLevel     = !empty($isUpperLevel);
$effectiveStream  = $effectiveStream ?? 'all';
$studentCount     = (int) ($studentCount ?? 0);

$streamNoun = $effectiveStream === 'all' ? '' : ' (' . ucfirst($effectiveStream) . ' stream)';
$className  = $selectedClass ? (string) $selectedClass['name'] : '';
?>

<div class="page-header mb-4">
  <div>
    <nav aria-label="breadcrumb" class="mb-2">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= $base ?>/students">Students</a></li>
        <li class="breadcrumb-item active" aria-current="page">Delete by class</li>
      </ol>
    </nav>
    <h2 class="h4 mb-1 text-danger-emphasis"><i class="bi bi-exclamation-triangle-fill"></i> Delete students by class</h2>
    <p class="page-header__sub mb-0">Remove every student in one class &mdash; or just one stream of a Form 3 / Form 4 class &mdash; along with their marks, attendance, fees, and login account.</p>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-lg-8">

    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <form method="get" action="<?= $base ?>/students/delete-by-class" class="row g-3 align-items-end">

          <?php if ($isAdmin && !empty($schools)): ?>
          <div class="col-md-6">
            <label class="form-label fw-semibold" for="dbcSchool">School</label>
            <select name="school_id" id="dbcSchool" class="form-select" onchange="this.form.submit()">
              <option value="">— All schools —</option>
              <?php foreach ($schools as $sch): ?>
                <option value="<?= (int) $sch['id'] ?>" <?= $selectedSchoolId === (int) $sch['id'] ? 'selected' : '' ?>><?= View::e((string) $sch['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>

          <div class="col-md-6">
            <label class="form-label fw-semibold" for="dbcClass">Class</label>
            <select name="class_id" id="dbcClass" class="form-select" onchange="this.form.submit()">
              <option value="">— Choose a class —</option>
              <?php foreach ($classes as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= $selectedClass && (int) $selectedClass['id'] === (int) $c['id'] ? 'selected' : '' ?>>
                  <?= View::e(mb_strtoupper((string) ($c['name'] ?? ''), 'UTF-8')) ?><?= !empty($c['level']) ? ' · ' . View::e($c['level']) : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <?php if ($selectedClass && $isUpperLevel): ?>
          <div class="col-md-6">
            <label class="form-label fw-semibold" for="dbcStream">Stream</label>
            <select name="stream" id="dbcStream" class="form-select" onchange="this.form.submit()">
              <option value="all" <?= $stream === 'all' ? 'selected' : '' ?>>All streams (whole class)</option>
              <option value="science" <?= $stream === 'science' ? 'selected' : '' ?>>Science only</option>
              <option value="arts" <?= $stream === 'arts' ? 'selected' : '' ?>>Arts only</option>
            </select>
            <p class="form-text mb-0"><?= View::e($selectedClass['level']) ?> has a Science/Arts stream — narrow to one, or delete the whole class.</p>
          </div>
          <?php endif; ?>

        </form>
      </div>
    </div>

    <?php if ($selectedClass): ?>
    <div class="card border-danger border shadow-sm mb-4">
      <div class="card-body">
        <p class="mb-3">
          You are about to remove <strong><?= $studentCount ?></strong> student record<?= $studentCount === 1 ? '' : 's' ?>
          from <strong><?= View::e($className) ?></strong><?= View::e($streamNoun) ?>.
        </p>

        <?php if ($studentCount === 0): ?>
          <p class="alert alert-secondary mb-0">No students match this class<?= View::e($streamNoun) ?>. Nothing to do.</p>
        <?php else: ?>
          <ul class="small mb-4 text-secondary">
            <li>Attendance, grades/marks, term results, and legacy fees rows for these students are deleted (database cascades).</li>
            <li>Bursar <strong>student_fees</strong> and payment history tied to these students are deleted.</li>
            <li>These students' login accounts (role <strong>student</strong>) are removed &mdash; other classes' student logins are untouched.</li>
            <li><strong>This does not</strong> delete the class itself, other students in the school, subjects, staff, or structure fees.</li>
            <li>Passport photo files for these students are deleted from the server.</li>
          </ul>

          <form method="post"
                action="<?= $base ?>/students/delete-by-class"
                data-confirm="This permanently erases every student in <?= View::e($className) ?><?= View::e($streamNoun) ?>. Are you absolutely sure?">
            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
            <input type="hidden" name="class_id" value="<?= (int) $selectedClass['id'] ?>">
            <?php if ($isUpperLevel): ?>
              <input type="hidden" name="stream" value="<?= View::e($stream) ?>">
            <?php endif; ?>
            <div class="mb-3">
              <label for="confirm_name" class="form-label fw-semibold">Type the class name to confirm: <code><?= View::e(mb_strtoupper($className, 'UTF-8')) ?></code></label>
              <input id="confirm_name" name="confirm_name" type="text"
                     class="form-control font-monospace" autocomplete="off" spellcheck="false"
                     placeholder="<?= View::e(mb_strtoupper($className, 'UTF-8')) ?>" required maxlength="100">
              <p class="form-text mb-0">Must match the class name exactly (case is ignored).</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <button type="submit" class="btn btn-danger">
                <i class="bi bi-trash3"></i> Delete these students
              </button>
              <a href="<?= $base ?>/students" class="btn btn-outline-secondary">Cancel</a>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>
