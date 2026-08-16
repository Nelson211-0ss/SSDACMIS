<?php
use App\Core\View;
use App\Services\FeesService;

$layout = 'app';
$fullName = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
$title    = $fullName;

$section  = strtolower((string) ($student['section'] ?? 'day'));
$stream   = strtolower((string) ($student['stream']  ?? 'none'));
$teacherName = trim(($student['teacher_first'] ?? '') . ' ' . ($student['teacher_last'] ?? ''));

$activeTotal   = (float) ($activeBill['total_amount'] ?? 0);
$activePaid    = (float) ($activeBill['paid_amount']  ?? 0);
$activeBalance = max(0.0, $activeTotal - $activePaid);
$activeStatus  = (string) ($activeBill['status'] ?? 'not_paid');
?>

<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-3">
  <div class="d-flex align-items-center gap-3">
    <?php
      $av_photo = $student['photo_path'] ?? '';
      $av_first = $student['first_name'] ?? '';
      $av_last  = $student['last_name']  ?? '';
      $av_size  = 72;
      include dirname(__DIR__) . '/_partials/student_avatar.php';
    ?>
    <div>
      <h4 class="mb-1"><i class="bi bi-person-vcard"></i> <?= View::e($fullName) ?></h4>
      <p class="text-muted small mb-0">
        <span class="badge-soft font-monospace"><?= View::e($student['admission_no'] ?? '') ?></span>
        &middot; <?= View::e($student['level'] ?? '—') ?><?= $student['class_name'] ? ' (' . View::e($student['class_name']) . ')' : '' ?>
        &middot; <span class="badge <?= $section === 'boarding' ? 'bg-info-subtle text-info-emphasis' : 'bg-light text-secondary border' ?>"><?= View::studentEnumUpper('section', $section) ?></span>
        <?php if ($stream !== 'none'): ?>
          &middot; <span class="badge bg-warning-subtle text-warning-emphasis"><?= View::studentEnumUpper('stream', $stream) ?></span>
        <?php endif; ?>
      </p>
      <?php if (!empty($student['guardian_name']) || !empty($student['guardian_phone'])): ?>
        <p class="text-muted small mb-0 mt-1">
          <i class="bi bi-people"></i>
          <?= View::e($student['guardian_name'] ?: '—') ?>
          <?php if (!empty($student['guardian_phone'])): ?>
            &middot; <span class="font-monospace"><?= View::e($student['guardian_phone']) ?></span>
          <?php endif; ?>
        </p>
      <?php endif; ?>
    </div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/students"><i class="bi bi-arrow-left"></i> Back to students</a>
    <?php if (in_array($auth['role'] ?? '', ['admin', 'school_admin'], true)): ?>
      <a class="btn btn-outline-primary btn-sm" href="<?= $base ?>/students/<?= (int) $student['id'] ?>/edit"><i class="bi bi-pencil"></i> Edit</a>
    <?php endif; ?>
    <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/students/<?= (int) $student['id'] ?>/admission-letter" data-inline-print><i class="bi bi-envelope-paper"></i> Admission letter</a>
    <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/students/<?= (int) $student['id'] ?>/id-card" data-inline-print><i class="bi bi-person-vcard"></i> Print ID card</a>
  </div>
</div>

<ul class="nav nav-tabs mb-3" id="studentProfileTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="tab-overview-btn" data-bs-toggle="tab" data-bs-target="#tab-overview" type="button" role="tab" aria-controls="tab-overview" aria-selected="true">
      <i class="bi bi-person-lines-fill"></i> Overview
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-finance-btn" data-bs-toggle="tab" data-bs-target="#tab-finance" type="button" role="tab" aria-controls="tab-finance" aria-selected="false">
      <i class="bi bi-cash-coin"></i> Finance
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-results-btn" data-bs-toggle="tab" data-bs-target="#tab-results" type="button" role="tab" aria-controls="tab-results" aria-selected="false">
      <i class="bi bi-graph-up-arrow"></i> Results
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-attendance-btn" data-bs-toggle="tab" data-bs-target="#tab-attendance" type="button" role="tab" aria-controls="tab-attendance" aria-selected="false">
      <i class="bi bi-calendar-check"></i> Attendance
    </button>
  </li>
</ul>

