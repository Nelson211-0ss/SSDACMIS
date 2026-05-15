<?php
use App\Core\View;
/** Financial snapshot only — operations KPIs live in _admin_kpi_ops.php inside At a glance. */
if (!$feesSnap) {
    return;
}
?>
<div class="section-block mb-2">
  <div class="section-block__head">
    <div>
      <h3 class="section-block__title"><i class="bi bi-cash-stack"></i> Financial snapshot</h3>
      <p class="section-block__sub">
        Fees for <?= View::e($feesYear) ?> &middot; <?= View::e($feesTerm) ?> (bursar module).
      </p>
    </div>
    <a href="<?= $base ?>/bursars" class="btn btn-sm btn-outline-primary">Manage bursars</a>
  </div>
</div>
<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="card admin-finance-card h-100">
      <div class="card-body">
        <div class="admin-finance-card__top">
          <div>
            <div class="admin-finance-card__label">Collected</div>
            <div class="admin-finance-card__amount">$<?= number_format($feesCollected, 2) ?></div>
            <div class="admin-finance-card__meta text-muted small">
              of $<?= number_format($feesExpected, 2) ?> expected
            </div>
          </div>
          <div class="admin-finance-card__pct"><?= $feesCollectedPct ?>%</div>
        </div>
        <div class="admin-finance-progress" role="progressbar"
             aria-valuenow="<?= (int) $feesCollectedPct ?>" aria-valuemin="0" aria-valuemax="100">
          <div class="admin-finance-progress__bar" style="width:<?= $feesCollectedPct ?>%"></div>
        </div>
        <div class="admin-finance-stats">
          <div class="admin-finance-stat">
            <span class="admin-finance-stat__n text-success"><?= number_format($feesPaidCount) ?></span>
            <span class="admin-finance-stat__l">Fully paid</span>
          </div>
          <div class="admin-finance-stat">
            <span class="admin-finance-stat__n text-warning"><?= number_format($feesPartialCount) ?></span>
            <span class="admin-finance-stat__l">Partial</span>
          </div>
          <div class="admin-finance-stat">
            <span class="admin-finance-stat__n text-danger"><?= number_format($feesUnpaidCount) ?></span>
            <span class="admin-finance-stat__l">Not paid</span>
          </div>
          <div class="admin-finance-stat">
            <span class="admin-finance-stat__n">$<?= number_format($feesOutstanding, 2) ?></span>
            <span class="admin-finance-stat__l">Outstanding</span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold mb-0"><i class="bi bi-receipt me-1"></i> Recent payments</span>
      </div>
      <?php if (empty($recentPaymentsAdmin)): ?>
        <div class="card-body">
          <div class="empty-state py-3">
            <i class="bi bi-receipt d-block"></i>
            <div>No payments recorded yet.</div>
          </div>
        </div>
      <?php else: ?>
        <ul class="recent-list recent-list--compact">
          <?php foreach ($recentPaymentsAdmin as $p): ?>
            <li class="recent-list__item">
              <div class="recent-list__body">
                <div class="recent-list__name">
                  <?= View::e(trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''))) ?>
                </div>
                <div class="recent-list__meta text-muted small">
                  <?= View::e($p['admission_no'] ?? '') ?>
                  &middot; <?= View::e(date('M j', strtotime($p['payment_date'] ?? 'now'))) ?>
                </div>
              </div>
              <div class="recent-list__date fw-semibold text-success">
                $<?= number_format((float) ($p['amount'] ?? 0), 2) ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</div>
