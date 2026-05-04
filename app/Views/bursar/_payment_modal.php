<?php
/**
 * Payment-success popup partial. Include this from any bursar view; it
 * renders nothing unless $_SESSION['_payment_success'] was set by
 * BursarController::recordPayment(). Reading it clears the slot, so the
 * modal pops exactly once per successful payment.
 *
 * Required vars from parent view: $base
 */
use App\Core\View;

$pay = $_SESSION['_payment_success'] ?? null;
unset($_SESSION['_payment_success']);
if (!$pay) return;
?>
<style>
/* Self-contained styling so the popup looks polished without touching
   the global stylesheet. Scoped via #paymentSuccessModal IDs/classes. */
#paymentSuccessModal .modal-content {
  border: 0;
  border-radius: 1rem;
  overflow: hidden;
  box-shadow: 0 25px 70px rgba(15, 23, 42, 0.25);
}
#paymentSuccessModal .ps-hero {
  background: linear-gradient(135deg, #16a34a 0%, #22c55e 60%, #4ade80 100%);
  color: #fff;
  padding: 2rem 1.5rem 1.25rem;
  text-align: center;
  position: relative;
}
#paymentSuccessModal .ps-check {
  width: 88px; height: 88px; margin: 0 auto 0.75rem;
  border-radius: 50%; background: rgba(255,255,255,0.18);
  display: grid; place-items: center;
  box-shadow: 0 0 0 8px rgba(255,255,255,0.10);
  animation: ps-pop 0.5s cubic-bezier(.2,.9,.3,1.4) both;
}
#paymentSuccessModal .ps-check i {
  font-size: 2.6rem;
  animation: ps-tick 0.6s ease-out 0.15s both;
}
#paymentSuccessModal .ps-title {
  font-size: 1.35rem; font-weight: 700; margin: 0; letter-spacing: -0.01em;
}
#paymentSuccessModal .ps-sub { opacity: 0.92; margin-top: 0.25rem; font-size: 0.9rem; }
#paymentSuccessModal .ps-amount {
  margin-top: 0.85rem;
  font-family: 'Inter', system-ui, sans-serif;
  font-size: 2.25rem; font-weight: 800; letter-spacing: -0.02em;
  line-height: 1.1;
}
#paymentSuccessModal .ps-amount small { font-size: 0.85rem; opacity: 0.85; font-weight: 500; }
#paymentSuccessModal .ps-body {
  padding: 1.25rem 1.5rem 0.5rem;
  background: #fff;
}
#paymentSuccessModal .ps-row {
  display: flex; justify-content: space-between; align-items: baseline;
  padding: 0.55rem 0; border-bottom: 1px dashed #e5e7eb;
  font-size: 0.92rem;
}
#paymentSuccessModal .ps-row:last-child { border-bottom: 0; }
#paymentSuccessModal .ps-row .ps-label { color: #6b7280; }
#paymentSuccessModal .ps-row .ps-val   { font-weight: 600; color: #1f2937; }
#paymentSuccessModal .ps-receipt {
  background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 0.65rem;
  padding: 0.6rem 0.85rem; margin: 0.85rem 0 0.25rem;
  display: flex; justify-content: space-between; align-items: center;
}
#paymentSuccessModal .ps-receipt code {
  font-size: 0.95rem; color: #0f172a; background: transparent; padding: 0;
}
#paymentSuccessModal .ps-balance--paid { color: #16a34a; }
#paymentSuccessModal .ps-balance--owe  { color: #dc2626; }
#paymentSuccessModal .ps-foot {
  padding: 0.85rem 1.5rem 1.25rem; background: #fff;
  display: flex; gap: 0.5rem; flex-wrap: wrap;
}
#paymentSuccessModal .ps-foot .btn { flex: 1 1 auto; min-width: 9rem; }
#paymentSuccessModal .ps-progress {
  height: 4px; background: rgba(255,255,255,0.35); position: relative; overflow: hidden;
}
#paymentSuccessModal .ps-progress::after {
  content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 100%;
  background: rgba(255,255,255,0.85);
  transform: scaleX(1); transform-origin: left;
  animation: ps-countdown 5s linear forwards;
}
#paymentSuccessModal.is-paused .ps-progress::after { animation-play-state: paused; }
@keyframes ps-pop  { 0% { transform: scale(0); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
@keyframes ps-tick { 0% { transform: scale(0.6); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
@keyframes ps-countdown { from { transform: scaleX(1); } to { transform: scaleX(0); } }
</style>

<div class="modal fade" id="paymentSuccessModal" tabindex="-1" aria-hidden="true" aria-labelledby="paymentSuccessTitle">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="ps-hero">
        <div class="ps-check"><i class="bi bi-check-lg"></i></div>
        <h2 class="ps-title" id="paymentSuccessTitle">Payment Recorded</h2>
        <p class="ps-sub">
          <?php if ($pay['fully_paid']): ?>
            <i class="bi bi-stars"></i> <?= View::e($pay['term'] ?? 'Term') ?> is now fully paid · Thank you!
          <?php else: ?>
            Saved to <?= View::e($pay['student_name'] ?: 'student') ?>'s account
            <?php if (!empty($pay['term'])): ?>
              for <?= View::e($pay['term']) ?>
            <?php endif; ?>
          <?php endif; ?>
        </p>
        <div class="ps-amount">
          <small>$</small><?= number_format((float) $pay['amount'], 2) ?>
        </div>
        <div class="ps-progress" aria-hidden="true"></div>
      </div>

      <div class="ps-body">
        <div class="d-flex align-items-center gap-3 mb-2">
          <?php
            $av_photo = $pay['photo_path'] ?? '';
            $av_first = $pay['first_name'] ?? '';
            $av_last  = $pay['last_name']  ?? '';
            $av_size  = 56;
            include dirname(__DIR__) . '/_partials/student_avatar.php';
          ?>
          <div class="flex-grow-1 min-w-0">
            <div class="fw-semibold text-truncate"><?= View::e($pay['student_name']) ?></div>
            <div class="small text-muted"><?= View::e($pay['admission_no']) ?></div>
          </div>
        </div>

        <div class="ps-receipt">
          <span class="text-muted small">Receipt no.</span>
          <code><?= View::e($pay['receipt_no']) ?></code>
        </div>

        <div class="ps-row">
          <span class="ps-label">Student</span>
          <span class="ps-val">
            <?= View::e($pay['student_name']) ?>
            <span class="text-muted small">(<?= View::e($pay['admission_no']) ?>)</span>
          </span>
        </div>
        <div class="ps-row">
          <span class="ps-label">Date</span>
          <span class="ps-val"><?= View::e(date('M j, Y', strtotime($pay['payment_date']))) ?></span>
        </div>
        <?php if (!empty($pay['term']) || !empty($pay['academic_year'])): ?>
        <div class="ps-row">
          <span class="ps-label">Period</span>
          <span class="ps-val">
            <?= View::e($pay['academic_year'] ?? '') ?>
            <?php if (!empty($pay['term'])): ?>
              <span class="badge bg-primary-subtle text-primary-emphasis ms-1"><?= View::e($pay['term']) ?></span>
            <?php endif; ?>
          </span>
        </div>
        <?php endif; ?>
        <div class="ps-row">
          <span class="ps-label">Term paid to date</span>
          <span class="ps-val"><?= number_format((float) $pay['paid_total'], 2) ?> / <?= number_format((float) $pay['fee_total'], 2) ?></span>
        </div>
        <div class="ps-row">
          <span class="ps-label">New term balance</span>
          <span class="ps-val ps-balance--<?= $pay['balance'] > 0 ? 'owe' : 'paid' ?>">
            <?= number_format((float) $pay['balance'], 2) ?>
          </span>
        </div>
      </div>

      <div class="ps-foot">
        <a class="btn btn-outline-secondary" data-inline-print
           href="<?= $base ?>/bursar/payments/<?= (int) $pay['payment_id'] ?>/receipt">
          <i class="bi bi-printer"></i> Print receipt
        </a>
        <button type="button" class="btn btn-success" data-bs-dismiss="modal" autofocus>
          <i class="bi bi-check2-circle"></i> Done
        </button>
      </div>
    </div>
  </div>
</div>

<script>
// Initialise the payment-success popup. The layout's bootstrap bundle is
// loaded BELOW the page content, so this script runs at parse time before
// `window.bootstrap` exists. We therefore (a) wait for DOMContentLoaded so
// the modal markup is in the DOM, and (b) poll briefly until the bootstrap
// bundle has finished loading. Without this, the modal silently never opens.
(function () {
  function whenReady(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn, { once: true });
    } else {
      fn();
    }
  }
  function whenBootstrap(fn) {
    if (window.bootstrap && window.bootstrap.Modal) { fn(); return; }
    var tries = 0;
    var poll = setInterval(function () {
      if ((window.bootstrap && window.bootstrap.Modal) || tries++ > 80) {
        clearInterval(poll);
        if (window.bootstrap && window.bootstrap.Modal) fn();
      }
    }, 50);
  }

  whenReady(function () {
    var el = document.getElementById('paymentSuccessModal');
    if (!el) return;
    whenBootstrap(function () {
      var modal = bootstrap.Modal.getOrCreateInstance(el, { backdrop: true, keyboard: true });
      modal.show();

      // Auto-dismiss after the progress bar finishes (5s) — pause on hover
      // so the bursar has time to read or click "Print receipt".
      var timer = null;
      function arm() {
        if (timer) clearTimeout(timer);
        timer = setTimeout(function () { modal.hide(); }, 5000);
      }
      el.addEventListener('shown.bs.modal', arm);
      el.addEventListener('mouseenter', function () {
        if (timer) clearTimeout(timer);
        el.classList.add('is-paused');
      });
      el.addEventListener('mouseleave', function () {
        el.classList.remove('is-paused');
        arm();
      });
      el.addEventListener('hidden.bs.modal', function () {
        if (timer) clearTimeout(timer);
      });
    });
  });
})();
</script>