<div class="tab-content" id="studentProfileTabContent">

  <!-- ============================== Overview ============================== -->
  <div class="tab-pane fade show active" id="tab-overview" role="tabpanel" aria-labelledby="tab-overview-btn">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white d-flex align-items-center">
        <span class="card-header-icon card-header-icon--blue me-2" aria-hidden="true"><i class="bi bi-person-lines-fill"></i></span>
        <strong class="mb-0">Personal &amp; enrollment details</strong>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <div class="text-muted small">Date of birth</div>
            <div class="fw-semibold"><?= !empty($student['dob']) ? View::e(date('d M Y', strtotime((string) $student['dob']))) : '—' ?></div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Gender</div>
            <div class="fw-semibold"><?= View::studentEnumUpper('gender', $student['gender'] ?? '') ?></div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Admitted on</div>
            <div class="fw-semibold"><?= !empty($student['created_at']) ? View::e(date('d M Y', strtotime((string) $student['created_at']))) : '—' ?></div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Class teacher</div>
            <div class="fw-semibold"><?= $teacherName !== '' ? View::e($teacherName) : '—' ?></div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Guardian</div>
            <div class="fw-semibold"><?= View::e($student['guardian_name'] ?: '—') ?></div>
          </div>
          <div class="col-md-4">
            <div class="text-muted small">Guardian phone</div>
            <div class="fw-semibold font-monospace"><?= View::e($student['guardian_phone'] ?: '—') ?></div>
          </div>
          <div class="col-12">
            <div class="text-muted small">Address</div>
            <div class="fw-semibold"><?= !empty($student['address']) ? nl2br(View::e($student['address'])) : '—' ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ============================== Finance ============================== -->
  <div class="tab-pane fade" id="tab-finance" role="tabpanel" aria-labelledby="tab-finance-btn">
    <div class="row g-2 mb-3">
      <?php foreach ($termBills as $tb):
        $tTotal = (float) $tb['total_amount'];
        $tPaid  = (float) $tb['paid_amount'];
        $tBal   = max(0.0, $tTotal - $tPaid);
        $isActive = (string) $tb['term'] === $feeTerm;
      ?>
        <div class="col-md-4">
          <div class="card border-0 shadow-sm h-100 <?= $isActive ? 'border-primary border-2' : '' ?>">
            <div class="card-body py-3">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <strong>
                  <?= View::e($tb['term']) ?>
                  <?php if ($isActive): ?><span class="badge bg-primary ms-1">active</span><?php endif; ?>
                </strong>
                <span class="badge <?= FeesService::statusBadgeClass((string) $tb['status']) ?>"><?= View::e(FeesService::statusLabel((string) $tb['status'])) ?></span>
              </div>
              <div class="d-flex justify-content-between small">
                <span class="text-muted">Term fees</span>
                <span class="fw-semibold"><?= number_format($tTotal, 2) ?></span>
              </div>
              <div class="d-flex justify-content-between small">
                <span class="text-muted">Paid</span>
                <span class="text-success fw-semibold"><?= number_format($tPaid, 2) ?></span>
              </div>
              <div class="d-flex justify-content-between small">
                <span class="text-muted">Balance</span>
                <span class="fw-semibold text-<?= $tBal > 0 ? 'danger' : 'success' ?>"><?= number_format($tBal, 2) ?></span>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body py-3">
          <div class="text-muted small">Active term status</div>
          <div class="h5 mb-0"><span class="badge <?= FeesService::statusBadgeClass($activeStatus) ?>"><?= View::e(FeesService::statusLabel($activeStatus)) ?></span></div>
        </div></div>
      </div>
      <div class="col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body py-3">
          <div class="text-muted small"><?= View::e($feeTerm) ?> fees</div>
          <div class="h5 mb-0"><?= number_format($activeTotal, 2) ?></div>
        </div></div>
      </div>
      <div class="col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body py-3">
          <div class="text-muted small">Paid</div>
          <div class="h5 mb-0 text-success"><?= number_format($activePaid, 2) ?></div>
        </div></div>
      </div>
      <div class="col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body py-3">
          <div class="text-muted small">Balance</div>
          <div class="h5 mb-0 text-<?= $activeBalance > 0 ? 'danger' : 'success' ?>"><?= number_format($activeBalance, 2) ?></div>
        </div></div>
      </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white d-flex align-items-center justify-content-between flex-wrap gap-2">
        <span><span class="card-header-icon card-header-icon--blue me-2" aria-hidden="true"><i class="bi bi-clock-history"></i></span><strong class="mb-0">Transaction history</strong></span>
        <a class="btn btn-sm btn-primary" href="<?= $base ?>/bursar/students/<?= (int) $student['id'] ?>"><i class="bi bi-cash-coin"></i> Go to Fees Management</a>
      </div>
      <div class="table-responsive">
        <table class="table mb-0 align-middle">
          <thead class="table-light">
            <tr><th>Date</th><th>Period</th><th>Receipt No.</th><th class="text-end">Amount</th><th>Entered By</th><th>Notes</th></tr>
          </thead>
          <tbody>
            <?php if (empty($payments)): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">No payments yet.</td></tr>
            <?php else: foreach ($payments as $p): ?>
              <tr>
                <td><?= View::e(date('Y-m-d', strtotime((string) $p['payment_date']))) ?></td>
                <td>
                  <span class="small text-muted"><?= View::e($p['academic_year'] ?? $feeYear) ?></span>
                  <?php if (!empty($p['term'])): ?><span class="badge bg-primary-subtle text-primary-emphasis ms-1"><?= View::e($p['term']) ?></span><?php endif; ?>
                </td>
                <td><code class="small"><?= View::e($p['receipt_no']) ?></code></td>
                <td class="text-end fw-semibold text-success"><?= number_format((float) $p['amount'], 2) ?></td>
                <td><?= View::e($p['bursar_name'] ?? '—') ?></td>
                <td class="text-muted small"><?= View::e($p['notes'] ?? '') ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ============================== Results ============================== -->
  <div class="tab-pane fade" id="tab-results" role="tabpanel" aria-labelledby="tab-results-btn">
    <form method="get" action="<?= $base ?>/students/<?= (int) $student['id'] ?>" class="row g-2 align-items-end mb-3">
      <input type="hidden" name="tab" value="results">
      <div class="col-6 col-md-3">
        <label class="form-label small mb-1">Academic year</label>
        <input type="text" name="year" value="<?= View::e($resultsYear) ?>" class="form-control form-control-sm" placeholder="2025/2026">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small mb-1">Term</label>
        <select name="term" class="form-select form-select-sm">
          <?php foreach (['Term 1', 'Term 2', 'Term 3'] as $t): ?>
            <option value="<?= View::e($t) ?>" <?= $resultsTerm === $t ? 'selected' : '' ?>><?= View::e($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small mb-1">Assessment</label>
        <select name="stage" class="form-select form-select-sm">
          <?php foreach (($stages ?? []) as $key => $label): ?>
            <option value="<?= View::e((string) $key) ?>" <?= ($resultsStage ?? '') === $key ? 'selected' : '' ?>><?= View::e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> View period</button>
      </div>
      <div class="col-6 col-md-3 text-md-end">
        <a class="btn btn-outline-secondary btn-sm"
           href="<?= $base ?>/reports/student/<?= (int) $student['id'] ?>?year=<?= rawurlencode($resultsYear) ?>&term=<?= rawurlencode($resultsTerm) ?>&stage=<?= rawurlencode($resultsStage ?? 'endterm') ?>">
          <i class="bi bi-file-earmark-text"></i> Full report card
        </a>
      </div>
    </form>

    <?php
      $year = $resultsYear;
      $term = $resultsTerm;
      $stage = $resultsStage ?? 'endterm';
      $schoolName  = \App\Core\SchoolIdentity::name();
      $schoolMotto = \App\Core\SchoolIdentity::motto();
      $schoolLogo  = \App\Core\SchoolIdentity::logoUrl();
      include dirname(__DIR__) . '/reports/_student_card.php';
    ?>
  </div>

  <!-- ============================== Attendance ============================== -->
  <div class="tab-pane fade" id="tab-attendance" role="tabpanel" aria-labelledby="tab-attendance-btn">
    <div class="row g-3">
      <div class="col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body py-3">
          <div class="text-muted small">Present</div>
          <div class="h5 mb-0 text-success"><?= (int) $attCounts['present'] ?></div>
        </div></div>
      </div>
      <div class="col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body py-3">
          <div class="text-muted small">Absent</div>
          <div class="h5 mb-0 text-danger"><?= (int) $attCounts['absent'] ?></div>
        </div></div>
      </div>
      <div class="col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body py-3">
          <div class="text-muted small">Late</div>
          <div class="h5 mb-0 text-warning"><?= (int) $attCounts['late'] ?></div>
        </div></div>
      </div>
      <div class="col-md-3">
        <div class="card border-0 shadow-sm"><div class="card-body py-3">
          <div class="text-muted small">Attendance rate</div>
          <div class="h5 mb-0"><?= $attRate !== null ? $attRate . '%' : '—' ?></div>
        </div></div>
      </div>
    </div>
    <?php if ($attTotal === 0): ?>
      <div class="alert alert-secondary mt-3 mb-0">No attendance records for this student yet.</div>
    <?php endif; ?>
  </div>

</div>

<script>
  // The Results filter reloads the page via GET — re-open that tab afterwards
  // instead of defaulting back to Overview.
  (function () {
    var params = new URLSearchParams(window.location.search);
    if (params.get('tab') !== 'results') return;
    var btn = document.getElementById('tab-results-btn');
    if (btn && window.bootstrap) new bootstrap.Tab(btn).show();
  })();
</script>
