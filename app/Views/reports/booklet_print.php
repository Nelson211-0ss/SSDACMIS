<?php
use App\Core\View;
use App\Core\SchoolIdentity;

/**
 * School-wide report card booklet — exactly one A4 sheet per student.
 *
 * Deliberately a STANDALONE document (no app shell), like the bulk
 * admission letters. Printing from inside the app shell means undoing a
 * flex/grid layout, a tinted page background and an overflow-clipped main
 * column with a wall of `!important` overrides, and any one of those
 * silently disables the browser's page-break handling. Here each card sits
 * in its own A4-sized sheet that breaks after itself, in a document with
 * nothing else in it — so "one student per sheet" is structural rather than
 * something a stray layout rule elsewhere can quietly undo.
 *
 * @var array<int, array{student: array, sheet: array, position: array}> $booklet
 */
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

$schoolName  = SchoolIdentity::name();
$schoolMotto = SchoolIdentity::motto();
$schoolLogo  = SchoolIdentity::logoUrl();

// Standalone documents render without the app layout, so the portal prefix
// the layout normally supplies has to be derived from the URL — otherwise
// "Back" sends an HOD out of their own portal.
$reqPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$portalPrefix = str_contains($reqPath, '/hod/reports') ? '/hod' : '';

$n = count($booklet ?? []);
$qs = static function (array $over = []) use ($year, $term, $stage, $classId): string {
    return http_build_query(array_merge([
        'year' => $year, 'term' => $term, 'stage' => $stage, 'class_id' => $classId,
    ], $over));
};
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Report Cards · <?= View::e($schoolName) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="<?= View::asset($base, 'assets/css/app.css') ?>" rel="stylesheet">
  <style>
    /* Loaded AFTER app.css so this @page wins: the sheets below are sized
       to the full page and carry their own margin as padding. */
    @page { size: A4 portrait; margin: 0; }

    html, body { margin: 0; padding: 0; background: #eef1f5; }
    body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }

    .rc-toolbar {
      position: sticky; top: 0; z-index: 20;
      display: flex; flex-wrap: wrap; gap: .5rem; align-items: end;
      padding: .75rem 1rem;
      background: #fff; border-bottom: 1px solid #d7dde5;
    }
    .rc-toolbar__title { font-weight: 700; margin: 0 auto 0 0; }
    .rc-toolbar__title small { display: block; font-weight: 400; color: #64748b; }

    /* One sheet = one printed page.
       `min-height` rather than a fixed height, and overflow left visible:
       the card is compacted by app.css's print rules to about 893px against
       ~1040px of usable sheet, so it fits with room to spare. Clipping an
       oversized card would silently drop a student's subjects, and scaling
       it in JS can't work here — the fit would have to be measured on
       screen, where app.css has NOT yet applied its print metrics, so every
       card would be shrunk to fit a height it never has on paper. */
    .rc-sheet {
      width: 210mm;
      min-height: 297mm;
      padding: 11mm 12mm;
      margin: 0 auto 14px;
      background: #fff;
      position: relative;
      box-shadow: 0 6px 22px rgba(15, 23, 42, .10);
      break-after: page;
      page-break-after: always;
    }
    .rc-sheet:last-of-type { break-after: auto; page-break-after: auto; }
    .rc-sheet .report-page { border: 0; box-shadow: none; padding: 0; max-width: none; }

    /* Guarantee one sheet per student even for an unusually large
       curriculum. `zoom` (not `transform`) because it reflows, so the card
       genuinely occupies less height rather than being painted smaller over
       the same box. The factor is computed server-side from the subject
       count — measuring in JS cannot work here, since on screen app.css has
       not applied its print metrics and every card would be shrunk to fit a
       height it never has on paper. */
    .rc-sheet .report-page--student { zoom: var(--rc-zoom, 1); }

    .rc-empty { max-width: 40rem; margin: 3rem auto; padding: 1.25rem 1.5rem; background: #fff; border-radius: .5rem; }

    @media print {
      html, body { background: #fff; }
      .no-print { display: none !important; }
      .rc-sheet {
        margin: 0;
        box-shadow: none;
        width: auto;
        min-height: 0;
      }
    }
  </style>
</head>
<body>

<div class="rc-toolbar no-print">
  <div class="rc-toolbar__title">
    <?= View::e($schoolName) ?>
    <small>
      Report cards &middot; <?= View::e($stageLabel) ?> &middot; <?= View::e($year) ?> &middot; <?= View::e($term) ?>
      &middot; <?= View::e($scopeLabel) ?> &middot; <?= (int) $n ?> student<?= $n === 1 ? '' : 's' ?>
    </small>
  </div>
  <form method="get" class="d-flex flex-wrap gap-2 align-items-end">
    <div>
      <label class="form-label small mb-1">Class</label>
      <select name="class_id" class="form-select form-select-sm">
        <option value="0" <?= (int) $classId === 0 ? 'selected' : '' ?>>All classes</option>
        <?php foreach (($classes ?? []) as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= (int) $classId === (int) $c['id'] ? 'selected' : '' ?>>
            <?= View::e(($c['name'] ?? '') . (!empty($c['level']) ? ' · ' . $c['level'] : '')) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="form-label small mb-1">Year</label>
      <input name="year" class="form-control form-control-sm" value="<?= View::e($year) ?>" size="9">
    </div>
    <div>
      <label class="form-label small mb-1">Term</label>
      <select name="term" class="form-select form-select-sm">
        <?php foreach (($terms ?? []) as $t): ?>
          <option <?= $t === $term ? 'selected' : '' ?>><?= View::e($t) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="form-label small mb-1">Assessment</label>
      <select name="stage" class="form-select form-select-sm">
        <?php foreach (($stages ?? []) as $key => $label): ?>
          <option value="<?= View::e((string) $key) ?>" <?= $key === $stage ? 'selected' : '' ?>><?= View::e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-outline-primary btn-sm" type="submit">Apply</button>
  </form>
  <button type="button" class="btn btn-primary btn-sm" onclick="window.print()" <?= $n === 0 ? 'disabled' : '' ?>>
    <i class="bi bi-printer"></i> Print <?= (int) $n ?> card<?= $n === 1 ? '' : 's' ?>
  </button>
  <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?><?= $portalPrefix ?>/reports?<?= View::e($qs()) ?>">Back</a>
</div>

<?php if ($n === 0): ?>
  <div class="rc-empty">
    <h2 class="h5 mb-2">No report cards to print</h2>
    <p class="text-muted mb-0">
      No students found for this selection. Pick a different class or period above.
    </p>
  </div>
<?php else: ?>
  <?php foreach ($booklet as $entry):
    $student  = $entry['student'];
    $sheet    = $entry['sheet'];
    $position = $entry['position'];

    /**
     * Shrink-to-fit factor for this card.
     *
     * Measured against Chromium with app.css's print metrics: a card costs
     * roughly 385px of fixed furniture (letterhead, student meta, summary,
     * signatures, footer) plus ~23px per subject row. The budget is held
     * at 980px rather than the ~1040px an A4 sheet actually offers at 12mm
     * margins, because the browser's print dialog overrides the page
     * margins and a user may pick wider ones — the slack absorbs that.
     * Anything at or under the budget prints at full size; only genuinely
     * oversized curricula are scaled, and only as far as they need.
     */
    $rowCount = 0;
    foreach (($sheet['groups'] ?? []) as $g) {
        $rowCount += count($g['rows'] ?? []);
    }
    $estimatedHeight = 385 + ($rowCount * 23);
    $zoom = $estimatedHeight > 980
        ? max(0.55, round(980 / $estimatedHeight, 3))
        : 1;
  ?>
    <section class="rc-sheet"<?= $zoom < 1 ? ' style="--rc-zoom: ' . $zoom . '"' : '' ?>>
      <?php include __DIR__ . '/_student_card.php'; ?>
    </section>
  <?php endforeach; ?>
<?php endif; ?>

</body>
</html>
