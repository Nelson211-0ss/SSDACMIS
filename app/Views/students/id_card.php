<?php
use App\Core\View;

/**
 * Single printable student ID card.
 *
 * @var array $student
 * @var array $school
 * @var array $theme
 */
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$studentName = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
$accentHover = (string) ($theme['accent_hover'] ?? '#1d4ed8');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Student ID Card · <?= View::e($studentName) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    @page { size: A4; margin: 0; }
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; }
    body {
      background: #eef1f5;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 24mm 0;
      -webkit-font-smoothing: antialiased;
    }

    /* ----- ID card face (shared proportions with the bulk sheet) ----- */
    .id-card {
      width: 85.6mm;
      height: 54mm;
      border-radius: 3mm;
      overflow: hidden;
      background: #fff;
      border: 0.2mm solid #e2e8f0;
      box-shadow: 0 10px 26px rgba(15, 23, 42, .16);
      display: flex;
      flex-direction: column;
    }
    .id-card__band {
      background: var(--ic-accent-hover);
      color: #fff;
      display: flex;
      align-items: center;
      gap: 2mm;
      padding: 2mm 3mm;
    }
    .id-card__logo {
      width: 7mm; height: 7mm;
      object-fit: contain;
      background: #fff;
      border-radius: 1mm;
      padding: 0.5mm;
      flex-shrink: 0;
    }
    .id-card__logo--ph {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      color: var(--ic-accent-hover);
      font-size: 4mm;
    }
    .id-card__school {
      font-size: 2.6mm;
      font-weight: 700;
      letter-spacing: 0.02em;
      text-transform: uppercase;
      line-height: 1.15;
      flex: 1 1 auto;
      min-width: 0;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .id-card__doctype {
      font-size: 1.9mm;
      font-weight: 600;
      letter-spacing: 0.12em;
      background: rgba(255, 255, 255, .18);
      padding: 0.6mm 1.4mm;
      border-radius: 1mm;
      white-space: nowrap;
      flex-shrink: 0;
    }
    .id-card__body {
      flex: 1 1 auto;
      display: flex;
      gap: 2.5mm;
      padding: 2.5mm 3mm;
      background: var(--ic-accent-soft);
    }
    .id-card__photo {
      width: 16mm; height: 20mm;
      border-radius: 1.5mm;
      overflow: hidden;
      background: #fff;
      border: 0.4mm solid var(--ic-accent);
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .id-card__photo img { width: 100%; height: 100%; object-fit: cover; }
    .id-card__initials { font-size: 6mm; font-weight: 700; color: var(--ic-accent); }
    .id-card__info {
      flex: 1 1 auto;
      min-width: 0;
      display: flex;
      flex-direction: column;
      justify-content: center;
      gap: 1mm;
    }
    .id-card__name {
      font-size: 3.4mm;
      font-weight: 700;
      color: #111827;
      line-height: 1.15;
      margin-bottom: 0.5mm;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .id-card__row { display: flex; gap: 1.5mm; font-size: 2.3mm; line-height: 1.3; }
    .id-card__lbl {
      color: #6b7280;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      width: 13mm;
      flex-shrink: 0;
      font-weight: 600;
    }
    .id-card__val { color: #111827; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .id-card__foot {
      background: var(--ic-accent);
      color: #fff;
      font-size: 1.7mm;
      text-align: center;
      padding: 1mm;
      letter-spacing: 0.03em;
    }

    @media print {
      body { background: #fff; padding: 0; align-items: flex-start; justify-content: flex-start; }
      .id-card { box-shadow: none; margin: 14mm auto 0; }
      .no-print { display: none !important; }
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/_id_card_face.php'; ?>

<div class="no-print" style="text-align:center; margin-top: 8mm;">
  <button onclick="window.print()" style="background:<?= View::e($accentHover) ?>; color:#fff; border:0; padding:10px 22px; font-size:.95rem; letter-spacing: .04em; cursor:pointer; border-radius: 6px;">
    <i class="bi bi-printer"></i>&nbsp; PRINT
  </button>
  <button onclick="window.close()" style="background:#fff; color:#0f172a; border:1px solid #cbd5e1; padding:10px 22px; font-size:.95rem; letter-spacing: .04em; cursor:pointer; margin-left:8px; border-radius: 6px;">
    CLOSE
  </button>
</div>

<script>
  window.addEventListener('load', function () {
    if (window.self !== window.top) return;
    setTimeout(window.print, 400);
  });
</script>
</body>
</html>
