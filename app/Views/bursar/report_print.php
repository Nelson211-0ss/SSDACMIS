<?php
use App\Core\View;
use App\Core\App;
use App\Core\Settings;
use App\Services\FeesService;

// Standalone printable view — no admin sidebar/topbar.
$schoolName = Settings::get('school_name') ?: App::config('app.name');
$schoolLogo = Settings::logoUrl();
$base       = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

$isPaid = $type === 'paid';
$reportTitle = $isPaid ? 'Fully Paid Students' : 'Students With Outstanding Balances';

$totalBilled = array_sum(array_map(fn($r) => (float)$r['total_amount'], $rows));
$totalPaid   = array_sum(array_map(fn($r) => (float)$r['paid_amount'],  $rows));
$totalBal    = max(0.0, $totalBilled - $totalPaid);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= View::e($reportTitle) ?> · <?= View::e($schoolName) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { background: #fff; padding: 24px; font-family: 'Helvetica Neue', Arial, sans-serif; color: #1f2937; }
    .report-header { display: flex; align-items: center; gap: 16px; border-bottom: 2px solid #1f2937; padding-bottom: 12px; margin-bottom: 18px; }
    .report-header img { max-height: 64px; }
    .report-header h1 { font-size: 1.4rem; margin: 0; }
    .report-header .meta { color: #6b7280; font-size: 0.9rem; }
    table { font-size: 0.85rem; }
    .footer-totals td { font-weight: 700; }
    @media print {
      body { padding: 0; }
      .no-print { display: none !important; }
    }
  </style>
</head>
<body>
  <div class="report-header">
    <?php if ($schoolLogo): ?>
      <img src="<?= $base ?>/<?= View::e($schoolLogo) ?>" alt="">
    <?php endif; ?>
    <div class="flex-grow-1">
      <h1><?= View::e($schoolName) ?></h1>
      <div class="meta">
        <?= View::e($reportTitle) ?>
        &middot; AY <?= View::e($year) ?>
        &middot; <?= View::e($term) ?>
        <?php if ($level): ?> &middot; <?= View::e($level) ?><?php endif; ?>
        &middot; Generated <?= date('Y-m-d H:i') ?>
      </div>
    </div>
    <div class="no-print">
      <button class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
    </div>
  </div>

  <table class="table table-bordered align-middle">
    <thead class="table-light">
      <tr>
        <th style="width: 36px;">#</th>
        <th>Admission No.</th>
        <th>Student Name</th>
        <th>Class</th>
        <th>Section</th>
        <th class="text-end">Term Fees</th>
        <th class="text-end">Paid</th>
        <?php if (!$isPaid): ?><th class="text-end">Balance</th><?php endif; ?>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="<?= $isPaid ? 8 : 9 ?>" class="text-center text-muted py-4">No records.</td></tr>
      <?php else: foreach ($rows as $i => $r):
        $bal = max(0.0, (float)$r['total_amount'] - (float)$r['paid_amount']); ?>
        <tr>
          <td class="text-muted"><?= $i + 1 ?></td>
          <td><?= View::e($r['admission_no']) ?></td>
          <td><?= View::e(trim($r['first_name'] . ' ' . $r['last_name'])) ?></td>
          <td><?= View::e($r['level']) ?></td>
          <td><?= ucfirst((string) $r['section']) ?></td>
          <td class="text-end"><?= number_format((float)$r['total_amount'], 2) ?></td>
          <td class="text-end"><?= number_format((float)$r['paid_amount'], 2) ?></td>
          <?php if (!$isPaid): ?><td class="text-end"><?= number_format($bal, 2) ?></td><?php endif; ?>
          <td><?= View::e(FeesService::statusLabel((string)$r['status'])) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
    <?php if (!empty($rows)): ?>
    <tfoot class="footer-totals">
      <tr>
        <td colspan="5" class="text-end">Totals (<?= count($rows) ?> students)</td>
        <td class="text-end"><?= number_format($totalBilled, 2) ?></td>
        <td class="text-end"><?= number_format($totalPaid, 2) ?></td>
        <?php if (!$isPaid): ?><td class="text-end"><?= number_format($totalBal, 2) ?></td><?php endif; ?>
        <td>—</td>
      </tr>
    </tfoot>
    <?php endif; ?>
  </table>

  <div class="d-flex justify-content-between mt-5 pt-5 small">
    <div>
      <div style="border-top: 1px solid #1f2937; padding-top: 4px; min-width: 220px;">
        Bursar Signature
      </div>
    </div>
    <div>
      <div style="border-top: 1px solid #1f2937; padding-top: 4px; min-width: 220px;">
        Head Teacher / Principal
      </div>
    </div>
  </div>

  <script>
    // Optional auto-open print dialog. Disabled to give the user a chance
    // to review the report first.
  </script>
</body>
</html>
