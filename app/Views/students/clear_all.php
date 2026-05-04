<?php
use App\Core\View;

$layout = 'app';
$title = 'Clear all students';
$studentCount = (int) ($studentCount ?? 0);
?>

<div class="page-header mb-4">
  <div>
    <nav aria-label="breadcrumb" class="mb-2">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= $base ?>/students">Students</a></li>
        <li class="breadcrumb-item active" aria-current="page">Clear all</li>
      </ol>
    </nav>
    <h2 class="h4 mb-1 text-danger-emphasis"><i class="bi bi-exclamation-triangle-fill"></i> Clear all students</h2>
    <p class="page-header__sub mb-0">This action permanently deletes every learner profile and linked school data.</p>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card border-danger border shadow-sm mb-4">
      <div class="card-body">
        <p class="mb-3">
          You are about to remove <strong><?= (int) $studentCount ?></strong> student record<?= $studentCount === 1 ? '' : 's' ?> from the database.
        </p>
        <ul class="small mb-4 text-secondary">
          <li>Attendance, grades/marks, term results, and legacy fees rows for students are deleted (database cascades).</li>
          <li>Bursar <strong>student_fees</strong> and payment history tied to learners are deleted.</li>
          <li>Every user account whose role is <strong>student</strong> is removed (they cannot sign in).</li>
          <li><strong>This does not</strong> delete classes, subjects, staff, structure fees, announcements, or admin/staff/HOD users.</li>
          <li>Passport photo files stored for students are deleted from the server.</li>
        </ul>

        <?php if ($studentCount === 0): ?>
          <p class="alert alert-secondary mb-0">There are no students in the database. Nothing to do.</p>
          <div class="mt-3">
            <a href="<?= $base ?>/students" class="btn btn-outline-secondary">Back to students</a>
          </div>
        <?php else: ?>
          <form method="post"
                action="<?= $base ?>/students/clear-all"
                data-confirm="This permanently erases EVERY student and their school data from the database. Are you absolutely sure?"
                class="mt-2">
            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
            <div class="mb-3">
              <label for="confirm_phrase" class="form-label fw-semibold">Type <?= View::e('DELETE ALL STUDENTS') ?> to confirm</label>
              <input id="confirm_phrase" name="confirm_phrase" type="text"
                     class="form-control font-monospace" autocomplete="off" spellcheck="false"
                     placeholder="DELETE ALL STUDENTS" required maxlength="128">
              <p class="form-text mb-0">Letters must match exactly, including spacing (case is ignored).</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <button type="submit" class="btn btn-danger">
                <i class="bi bi-trash3"></i> Erase all student data
              </button>
              <a href="<?= $base ?>/students" class="btn btn-outline-secondary">Cancel</a>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
