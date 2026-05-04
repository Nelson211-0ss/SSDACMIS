<?php
use App\Core\View;
use App\Core\App;
use App\Core\Settings;
use App\Services\FeesService;

// Standalone printable receipt — no admin sidebar/topbar.
$schoolName  = Settings::get('school_name')  ?: App::config('app.name');
$schoolMotto = Settings::get('school_motto') ?? '';
$schoolLogo  = Settings::logoUrl();
$base        = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

$balance = max(0.0, (float) ($p['total_amount'] ?? 0) - (float) ($p['paid_amount'] ?? 0));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Receipt <?= View::e($p['receipt_no']) ?> · <?= View::e($schoolName) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { background: #f8fafc; font-family: 'Helvetica Neue', Arial, sans-serif; color: #1f2937; padding: 24px; }
    .receipt {
      max-width: 720px; margin: 0 auto; background: #fff;
      border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;
      box-shadow: 0 8px 30px rgba(15,23,42,0.08);
    }
    .receipt-hero {
      background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 60%, #3b82f6 100%);
      color: #fff; padding: 22px 28px; display: flex; align-items: center; gap: 16px;
    }
    .receipt-hero img { max-height: 56px; background: #fff; padding: 4px; border-radius: 8px; }
    .receipt-hero h1 { font-size: 1.3rem; margin: 0; font-weight: 700; }
    .receipt-hero .meta { font-size: 0.85rem; opacity: 0.92; }
    .receipt-body { padding: 24px 28px; }
    .receipt-amount {
      text-align: center; padding: 18px;
      background: #f0fdf4; border: 1px dashed #86efac; border-radius: 10px;
      margin-bottom: 18px;
    }
    .receipt-amount .label { color: #15803d; font-size: 0.75rem; letter-spacing: 0.08em; text-transform: uppercase; font-weight: 700; }
    .receipt-amount .value { font-size: 2.4rem; font-weight: 800; color: #14532d; line-height: 1.1; margin-top: 4px; }
    .kv { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #e5e7eb; font-size: 0.92rem; }
    .kv:last-of-type { border-bottom: 0; }
    .kv .lbl { color: #6b7280; }
    .kv .val { font-weight: 600; }
    .stamp {
      display: inline-block; padding: 6px 16px; border: 2px solid;
      border-radius: 999px; font-weight: 700; letter-spacing: 0.04em;
      transform: rotate(-6deg); margin-top: 10px;
    }
    .stamp--paid    { color: #16a34a; border-color: #16a34a; }
    .stamp--partial { color: #d97706; border-color: #d97706; }
    .signatures { display: flex; gap: 32px; margin-top: 36px; }
    .sig { flex: 1; text-align: center; }
    .sig-line { border-top: 1px solid #1f2937; padding-top: 6px; font-size: 0.85rem; color: #6b7280; }
    @media print {
      body { background: #fff; padding: 0; }
      .receipt { box-shadow: none; border: 0; max-width: 100%; }
      .no-print { display: none !important; }
    }
  </style>
</head>
<body>
  <div class="receipt">
    <div class="receipt-hero">
      <?php if ($schoolLogo): ?>
        <img src="<?= $base ?>/<?= View::e($schoolLogo) ?>" alt="">
      <?php else: ?>
        <i class="bi bi-mortarboard-fill" style="font-size: 2.4rem;"></i>
      <?php endif; ?>
      <div class="flex-grow-1">
        <h1><?= View::e($schoolName) ?></h1>
        <?php if ($schoolMotto !== ''): ?>
          <div class="meta"><em><?= View::e($schoolMotto) ?></em></div>
        <?php endif; ?>
        <div class="meta">Official Fees Receipt</div>
      </div>
      <div class="text-end">
        <div style="font-size: 0.75rem; opacity: 0.85;">RECEIPT</div>
        <div style="font-family: monospace; font-size: 1rem;">
          <?= View::e($p['receipt_no']) ?>
        </div>
      </div>
    </div>

    <div class="receipt-body">
      <div class="receipt-amount">
        <div class="label">Amount paid</div>
        <div class="value">$<?= number_format((float) $p['amount'], 2) ?></div>
      </div>

      <div class="row g-3 mb-3 align-items-start">
        <?php
          $rcptPhoto = trim((string) ($p['photo_path'] ?? ''));
          $rcptHasPhoto = $rcptPhoto !== '';
        ?>
        <?php if ($rcptHasPhoto): ?>
          <div class="col-md-2 text-center">
            <img src="<?= $base ?>/<?= View::e($rcptPhoto) ?>" alt=""
                 style="width: 100%; max-width: 90px; aspect-ratio: 3/4; object-fit: cover;
                        border: 1px solid #1f2937; border-radius: 4px; background: #f1f5f9;">
          </div>
        <?php endif; ?>
        <div class="<?= $rcptHasPhoto ? 'col-md-4' : 'col-md-6' ?>">
          <div class="kv"><span class="lbl">Student</span><span class="val"><?= View::e(trim($p['first_name'] . ' ' . $p['last_name'])) ?></span></div>
          <div class="kv"><span class="lbl">Admission no.</span><span class="val"><?= View::e($p['admission_no']) ?></span></div>
          <div class="kv"><span class="lbl">Class</span><span class="val"><?= View::e($p['level'] ?? '—') ?></span></div>
          <div class="kv"><span class="lbl">Section</span><span class="val"><?= ucfirst((string) $p['section']) ?></span></div>
        </div>
        <div class="col-md-6">
          <div class="kv"><span class="lbl">Payment date</span><span class="val"><?= View::e(date('M j, Y', strtotime($p['payment_date']))) ?></span></div>
          <div class="kv"><span class="lbl">Academic year</span><span class="val"><?= View::e($p['academic_year'] ?? '—') ?></span></div>
          <div class="kv"><span class="lbl">Term</span><span class="val"><?= View::e($p['term'] ?? '—') ?></span></div>
          <div class="kv"><span class="lbl">Recorded</span><span class="val"><?= View::e(date('M j, Y H:i', strtotime($p['created_at']))) ?></span></div>
          <div class="kv"><span class="lbl">Bursar</span><span class="val"><?= View::e($p['bursar_name'] ?? '—') ?></span></div>
          <?php if (!empty($p['notes'])): ?>
            <div class="kv"><span class="lbl">Notes</span><span class="val"><?= View::e($p['notes']) ?></span></div>
          <?php endif; ?>
        </div>
      </div>

      <hr>

      <div class="row g-3">
        <div class="col-6">
          <div class="kv"><span class="lbl"><?= View::e($p['term'] ?? 'Term') ?> fees</span><span class="val"><?= number_format((float) ($p['total_amount'] ?? 0), 2) ?></span></div>
          <div class="kv"><span class="lbl">Paid this term</span><span class="val text-success"><?= number_format((float) ($p['paid_amount'] ?? 0), 2) ?></span></div>
          <div class="kv"><span class="lbl">Term balance</span><span class="val text-<?= $balance > 0 ? 'danger' : 'success' ?>"><?= number_format($balance, 2) ?></span></div>
        </div>
        <div class="col-6 text-end">
          <?php $st = (string) ($p['status'] ?? 'partial'); ?>
          <span class="stamp stamp--<?= $st === 'paid' ? 'paid' : 'partial' ?>">
            <?= View::e(strtoupper(FeesService::statusLabel($st))) ?>
          </span>
        </div>
      </div>

      <div class="signatures">
        <div class="sig"><div class="sig-line">Bursar Signature</div></div>
        <div class="sig"><div class="sig-line">Parent / Student Signature</div></div>
      </div>

      <div class="text-center small text-muted mt-4">
        Thank you for your payment. Please keep this receipt for your records.
      </div>
    </div>
  </div>

  <div class="text-center mt-3 no-print">
    <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
    <button class="btn btn-outline-secondary" onclick="window.close()">Close</button>
  </div>

  <script>
    // Auto-open the print dialog so a paper receipt is one click away.
    // Skip when embedded in the dashboard's hidden iframe — the parent
    // page triggers print itself to keep the user inside the dashboard.
    window.addEventListener('load', function () {
      if (window.self !== window.top) return;
      setTimeout(window.print, 350);
    });
  </script>
</body>
</html>
