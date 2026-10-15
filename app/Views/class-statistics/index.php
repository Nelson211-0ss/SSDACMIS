<?php
use App\Core\View;

$layout = 'app';
$title  = 'Class Statistics';

$classes    = $classes ?? [];
$unassigned = $unassigned ?? null;
$totals     = $totals ?? [];
$hasOther   = !empty($hasOther);

/** One decimal percentage, or a dash for an empty class. */
$pct = static fn ($v): string => $v === null ? '–' : number_format((float) $v, 1) . '%';

$pageTitle    = 'Class Statistics';
$pageSubtitle = 'Number of boys and girls in each class, and their share of the class.';
$pageIcon     = 'bi-pie-chart';
include dirname(__DIR__) . '/_partials/app_page_header.php';

$renderRow = static function (array $r, bool $strong = false) use ($pct, $hasOther): void {
    $total = (int) $r['total'];
    ?>
    <tr<?= $strong ? ' class="fw-semibold table-light"' : '' ?>>
      <td>
        <?= View::e((string) $r['name']) ?>
        <?php if (!empty($r['level']) && $r['level'] !== $r['name']): ?>
          <span class="text-muted small ms-1"><?= View::e((string) $r['level']) ?></span>
        <?php endif; ?>
      </td>
      <td class="text-end"><?= number_format((int) $r['boys']) ?></td>
      <td class="text-end text-muted"><?= $pct($r['boys_pct']) ?></td>
      <td class="text-end"><?= number_format((int) $r['girls']) ?></td>
      <td class="text-end text-muted"><?= $pct($r['girls_pct']) ?></td>
      <?php if ($hasOther): ?>
        <td class="text-end"><?= number_format((int) $r['other']) ?></td>
        <td class="text-end text-muted"><?= $pct($r['other_pct']) ?></td>
      <?php endif; ?>
      <td class="text-end"><?= number_format($total) ?></td>
      <td style="min-width: 140px">
        <?php if ($total > 0): ?>
          <div class="progress" style="height: 10px" role="img"
               aria-label="<?= View::e('Boys ' . $pct($r['boys_pct']) . ', girls ' . $pct($r['girls_pct'])) ?>">
            <div class="progress-bar bg-primary" style="width: <?= (float) $r['boys_pct'] ?>%"></div>
            <div class="progress-bar bg-danger" style="width: <?= (float) $r['girls_pct'] ?>%"></div>
            <?php if ($hasOther && $r['other'] > 0): ?>
              <div class="progress-bar bg-secondary" style="width: <?= (float) $r['other_pct'] ?>%"></div>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <span class="text-muted small">No students</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php
};
?>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100"><div class="card-body">
      <div class="text-muted small text-uppercase">Total students</div>
      <div class="fs-3 fw-bold"><?= number_format((int) ($totals['total'] ?? 0)) ?></div>
      <div class="small text-muted">across <?= count($classes) ?> classes</div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100"><div class="card-body">
      <div class="text-muted small text-uppercase">Boys</div>
      <div class="fs-3 fw-bold text-primary"><?= number_format((int) ($totals['boys'] ?? 0)) ?></div>
      <div class="small text-muted"><?= $pct($totals['boys_pct'] ?? null) ?> of students</div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100"><div class="card-body">
      <div class="text-muted small text-uppercase">Girls</div>
      <div class="fs-3 fw-bold text-danger"><?= number_format((int) ($totals['girls'] ?? 0)) ?></div>
      <div class="small text-muted"><?= $pct($totals['girls_pct'] ?? null) ?> of students</div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card border-0 shadow-sm h-100"><div class="card-body">
      <div class="text-muted small text-uppercase">Boys to girls</div>
      <div class="fs-3 fw-bold">
        <?= (int) ($totals['girls'] ?? 0) > 0
            ? number_format((int) $totals['boys'] / (int) $totals['girls'], 2) . ' : 1'
            : '–' ?>
      </div>
      <div class="small text-muted">boys per girl</div>
    </div></div>
  </div>
</div>

<div class="app-panel">
  <div class="sa-table-wrap">
    <table class="table table-hover sa-table align-middle mb-0">
      <thead>
        <tr>
          <th>Class</th>
          <th class="text-end">Boys</th>
          <th class="text-end">% Boys</th>
          <th class="text-end">Girls</th>
          <th class="text-end">% Girls</th>
          <?php if ($hasOther): ?>
            <th class="text-end">Other</th>
            <th class="text-end">% Other</th>
          <?php endif; ?>
          <th class="text-end">Total</th>
          <th>Split</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($classes === []): ?>
          <tr><td colspan="<?= $hasOther ? 9 : 7 ?>" class="text-center text-muted py-4">No classes have been created yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($classes as $c) { $renderRow($c); } ?>
        <?php if ($unassigned !== null && $unassigned['total'] > 0) { $renderRow($unassigned); } ?>
      </tbody>
      <?php if ($classes !== []): ?>
        <tfoot>
          <?php $renderRow($totals, true); ?>
        </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>
