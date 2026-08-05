<?php
use App\Core\View;

/**
 * Single printable student ID card.
 *
 * Standalone print page (own <!doctype>, not the app layout) — it carries its
 * own copy of the .id-card* CSS, matching the convention used by the exam
 * permit and admission letter print views.
 *
 * @var array $student
 * @var array $school
 * @var array $theme
 */
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$studentName = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
$accent      = (string) ($theme['accent'] ?? '#2563eb');
$accentHover = (string) ($theme['accent_hover'] ?? '#1d4ed8');
$cardClass   = trim((string) ($student['class_name'] ?? '')) ?: trim((string) ($student['level'] ?? ''));
$admNo       = trim((string) ($student['admission_no'] ?? ''));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Student ID Card · <?= View::e($studentName) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    @page { size: A4; margin: 0; }

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
      padding: 26px 18px 40px;
      -webkit-font-smoothing: antialiased;
    }

    /* ----- On-screen chrome (never printed) ----- */
    .pg-shell { max-width: 560px; margin: 0 auto; }
    .pg-head { text-align: center; margin-bottom: 18px; }
    .pg-head__kicker {
      font-size: .66rem;
      font-weight: 600;
      letter-spacing: .18em;
      text-transform: uppercase;
      color: var(--pg-accent-hover);
    }
    .pg-head__name {
      margin: 4px 0 0;
      font-size: 1.35rem;
      font-weight: 700;
      letter-spacing: -.01em;
    }
    .pg-head__meta {
      margin: 4px 0 0;
      font-size: .8rem;
      color: var(--pg-muted);
    }
    .pg-head__meta .mono {
      font-family: 'JetBrains Mono', 'SFMono-Regular', Menlo, Consolas, monospace;
      color: var(--pg-ink);
      font-weight: 500;
    }

    .pg-stage {
      display: flex;
      justify-content: center;
      padding: 34px 18px;
      background: #e4e9ef;
      border: 1px solid #d7dee7;
      border-radius: 14px;
      overflow-x: auto;
    }
    .pg-stage .id-card { box-shadow: 0 14px 34px rgba(15, 23, 42, .18); }

    .pg-actions { margin-top: 20px; text-align: center; }
    .pg-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 22px;
      font-family: inherit;
      font-size: .9rem;
      font-weight: 600;
      letter-spacing: .06em;
      text-transform: uppercase;
      border-radius: 8px;
      border: 1px solid transparent;
      cursor: pointer;
    }
    .pg-btn--primary { background: var(--pg-accent-hover); color: #fff; }
    .pg-btn--primary:hover { background: var(--pg-accent); }
    .pg-btn--ghost {
      background: #fff;
      color: var(--pg-ink);
      border-color: #cbd5e1;
      margin-left: 8px;
    }
    .pg-btn--ghost:hover { background: #f8fafc; }
    .pg-hint {
      margin: 12px 0 0;
      font-size: .72rem;
      letter-spacing: .04em;
      color: var(--pg-soft);
    }

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
      .pg-shell { max-width: none; }
      .pg-head, .pg-actions, .no-print { display: none !important; }
      .pg-stage {
        display: block;
        padding: 0;
        background: none;
        border: 0;
        border-radius: 0;
        overflow: visible;
      }
      .pg-stage .id-card { box-shadow: none; }
      .id-card {
        margin: 16mm auto 0;
        outline: 0.2mm dashed #d5dbe3;   /* trim guide, same idea as the bulk sheet */
        outline-offset: 2mm;
      }
    }
  </style>
</head>
<body>

<div class="pg-shell">
  <div class="pg-head no-print">
    <div class="pg-head__kicker">Student Identity Card</div>
    <h1 class="pg-head__name"><?= View::e($studentName !== '' ? $studentName : 'Student') ?></h1>
    <p class="pg-head__meta">
      <?= View::e($cardClass !== '' ? $cardClass : '—') ?>
      <?php if ($admNo !== ''): ?>
        &middot; <span class="mono"><?= View::e($admNo) ?></span>
      <?php endif; ?>
    </p>
  </div>

  <div class="pg-stage">
    <?php include __DIR__ . '/_id_card_face.php'; ?>
  </div>

  <div class="pg-actions no-print">
    <button type="button" class="pg-btn pg-btn--primary" onclick="window.print()">
      <i class="bi bi-printer"></i> Print
    </button>
    <button type="button" class="pg-btn pg-btn--ghost" onclick="window.close()">Close</button>
    <p class="pg-hint">Prints at actual size — 85.6 &times; 54 mm on A4. Trim and laminate.</p>
  </div>
</div>

<script>
  window.addEventListener('load', function () {
    if (window.self !== window.top) return;
    setTimeout(window.print, 400);
  });
</script>
</body>
</html>
