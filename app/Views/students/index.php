<?php
use App\Core\View;

$layout = 'app';
$title = 'Students';
$studentsEmptyMessage = empty($students)
    ? (trim((string) ($search ?? '')) !== '' ? 'No matching students.' : 'No students yet.')
    : '';
?>
<div class="page-header mb-4">
  <div>
    <h2 class="h4 mb-1"><i class="bi bi-people text-primary"></i> Students</h2>
    <p class="page-header__sub mb-0">Search, edit, or register learners. Admission numbers follow each class prefix.</p>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <?php if (($auth['role'] ?? '') === 'admin'): ?>
      <a class="btn btn-outline-danger" href="<?= $base ?>/students/clear-all"
         title="Remove every learner, marks, attendance, fees, and student login accounts">
        <i class="bi bi-slash-circle"></i> Clear all students
      </a>
      <a class="btn btn-outline-secondary" href="<?= $base ?>/students/print" title="Print enrolled students (whole school or by class)">
        <i class="bi bi-printer"></i> Print roster
      </a>
      <a class="btn btn-outline-primary" href="<?= $base ?>/students/admission-letters"
         data-inline-print
         title="Print admission letters for every admitted student">
        <i class="bi bi-envelope-paper"></i> Admission letters
      </a>
    <?php endif; ?>
    <a class="btn btn-primary" href="<?= $base ?>/students/create"><i class="bi bi-plus-lg"></i> Add student</a>
  </div>
</div>

<div class="card border-0 shadow-sm mb-4">
  <div class="card-header py-3 d-flex align-items-center gap-2 border-0 border-bottom bg-transparent">
    <span class="card-header-icon card-header-icon--blue flex-shrink-0" aria-hidden="true"><i class="bi bi-search"></i></span>
    <div>
      <strong class="d-block">Find students</strong>
      <span class="small text-muted fw-normal">Results filter as you type — name or admission number.</span>
    </div>
  </div>
  <div class="card-body pt-3 pb-4">
    <form method="get" action="<?= $base ?>/students" id="students-search-form" class="row g-3 align-items-end"
          data-rows-url="<?= View::e($base . '/students/table-rows') ?>">
      <div class="col-12 col-lg-7 col-xl-6">
        <label class="form-label fw-semibold mb-2" for="student-search-q">Search</label>
        <div class="compact-search compact-search--wide">
          <div class="input-group">
            <span class="input-group-text py-2"><i class="bi bi-person-lines-fill"></i></span>
            <input id="student-search-q" type="search" name="q" value="<?= View::e($search ?? '') ?>"
                   class="form-control py-2 shadow-none"
                   placeholder="Name or admission no." autocomplete="off" spellcheck="false"
                   aria-busy="false">
            <span class="input-group-text border-start py-2 px-2 student-search-busy d-none" id="student-search-busy" aria-hidden="true">
              <span class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Loading</span></span>
            </span>
            <button type="submit" class="btn btn-primary px-3">Search</button>
          </div>
        </div>
        <p class="form-text mb-0 mt-2 small text-muted">The list updates as you type; use Search for an immediate refresh.</p>
        <noscript><p class="small text-warning mb-0 mt-2">Enable JavaScript for live filtering, or press Enter to search.</p></noscript>
      </div>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm overflow-hidden">
  <div class="card-header py-3 border-0 border-bottom bg-transparent d-flex align-items-center gap-2">
    <span class="card-header-icon card-header-icon--blue flex-shrink-0" aria-hidden="true"><i class="bi bi-list-ul"></i></span>
    <strong>All students</strong>
    <span class="text-muted small fw-normal ms-1">(newest registrations first)</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th scope="col" class="text-nowrap">Adm. no.</th>
          <th scope="col">Name</th>
          <th scope="col" class="d-none d-md-table-cell">Gender</th>
          <th scope="col">Class</th>
          <th scope="col" class="d-none d-lg-table-cell">Section</th>
          <th scope="col" class="d-none d-xl-table-cell">Stream</th>
          <th scope="col" class="d-none d-xl-table-cell">Guardian</th>
          <th scope="col" class="d-none d-xl-table-cell">Phone</th>
          <th scope="col" class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody id="students-table-body">
        <?php include __DIR__ . '/_tbody.php'; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="<?= View::asset($base, 'assets/js/students-index.js') ?>" defer></script>
