<?php
use App\Core\View;
use App\Services\FeesService;
$layout = 'app';
$title  = 'Student Fees';

$totalRows = count($rows);
$totBilled = array_sum(array_map(fn($r) => (float)$r['total_amount'], $rows));
$totPaid   = array_sum(array_map(fn($r) => (float)$r['paid_amount'],  $rows));
$totBal    = max(0.0, $totBilled - $totPaid);
?>

<style>
  .bursar-page .pretty-table .table { font-size: 0.86rem; }
  .bursar-page .pretty-table .table th,
  .bursar-page .pretty-table .table td { padding: 0.55rem 0.7rem; }
  .bursar-page .pretty-table thead th { position: sticky; top: 0; z-index: 1; }
</style>

<div class="bursar-page d-flex flex-column gap-3">

  <section class="dash-hero dash-hero--slim">
    <div class="dash-hero__content d-flex align-items-center gap-3">
      <span class="icon-chip icon-chip--blue d-none d-sm-inline-grid"><i class="bi bi-people-fill"></i></span>
      <div class="flex-grow-1" style="min-width:0;">
        <h2 class="dash-hero__title">Student Fees</h2>
        <p class="dash-hero__sub mb-0">
          <span class="dash-hero__inline"><i class="bi bi-cash-coin"></i> AY <?= View::e($year) ?></span>
          <span class="dash-hero__inline"><i class="bi bi-bookmark-star"></i> <?= View::e($term) ?></span>
          <span class="dash-hero__inline"><i class="bi bi-search"></i> Inline search & record payments</span>
        </p>
      </div>
    </div>
    <div class="dash-hero__actions">
      <a class="btn btn-outline-success btn-sm" href="<?= $base ?>/bursar/exam-permits<?= $levelFilter ? '?level=' . urlencode($levelFilter) : '' ?>">
        <i class="bi bi-shield-check"></i> Exam permits
      </a>
      <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/bursar/structure">
        <i class="bi bi-sliders"></i> Fees setup
      </a>
      <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/bursar/reports/export.csv?type=all<?= $levelFilter ? '&level=' . urlencode($levelFilter) : '' ?>">
        <i class="bi bi-filetype-csv"></i> Export CSV
      </a>
    </div>
  </section>

  <!-- Filter bar -->
  <form class="card border-0 shadow-sm" method="get" action="<?= $base ?>/bursar/students">
    <div class="card-body py-2 px-3 row g-2 align-items-end">
      <div class="col-md-5">
        <label class="form-label small fw-semibold mb-1">Search</label>
        <div class="input-group input-group-sm">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" name="q" class="form-control" placeholder="Name or admission no."
                 value="<?= View::e($q) ?>">
        </div>
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold mb-1">Class</label>
        <select name="level" class="form-select form-select-sm">
          <option value="">All classes</option>
          <?php foreach ($levels as $lvl): ?>
            <option value="<?= View::e($lvl) ?>" <?= $levelFilter === $lvl ? 'selected' : '' ?>><?= View::e($lvl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-semibold mb-1">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">Any</option>
          <option value="paid"     <?= $statusFilter === 'paid'     ? 'selected' : '' ?>>Paid</option>
          <option value="partial"  <?= $statusFilter === 'partial'  ? 'selected' : '' ?>>Partial</option>
          <option value="not_paid" <?= $statusFilter === 'not_paid' ? 'selected' : '' ?>>Not paid</option>
        </select>
      </div>
      <div class="col-md-2 d-grid gap-1">
        <button class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> Apply</button>
        <?php if ($q !== '' || $levelFilter !== '' || $statusFilter !== ''): ?>
          <a class="btn btn-link btn-sm py-0" href="<?= $base ?>/bursar/students">Clear filters</a>
        <?php endif; ?>
      </div>
    </div>
  </form>

  <!-- KPI strip -->
  <div class="row g-2">
    <div class="col-6 col-md-4">
      <div class="kpi-card kpi-card--xs h-100">
        <div class="kpi-card__icon kpi-card__icon--blue"><i class="bi bi-people"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Showing</div>
          <div class="kpi-card__value"><?= number_format($totalRows) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4">
      <div class="kpi-card kpi-card--xs h-100">
        <div class="kpi-card__icon kpi-card__icon--purple"><i class="bi bi-cash-stack"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Billed (filtered)</div>
          <div class="kpi-card__value"><?= number_format($totBilled, 2) ?></div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="kpi-card kpi-card--xs h-100">
        <div class="kpi-card__icon kpi-card__icon--<?= $totBal > 0 ? 'orange' : 'green' ?>">
          <i class="bi bi-<?= $totBal > 0 ? 'exclamation-circle' : 'check2-circle' ?>"></i>
        </div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Outstanding (filtered)</div>
          <div class="kpi-card__value text-<?= $totBal > 0 ? 'danger' : 'success' ?>"><?= number_format($totBal, 2) ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Roster -->
  <div class="card border-0 shadow-sm pretty-table">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Student</th>
            <th>Class</th>
            <th>Section</th>
            <th class="text-end">Term Fees</th>
            <th class="text-end">Paid</th>
            <th class="text-end">Balance</th>
            <th>Status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">
              No students match those filters.
            </td></tr>
          <?php else: foreach ($rows as $r):
            $bal = max(0.0, (float)$r['total_amount'] - (float)$r['paid_amount']);
            $statusLbl = FeesService::statusLabel((string)$r['status']);
            $statusCls = FeesService::statusBadgeClass((string)$r['status']);
          ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <?php
                    $av_photo = $r['photo_path'] ?? '';
                    $av_first = $r['first_name'] ?? '';
                    $av_last  = $r['last_name']  ?? '';
                    $av_size  = 44;
                    $av_shape = 'square';
                    include dirname(__DIR__) . '/_partials/student_avatar.php';
                  ?>
                  <div class="min-w-0">
                    <a href="<?= $base ?>/bursar/students/<?= (int) $r['id'] ?>" class="fw-semibold text-decoration-none">
                      <?= View::e(trim($r['first_name'] . ' ' . $r['last_name'])) ?>
                    </a>
                    <div class="small text-muted"><span class="badge-soft"><?= View::e($r['admission_no']) ?></span></div>
                  </div>
                </div>
              </td>
              <td>
                <span class="badge bg-primary-subtle text-primary-emphasis"><?= View::e($r['level'] ?? '—') ?></span>
                <div class="small text-muted"><?= View::e($r['class_name'] ?? '') ?></div>
              </td>
              <td>
                <?php if ($r['section'] === 'boarding'): ?>
                  <span class="badge bg-info-subtle text-info-emphasis"><i class="bi bi-house-fill"></i> Boarding</span>
                <?php else: ?>
                  <span class="badge bg-warning-subtle text-warning-emphasis"><i class="bi bi-sun"></i> Day</span>
                <?php endif; ?>
              </td>
              <td class="text-end"><?= number_format((float)$r['total_amount'], 2) ?></td>
              <td class="text-end text-success"><?= number_format((float)$r['paid_amount'], 2) ?></td>
              <td class="text-end text-<?= $bal > 0 ? 'danger' : 'success' ?>"><?= number_format($bal, 2) ?></td>
              <td><span class="badge <?= $statusCls ?>"><?= View::e($statusLbl) ?></span></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-secondary" href="<?= $base ?>/bursar/students/<?= (int)$r['id'] ?>"
                   title="Open student detail">
                  <i class="bi bi-receipt"></i>
                </a>
                <button class="btn btn-sm btn-primary"
                        type="button"
                        data-bs-toggle="modal"
                        data-bs-target="#payModal"
                        data-student-id="<?= (int)$r['id'] ?>"
                        data-student-name="<?= View::e(trim($r['first_name'] . ' ' . $r['last_name'])) ?>"
                        data-student-admission="<?= View::e($r['admission_no']) ?>"
                        data-student-photo="<?= !empty($r['photo_path']) ? View::e($base . '/' . ltrim((string)$r['photo_path'], '/')) : '' ?>"
                        data-student-initials="<?= View::e(mb_strtoupper(mb_substr((string)$r['first_name'], 0, 1, 'UTF-8') . mb_substr((string)$r['last_name'], 0, 1, 'UTF-8'), 'UTF-8')) ?>"
                        data-balance="<?= number_format($bal, 2, '.', '') ?>"
                        <?= $bal <= 0 && (float)$r['total_amount'] > 0 ? 'disabled title="Already paid in full"' : 'title="Record payment"' ?>>
                  <i class="bi bi-cash-coin"></i>
                </button>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/_payment_modal.php'; ?>

<!-- Record payment modal -->
<div class="modal fade" id="payModal" tabindex="-1" aria-labelledby="payModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" action="<?= $base ?>/bursar/payments" class="modal-content">
      <input type="hidden" name="_csrf" value="<?= $csrf ?>">
      <input type="hidden" name="student_id" id="payStudentId">
      <input type="hidden" name="academic_year" value="<?= View::e($year) ?>">
      <input type="hidden" name="term" value="<?= View::e($term) ?>">
      <?php
        // Send only the app-relative path — Controller::redirect() prepends
        // the base ($scriptDir) again, so passing the full REQUEST_URI would
        // double-prefix and 404.
        $reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/bursar/students', PHP_URL_PATH) ?? '/bursar/students';
        $reqQs   = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY) ?? '');
        $relReq  = ($base !== '' && str_starts_with($reqPath, $base)) ? substr($reqPath, strlen($base)) : $reqPath;
        if ($relReq === '' || !str_starts_with($relReq, '/bursar')) $relReq = '/bursar/students';
        if ($reqQs !== '') $relReq .= '?' . $reqQs;
      ?>
      <input type="hidden" name="return" value="<?= View::e($relReq) ?>">

      <div class="modal-header">
        <h5 class="modal-title" id="payModalTitle">
          <i class="bi bi-receipt-cutoff"></i>
          Record payment
          <span class="badge bg-primary-subtle text-primary-emphasis ms-2">
            <?= View::e($year) ?> · <?= View::e($term) ?>
          </span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3 p-2 rounded bg-body-secondary bg-opacity-25 border d-flex align-items-center gap-3">
          <img id="payStudentPhoto" src="" alt=""
               class="rounded-3 border border-2 d-none flex-shrink-0"
               style="width: 64px; height: 64px; object-fit: cover;">
          <span id="payStudentInitials"
                class="rounded-3 border border-2 bg-body-secondary text-secondary d-inline-flex align-items-center justify-content-center flex-shrink-0"
                style="width: 64px; height: 64px; font-size: 1.1rem; font-weight: 700;"
                aria-hidden="true">?</span>
          <div class="flex-grow-1">
            <div class="small text-muted">Student</div>
            <div class="fw-semibold" id="payStudentName">—</div>
            <div class="small text-muted">
              <span id="payStudentAdmission"></span>
              &middot; outstanding balance:
              <strong class="text-danger" id="payStudentBalance">0.00</strong>
            </div>
          </div>
        </div>

        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label small fw-semibold mb-1">Amount paid <span class="text-danger">*</span></label>
            <div class="input-group input-group-sm">
              <span class="input-group-text">$</span>
              <input type="number" min="0.01" step="0.01" name="amount" class="form-control text-end" required>
            </div>
            <div class="form-text small">Cannot exceed outstanding balance.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold mb-1">Date <span class="text-danger">*</span></label>
            <input type="date" name="payment_date" class="form-control form-control-sm" required
                   value="<?= date('Y-m-d') ?>">
          </div>
          <div class="col-md-12">
            <label class="form-label small fw-semibold mb-1">Receipt number <span class="text-danger">*</span></label>
            <input type="text" name="receipt_no" class="form-control form-control-sm"
                   required maxlength="50"
                   value="<?= View::e($nextReceipt) ?>">
            <div class="form-text small">Auto-generated, override only if you're using a pre-printed book.</div>
          </div>
          <div class="col-md-12">
            <label class="form-label small fw-semibold mb-1">Notes <span class="text-muted fw-normal small">(optional)</span></label>
            <input type="text" name="notes" class="form-control form-control-sm" maxlength="250"
                   placeholder="e.g. Cash · M-Pesa code · cheque #">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg"></i> Save payment</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  var modal = document.getElementById('payModal');
  if (!modal) return;
  // Note: the payment-success popup (_payment_modal.php) handles its own
  // auto-show — this listener is just for the "Record payment" entry modal.
  modal.addEventListener('show.bs.modal', function (ev) {
    var btn = ev.relatedTarget;
    if (!btn) return;
    document.getElementById('payStudentId').value         = btn.getAttribute('data-student-id') || '';
    document.getElementById('payStudentName').textContent = btn.getAttribute('data-student-name') || '—';
    document.getElementById('payStudentAdmission').textContent = btn.getAttribute('data-student-admission') || '';
    var bal = btn.getAttribute('data-balance') || '0';
    document.getElementById('payStudentBalance').textContent = bal;

    var photoUrl = btn.getAttribute('data-student-photo') || '';
    var initials = btn.getAttribute('data-student-initials') || '?';
    var photoEl  = document.getElementById('payStudentPhoto');
    var initEl   = document.getElementById('payStudentInitials');
    if (photoUrl) {
      photoEl.src = photoUrl;
      photoEl.classList.remove('d-none');
      initEl.classList.add('d-none');
    } else {
      photoEl.classList.add('d-none');
      photoEl.src = '';
      initEl.classList.remove('d-none');
      initEl.textContent = initials;
    }
    var amt = modal.querySelector('input[name="amount"]');
    if (amt) {
      amt.value = bal;
      amt.max   = bal;
      // Allow user to clear & retype; only the server enforces overpayment.
      setTimeout(function () { amt.focus(); amt.select(); }, 200);
    }
  });
})();
</script>
