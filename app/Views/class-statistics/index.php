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

/** A class is flagged when one gender makes up this share or more. */
$imbalanceAt = 65.0;

$boysPct  = (float) ($totals['boys_pct'] ?? 0);
$girlsPct = (float) ($totals['girls_pct'] ?? 0);
$total    = (int) ($totals['total'] ?? 0);

$populated = array_values(array_filter($classes, static fn ($c) => $c['total'] > 0));
$largest   = null;
$balanced  = null;
foreach ($populated as $c) {
    if ($largest === null || $c['total'] > $largest['total']) {
        $largest = $c;
    }
    $gap = abs((float) $c['boys_pct'] - (float) $c['girls_pct']);
    if ($balanced === null || $gap < $balanced['gap']) {
        $balanced = $c + ['gap' => $gap];
    }
}

$pageTitle    = 'Class Statistics';
$pageSubtitle = 'Boys and girls in each class, and their share of the class.';
$pageIcon     = 'bi-pie-chart';
$pageActionsHtml = '<button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">'
    . '<i class="bi bi-printer"></i> Print</button>';
include dirname(__DIR__) . '/_partials/app_page_header.php';
?>
<div class="cstats">

  <?php if ($total === 0): ?>
    <div class="app-panel cstats-empty">
      <i class="bi bi-people" aria-hidden="true"></i>
      <p class="mb-0">No students are enrolled yet. Statistics appear here once students are added to classes.</p>
    </div>
  <?php else: ?>

  <!-- School-wide overview -->
  <section class="cstats-overview">
    <div class="app-panel cstats-donut-card">
      <div class="cstats-donut"
           style="--boys: <?= $boysPct ?>; --girls: <?= $girlsPct ?>;"
           role="img"
           aria-label="<?= View::e('Boys ' . $pct($boysPct) . ', girls ' . $pct($girlsPct)) ?>">
        <div class="cstats-donut__hole">
          <span class="cstats-donut__num"><?= number_format($total) ?></span>
          <span class="cstats-donut__label">students</span>
        </div>
      </div>
      <ul class="cstats-legend">
        <li>
          <span class="cstats-dot cstats-dot--boys"></span>
          <span class="cstats-legend__name">Boys</span>
          <strong><?= number_format((int) $totals['boys']) ?></strong>
          <span class="cstats-legend__pct cstats-text-boys"><?= $pct($totals['boys_pct']) ?></span>
        </li>
        <li>
          <span class="cstats-dot cstats-dot--girls"></span>
          <span class="cstats-legend__name">Girls</span>
          <strong><?= number_format((int) $totals['girls']) ?></strong>
          <span class="cstats-legend__pct cstats-text-girls"><?= $pct($totals['girls_pct']) ?></span>
        </li>
        <?php if ($hasOther): ?>
          <li>
            <span class="cstats-dot cstats-dot--other"></span>
            <span class="cstats-legend__name">Other</span>
            <strong><?= number_format((int) $totals['other']) ?></strong>
            <span class="cstats-legend__pct"><?= $pct($totals['other_pct']) ?></span>
          </li>
        <?php endif; ?>
      </ul>
    </div>

    <div class="cstats-tiles">
      <div class="cstats-tile cstats-tile--boys">
        <i class="bi bi-gender-male" aria-hidden="true"></i>
        <div>
          <div class="cstats-tile__label">Boys</div>
          <div class="cstats-tile__value"><?= number_format((int) $totals['boys']) ?></div>
          <div class="cstats-tile__sub"><?= $pct($totals['boys_pct']) ?> of the school</div>
        </div>
      </div>
      <div class="cstats-tile cstats-tile--girls">
        <i class="bi bi-gender-female" aria-hidden="true"></i>
        <div>
          <div class="cstats-tile__label">Girls</div>
          <div class="cstats-tile__value"><?= number_format((int) $totals['girls']) ?></div>
          <div class="cstats-tile__sub"><?= $pct($totals['girls_pct']) ?> of the school</div>
        </div>
      </div>
      <div class="cstats-tile">
        <i class="bi bi-arrow-left-right" aria-hidden="true"></i>
        <div>
          <div class="cstats-tile__label">Boys to girls</div>
          <div class="cstats-tile__value">
            <?= (int) $totals['girls'] > 0
                ? number_format((int) $totals['boys'] / (int) $totals['girls'], 2) . ' : 1'
                : '–' ?>
          </div>
          <div class="cstats-tile__sub">boys for every girl</div>
        </div>
      </div>
      <div class="cstats-tile">
        <i class="bi bi-grid" aria-hidden="true"></i>
        <div>
          <div class="cstats-tile__label">Classes</div>
          <div class="cstats-tile__value"><?= count($classes) ?></div>
          <div class="cstats-tile__sub">
            <?= $largest ? 'Largest: ' . View::e((string) $largest['name']) . ' (' . (int) $largest['total'] . ')' : 'No students placed' ?>
          </div>
        </div>
      </div>
      <?php if ($balanced): ?>
        <div class="cstats-tile cstats-tile--wide">
          <i class="bi bi-check2-circle" aria-hidden="true"></i>
          <div>
            <div class="cstats-tile__label">Most balanced class</div>
            <div class="cstats-tile__value cstats-tile__value--sm"><?= View::e((string) $balanced['name']) ?></div>
            <div class="cstats-tile__sub">
              <?= $pct($balanced['boys_pct']) ?> boys, <?= $pct($balanced['girls_pct']) ?> girls
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- One card per class -->
  <h3 class="cstats-heading">By class</h3>
  <section class="cstats-grid">
    <?php foreach (array_merge($classes, ($unassigned && $unassigned['total'] > 0) ? [$unassigned] : []) as $c):
        $cTotal = (int) $c['total'];
        $flag = null;
        if ($cTotal > 0 && (float) $c['boys_pct'] >= $imbalanceAt) {
            $flag = ['Mostly boys', 'boys'];
        } elseif ($cTotal > 0 && (float) $c['girls_pct'] >= $imbalanceAt) {
            $flag = ['Mostly girls', 'girls'];
        }
    ?>
      <article class="cstats-card<?= $cTotal === 0 ? ' cstats-card--empty' : '' ?>">
        <header class="cstats-card__head">
          <div class="cstats-card__title">
            <h4><?= View::e((string) $c['name']) ?></h4>
            <?php if (!empty($c['level']) && $c['level'] !== $c['name']): ?>
              <span><?= View::e((string) $c['level']) ?></span>
            <?php endif; ?>
          </div>
          <div class="cstats-card__total">
            <strong><?= number_format($cTotal) ?></strong>
            <span><?= $cTotal === 1 ? 'student' : 'students' ?></span>
          </div>
        </header>

        <?php if ($cTotal === 0): ?>
          <p class="cstats-card__none">No students in this class yet.</p>
        <?php else: ?>
          <div class="cstats-bar" role="img"
               aria-label="<?= View::e('Boys ' . $pct($c['boys_pct']) . ', girls ' . $pct($c['girls_pct'])) ?>">
            <span class="cstats-bar__boys" style="width: <?= (float) $c['boys_pct'] ?>%"></span>
            <span class="cstats-bar__girls" style="width: <?= (float) $c['girls_pct'] ?>%"></span>
            <?php if ($c['other'] > 0): ?>
              <span class="cstats-bar__other" style="width: <?= (float) $c['other_pct'] ?>%"></span>
            <?php endif; ?>
          </div>

          <div class="cstats-card__split">
            <div>
              <span class="cstats-card__label"><i class="bi bi-gender-male cstats-text-boys"></i> Boys</span>
              <span class="cstats-card__count"><?= number_format((int) $c['boys']) ?></span>
              <span class="cstats-card__pct cstats-text-boys"><?= $pct($c['boys_pct']) ?></span>
            </div>
            <div class="text-end">
              <span class="cstats-card__label">Girls <i class="bi bi-gender-female cstats-text-girls"></i></span>
              <span class="cstats-card__count"><?= number_format((int) $c['girls']) ?></span>
              <span class="cstats-card__pct cstats-text-girls"><?= $pct($c['girls_pct']) ?></span>
            </div>
          </div>

          <?php if ($c['other'] > 0 || $flag): ?>
            <footer class="cstats-card__foot">
              <?php if ($flag): ?>
                <span class="cstats-flag cstats-flag--<?= $flag[1] ?>"><i class="bi bi-exclamation-circle"></i> <?= $flag[0] ?></span>
              <?php endif; ?>
              <?php if ($c['other'] > 0): ?>
                <span class="cstats-card__other"><?= (int) $c['other'] ?> other (<?= $pct($c['other_pct']) ?>)</span>
              <?php endif; ?>
            </footer>
          <?php endif; ?>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </section>

  <!-- Detailed table -->
  <h3 class="cstats-heading">Detailed table</h3>
  <div class="app-panel">
    <div class="sa-table-wrap">
      <table class="table sa-table cstats-table align-middle mb-0">
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
          </tr>
        </thead>
        <tbody>
          <?php foreach (array_merge($classes, ($unassigned && $unassigned['total'] > 0) ? [$unassigned] : []) as $r): ?>
            <tr>
              <td class="fw-medium"><?= View::e((string) $r['name']) ?></td>
              <td class="text-end"><?= number_format((int) $r['boys']) ?></td>
              <td class="text-end cstats-text-boys"><?= $pct($r['boys_pct']) ?></td>
              <td class="text-end"><?= number_format((int) $r['girls']) ?></td>
              <td class="text-end cstats-text-girls"><?= $pct($r['girls_pct']) ?></td>
              <?php if ($hasOther): ?>
                <td class="text-end"><?= number_format((int) $r['other']) ?></td>
                <td class="text-end text-muted"><?= $pct($r['other_pct']) ?></td>
              <?php endif; ?>
              <td class="text-end fw-semibold"><?= number_format((int) $r['total']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td>Whole school</td>
            <td class="text-end"><?= number_format((int) $totals['boys']) ?></td>
            <td class="text-end cstats-text-boys"><?= $pct($totals['boys_pct']) ?></td>
            <td class="text-end"><?= number_format((int) $totals['girls']) ?></td>
            <td class="text-end cstats-text-girls"><?= $pct($totals['girls_pct']) ?></td>
            <?php if ($hasOther): ?>
              <td class="text-end"><?= number_format((int) $totals['other']) ?></td>
              <td class="text-end"><?= $pct($totals['other_pct']) ?></td>
            <?php endif; ?>
            <td class="text-end"><?= number_format($total) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <?php endif; ?>
</div>
