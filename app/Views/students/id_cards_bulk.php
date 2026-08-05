<?php
use App\Core\View;

/**
 * Printable sheet of ID cards for one class — print, trim along the guides,
 * laminate. Eight cards per A4 page (2 columns x 4 rows) at 10mm page margins:
 * 2 x (85.6mm card + 2 x 2mm slot padding) + 8mm column gap = 187.2mm of the
 * ~190mm usable width; 4 x 58mm + 3 x 9mm row gap = 259mm of the ~277mm height.
 *
 * Standalone print page (own <!doctype>, not the app layout) — it carries its
 * own copy of the .id-card* CSS, matching the convention used by the exam
 * permit and admission letter print views.
 *
 * @var array<int, array> $students
 * @var string            $className
 * @var array             $school
 * @var array             $theme
 */
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$schoolName  = (string) ($school['name'] ?? '');
$accent      = (string) ($theme['accent'] ?? '#2563eb');
$accentHover = (string) ($theme['accent_hover'] ?? '#1d4ed8');
$count = count($students);
$pages = (int) ceil(max($count, 1) / 8);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>ID Cards · <?= View::e($className) ?> · <?= View::e($schoolName) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    @page { size: A4; margin: 10mm; }

    :root {
      --pg-accent: <?= View::e($accent) ?>;
      --pg-accent-hover: <?= View::e($accentHover) ?>;
      --pg-ink: #0f172a;
      --pg-muted: #6b7280;
      --pg-soft: #9ca3af;
      --pg-hair: #e5e7eb;
    }
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; }
    body {
      background: #eef1f5;
      color: var(--pg-ink);
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      padding: 0 10mm 14mm;
      -webkit-font-smoothing: antialiased;
    }

    /* ----- On-screen sheet header / toolbar (never printed) ----- */
    .sheet-bar {
      position: sticky;
      top: 0;
      z-index: 5;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 12px;
      max-width: 200mm;
      margin: 0 auto 10mm;
      padding: 14px 18px;
      background: #fff;
      border: 1px solid #dde4ec;
      border-top: 3px solid var(--pg-accent);
      border-radius: 0 0 12px 12px;
    }
    .sheet-bar__kicker {
      font-size: .66rem;
      font-weight: 600;
      letter-spacing: .18em;
      text-transform: uppercase;
      color: var(--pg-accent-hover);
    }
    .sheet-bar__title {
      margin: 3px 0 0;
      font-size: 1.1rem;
      font-weight: 700;
      letter-spacing: -.01em;
    }
    .sheet-bar__meta {
      margin: 4px 0 0;
      font-size: .78rem;
      color: var(--pg-muted);
      display: flex;
      flex-wrap: wrap;
      gap: 6px 14px;
    }
    .sheet-bar__meta i { color: var(--pg-soft); }
    .sheet-bar__actions { display: flex; align-items: center; gap: 8px; }
    .pg-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 20px;
      font-family: inherit;
      font-size: .85rem;
      font-weight: 600;
      letter-spacing: .06em;
      text-transform: uppercase;
      border-radius: 8px;
      border: 1px solid transparent;
      cursor: pointer;
    }
    .pg-btn--primary { background: var(--pg-accent-hover); color: #fff; }
    .pg-btn--primary:hover { background: var(--pg-accent); }
    .pg-btn--ghost { background: #fff; color: var(--pg-ink); border-color: #cbd5e1; }
    .pg-btn--ghost:hover { background: #f8fafc; }

    /* ----- Print-and-cut grid ----- */
    .id-card-grid {
      display: grid;
      grid-template-columns: repeat(2, 89.6mm);
      justify-content: center;
      column-gap: 8mm;
      row-gap: 9mm;
    }
    .id-card-slot {
      break-inside: avoid;
      page-break-inside: avoid;
      padding: 2mm;
      border: 0.2mm dashed #cbd5e1;
      border-radius: 3.4mm;
    }
    .id-card-slot .id-card { box-shadow: 0 6px 16px rgba(15, 23, 42, .12); }

    .empty-state {
      max-width: 520px;
      margin: 60px auto;
      text-align: center;
      background: #fff;
      padding: 36px;
      border: 1px solid #dde4ec;
      border-top: 3px solid var(--pg-accent);
      border-radius: 12px;
    }
    .empty-state h2 {
      margin: 0 0 8px;
      font-size: 1.15rem;
      font-weight: 700;
    }
    .empty-state p { margin: 0 0 20px; font-size: .88rem; color: var(--pg-muted); }

    /* ===== ID card face =========================================================
       ID-1 / credit-card proportions (85.6 x 54mm). Restrained institutional
       design language shared with the exam permit and admission letter: one
       accent rule, hairline dividers, uppercase micro-labels above values,
       a dominant name, and a faint diagonal security texture in the school's
       own colour.

       All colour is driven by the three custom properties set inline on .id-card
       by students/_id_card_face.php (--ic-accent, --ic-accent-hover,
       --ic-accent-soft), so every palette in Settings::THEMES works — and the
       theme picker's live preview stays accurate, since those are exactly the
       properties its applyTheme() swaps. Rule of thumb kept below: accent fills
       and hairlines use --ic-accent, accent TEXT uses the darker
       --ic-accent-hover (readable even for amber/lime/orange), and white text is
       never placed on an accent. Tints come from element opacity rather than
       rgba(), so no separate RGB channel variable is needed.

       This block is duplicated verbatim in the three pages that render a card
       face — keep them in sync:
         app/Views/students/id_card.php
         app/Views/students/id_cards_bulk.php
         app/Views/schools/id_card_theme.php   (#idCardPreview-prefixed copy)
       ========================================================================= */
    .id-card {
      --ic-accent: #2563eb;
      --ic-accent-hover: #1d4ed8;
      --ic-accent-soft: #eff4ff;
      --ic-ink: #0f172a;
      --ic-muted: #6b7280;
      --ic-soft: #9ca3af;
      --ic-hair: #e5e7eb;
      position: relative;
      width: 85.6mm;
      height: 54mm;
      border-radius: 2.4mm;
      overflow: hidden;
      background: #fff;
      border: 0.2mm solid var(--ic-hair);
      color: var(--ic-ink);
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      font-feature-settings: "kern", "liga", "tnum";
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .id-card__rule {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 1.2mm;
      background: var(--ic-accent);
    }
    .id-card__guard {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: repeating-linear-gradient(
        135deg,
        var(--ic-accent) 0,
        var(--ic-accent) 0.28mm,
        transparent 0.28mm,
        transparent 2.2mm
      );
      opacity: .06;
      pointer-events: none;
    }
    .id-card__inner {
      position: relative;
      z-index: 1;
      height: 100%;
      padding-top: 1.2mm;
      display: flex;
      flex-direction: column;
    }
    .id-card__head {
      display: flex;
      align-items: center;
      gap: 2.2mm;
      padding: 2.2mm 3.2mm 1.9mm;
      border-bottom: 0.2mm solid var(--ic-hair);
    }
    .id-card__logo {
      width: 7mm;
      height: 7mm;
      object-fit: contain;
      flex-shrink: 0;
    }
    .id-card__logo--ph {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: 0.2mm solid var(--ic-hair);
      border-radius: 1mm;
      background: #fff;
      color: var(--ic-accent-hover);
      font-size: 3.6mm;
      line-height: 1;
    }
    .id-card__brand {
      flex: 1 1 auto;
      min-width: 0;
    }
    .id-card__school {
      font-size: 2.5mm;
      font-weight: 700;
      letter-spacing: 0.04em;
      line-height: 1.16;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    .id-card__doctype {
      display: block;
      margin-top: 0.5mm;
      font-size: 1.7mm;
      font-weight: 600;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: var(--ic-accent-hover);
      line-height: 1.3;
    }
    .id-card__mark {
      flex-shrink: 0;
      font-size: 4.8mm;
      line-height: 1;
      color: var(--ic-accent);
    }
    .id-card__body {
      flex: 1 1 auto;
      min-height: 0;
      display: flex;
      align-items: stretch;
      gap: 3.2mm;
      padding: 2.6mm 3.2mm 2.4mm;
    }
    .id-card__photo {
      width: 22.5mm;
      min-height: 24mm;
      align-self: stretch;
      flex-shrink: 0;
      border-radius: 1.2mm;
      overflow: hidden;
      background: var(--ic-accent-soft);
      border: 0.25mm solid var(--ic-accent);
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .id-card__photo img {
      display: block;
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .id-card__initials {
      font-size: 7mm;
      font-weight: 700;
      letter-spacing: 0.02em;
      line-height: 1;
      color: var(--ic-accent-hover);
    }
    .id-card__info {
      flex: 1 1 auto;
      min-width: 0;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .id-card__name {
      font-size: 4mm;
      font-weight: 700;
      letter-spacing: 0.005em;
      line-height: 1.12;
      text-transform: uppercase;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    .id-card__field {
      margin-top: 1.2mm;
    }
    .id-card__lbl {
      display: block;
      font-size: 1.7mm;
      font-weight: 600;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      color: var(--ic-soft);
      line-height: 1.4;
    }
    .id-card__adm {
      display: block;
      font-family: 'JetBrains Mono', 'SFMono-Regular', Menlo, Consolas, monospace;
      font-size: 2.9mm;
      font-weight: 600;
      letter-spacing: 0.01em;
      line-height: 1.3;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .id-card__grid {
      margin-top: 1.2mm;
      padding-top: 1.7mm;
      border-top: 0.2mm solid var(--ic-hair);
      display: grid;
      grid-template-columns: 1fr 1fr;
      column-gap: 2.4mm;
    }
    .id-card__cell {
      min-width: 0;
    }
    .id-card__val {
      display: block;
      font-size: 2.5mm;
      font-weight: 600;
      line-height: 1.3;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .id-card__foot {
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 2mm;
      padding: 1.4mm 3.2mm;
      background: var(--ic-accent-soft);
      border-top: 0.2mm solid var(--ic-hair);
      font-size: 1.7mm;
      letter-spacing: 0.04em;
      color: var(--ic-muted);
    }
    .id-card__foot-own {
      min-width: 0;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .id-card__foot-note {
      flex-shrink: 0;
      color: var(--ic-soft);
      text-transform: uppercase;
      letter-spacing: 0.12em;
      font-size: 1.5mm;
      font-weight: 600;
    }
    /* ===== end ID card face ================================================== */

    @media print {
      body { background: #fff; padding: 0; }
      .sheet-bar, .no-print { display: none !important; }
      .id-card-slot { border-color: #d5dbe3; }
      .id-card-slot .id-card { box-shadow: none; }
    }
  </style>
</head>
<body>

<?php if (empty($students)): ?>
  <div class="empty-state">
    <h2>No students in this class yet</h2>
    <p>Add students to &ldquo;<?= View::e($className) ?>&rdquo; before printing ID cards.</p>
    <button type="button" class="pg-btn pg-btn--ghost no-print" onclick="window.close()">Close</button>
  </div>
<?php else: ?>
  <div class="sheet-bar no-print">
    <div>
      <div class="sheet-bar__kicker">Student Identity Cards</div>
      <h1 class="sheet-bar__title"><?= View::e($className) ?></h1>
      <div class="sheet-bar__meta">
        <span><i class="bi bi-building"></i> <?= View::e($schoolName) ?></span>
        <span><i class="bi bi-person-vcard"></i> <?= (int) $count ?> card<?= $count === 1 ? '' : 's' ?></span>
        <span><i class="bi bi-files"></i> <?= $pages ?> A4 page<?= $pages === 1 ? '' : 's' ?> &middot; 8 per page</span>
        <span><i class="bi bi-scissors"></i> Trim along the dashed guides</span>
      </div>
    </div>
    <div class="sheet-bar__actions">
      <button type="button" class="pg-btn pg-btn--primary" onclick="window.print()">
        <i class="bi bi-printer"></i> Print all
      </button>
      <button type="button" class="pg-btn pg-btn--ghost" onclick="window.close()">Close</button>
    </div>
  </div>

  <div class="id-card-grid">
    <?php foreach ($students as $student): ?>
      <div class="id-card-slot">
        <?php include __DIR__ . '/_id_card_face.php'; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<script>
  window.addEventListener('load', function () {
    if (window.self !== window.top) return;
    setTimeout(window.print, 400);
  });
</script>
</body>
</html>
