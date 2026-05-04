<?php
use App\Core\View;

$layout = 'app';
$title = 'Print student roster';
$showClassCol = empty($classId) || (int) $classId <= 0;
$total = count($students ?? []);
?>
<div class="student-roster-print results-print-area report-page--print-landscape">
  <div class="page-header student-roster-page-head d-print-none">
    <div>
      <h2 class="h4 mb-1"><i class="bi bi-printer text-primary"></i> Student roster</h2>
      <p class="page-header__sub mb-0">Print the official list of enrolled students — whole school or one class.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-outline-secondary" href="<?= $base ?>/students">
        <i class="bi bi-arrow-left"></i> Back
      </a>
      <button type="button" class="btn btn-primary" onclick="window.print()">
        <i class="bi bi-printer"></i> Print
      </button>
    </div>
  </div>

  <div class="card border-0 shadow-sm mb-4 student-roster-filter-card d-print-none">
    <div class="card-header py-3 d-flex align-items-center gap-2 border-0 border-bottom bg-transparent">
      <span class="card-header-icon card-header-icon--blue flex-shrink-0" aria-hidden="true"><i class="bi bi-funnel-fill"></i></span>
      <div>
        <strong class="d-block">Filter</strong>
        <span class="small text-muted fw-normal">Choose scope, then apply to refresh the preview below.</span>
      </div>
    </div>
    <div class="card-body pt-3 pb-4">
      <form method="get" action="<?= $base ?>/students/print" class="student-roster-filter-form">
        <label class="form-label fw-semibold mb-2 mb-lg-3" for="roster-class-filter">
          Who to include <span class="text-danger">*</span>
        </label>
        <div class="student-roster-filter-controls rounded-4 border shadow-sm">
          <div class="row g-3 g-lg-4 align-items-center">
            <div class="col-12 col-lg">
              <select id="roster-class-filter" name="class_id" class="form-select form-select-lg student-roster-filter-select">
                <option value="0" <?= empty($classId) ? 'selected' : '' ?>>Whole school — all enrolled students</option>
                <?php foreach (($classes ?? []) as $c): ?>
                  <option value="<?= (int) $c['id'] ?>" <?= (int) ($classId ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                    <?= View::e(($c['name'] ?? '') . (!empty($c['level']) ? ' · ' . $c['level'] : '')) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-lg-auto">
              <button type="submit" class="btn btn-primary btn-lg student-roster-filter-apply w-100">
                <i class="bi bi-arrow-clockwise" aria-hidden="true"></i>
                <span>Apply filter</span>
              </button>
            </div>
          </div>
        </div>
        <p class="form-text mb-0 mt-3 small">
          <i class="bi bi-shield-lock text-muted me-1"></i>
          Administrators only. Lists reflect students currently assigned in the system.
        </p>
      </form>
    </div>
  </div>

  <div class="results-table-panel student-roster-sheet border-0 shadow-sm">
    <div class="student-roster-brand pb-3 mb-3 border-bottom">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
          <div class="fw-bold fs-5 text-body"><?= View::e($schoolName ?? '') ?></div>
          <div class="text-secondary small text-uppercase letter-spacing-wide mt-1">Student enrollment roster</div>
        </div>
        <div class="student-roster-meta-pill text-center px-3 py-2 rounded-3 border bg-body-secondary bg-opacity-25">
          <div class="small text-muted text-uppercase fw-semibold" style="font-size: .65rem; letter-spacing: .06em;">Students</div>
          <div class="fs-4 fw-semibold lh-1 mt-1"><?= (int) $total ?></div>
        </div>
      </div>
      <div class="mt-3 pt-2 border-top border-opacity-50">
        <?php if (!empty($filterClass)): ?>
          <span class="badge bg-primary-subtle text-primary-emphasis rounded-pill px-3 py-2">
            <i class="bi bi-mortarboard me-1"></i><?= View::e($filterClass['name'] ?? '') ?>
            <?php if (!empty($filterClass['level'])): ?>
              <span class="opacity-75"><?= View::e($filterClass['level']) ?></span>
            <?php endif; ?>
          </span>
        <?php else: ?>
          <span class="badge bg-secondary-subtle text-secondary-emphasis rounded-pill px-3 py-2">
            <i class="bi bi-globe2 me-1"></i>Whole school
          </span>
        <?php endif; ?>
        <span class="text-muted small ms-2 d-none d-print-inline">Generated <?= View::e($printedAt ?? '') ?></span>
      </div>
      <div class="small text-muted mt-2 d-none d-print-block"><?= View::e($printedAt ?? '') ?></div>
    </div>

    <p class="small text-muted mb-3 d-print-none">
      <i class="bi bi-info-circle"></i> Preview matches what will print (landscape). Use <strong>Print</strong> above.
    </p>

    <?php if ($total === 0): ?>
      <div class="alert alert-info border-0 shadow-none d-print-none mb-0">
        <i class="bi bi-inbox me-2"></i>No students match this filter.
      </div>
      <p class="d-none d-print-block text-muted small mb-0">No students on file for this selection.</p>
    <?php else: ?>
      <div class="table-edge rounded-2 border overflow-hidden">
        <table class="table table-sm table-bordered align-middle results-density-table student-roster-table mb-0">
          <thead class="table-light">
            <tr>
              <th class="text-center rd-pos text-muted">#</th>
              <th scope="col">Admission no.</th>
              <th scope="col">Student name</th>
              <th class="text-center">Gender</th>
              <th class="text-center">DOB</th>
              <?php if ($showClassCol): ?>
                <th scope="col">Class</th>
              <?php endif; ?>
              <th class="text-center">Section</th>
              <th class="text-center">Stream</th>
              <th scope="col">Guardian</th>
              <th scope="col">Phone</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (array_values($students) as $i => $s):
              $dob = $s['dob'] ?? null;
              $dobStr = ($dob && $dob !== '0000-00-00') ? date('d M Y', strtotime((string) $dob)) : '—';
              $stream = (string) ($s['stream'] ?? 'none');
            ?>
              <tr>
                <td class="text-center text-muted rd-pos"><?= $i + 1 ?></td>
                <td class="font-monospace small"><?= View::e($s['admission_no'] ?? '') ?></td>
                <td class="fw-medium"><?= View::e(trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? ''))) ?></td>
                <td class="text-center"><span class="badge rounded-pill bg-light text-secondary border"><?= View::studentEnumUpper('gender', $s['gender'] ?? '') ?></span></td>
                <td class="text-center small font-monospace"><?= View::e($dobStr) ?></td>
                <?php if ($showClassCol): ?>
                  <td class="small">
                    <span class="d-block"><?= View::upper($s['class_name'] ?? '') ?: '—' ?></span>
                    <?php if (!empty($s['class_level'])): ?>
                      <span class="text-muted"><?= View::upper($s['class_level']) ?></span>
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
                <td class="text-center small"><?= View::studentEnumUpper('section', $s['section'] ?? '') ?></td>
                <td class="text-center small">
                  <?php if ($stream === 'none'): ?>
                    <?= View::studentEnumUpper('stream', $stream) ?>
                  <?php else: ?>
                    <span class="badge rounded-pill bg-light border"><?= View::studentEnumUpper('stream', $stream) ?></span>
                  <?php endif; ?>
                </td>
                <td class="small"><?= View::e($s['guardian_name'] ?: '—') ?></td>
                <td class="small font-monospace"><?= View::e($s['guardian_phone'] ?: '—') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
