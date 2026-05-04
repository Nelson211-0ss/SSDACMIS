<?php
use App\Core\View;
use App\Core\App;
use App\Core\Settings;

/**
 * Examination permit — modern, restrained design. One permit per A4 page.
 *
 * Design language:
 *   - Single emerald accent rule across the top (the only colour spike).
 *   - Compact sans-serif letterhead on the left, document type on the right.
 *   - ID-card style two-column body: passport photo + tabular details.
 *   - Status as a thin-bordered chip (no banners, no stars, no watermarks).
 *   - Two flat signature blocks separated by a neutral hairline.
 *
 * @var array<int, array> $rows
 * @var string            $year
 * @var string            $term
 * @var string            $level
 */
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

$schoolName    = Settings::get('school_name')  ?: (string) App::config('app.name');
$schoolMotto   = (string) (Settings::get('school_motto')   ?? '');
$schoolPhone   = (string) (Settings::get('school_phone')   ?? '');
$schoolEmail   = (string) (Settings::get('school_email')   ?? '');
$schoolAddress = (string) (Settings::get('school_address') ?? '');
$schoolLogo    = Settings::logoUrl();

$htName     = trim((string) (Settings::get('school_headteacher_name')  ?? ''));
$htTitle    = trim((string) (Settings::get('school_headteacher_title') ?? 'Head Teacher'));
if ($htTitle === '') $htTitle = 'Head Teacher';
$htSignature = Settings::headteacherSignatureUrl();

$today    = date('F j, Y');
$validFor = trim($term . ' · ' . $year);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Examination Permit · <?= View::e($schoolName) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    @page { size: A4; margin: 0; }

    :root {
      --ink:    #111827;
      --muted:  #6b7280;
      --soft:   #9ca3af;
      --hair:   #e5e7eb;
      --accent: #047857; /* emerald — clearance/approval colour */
      --accent-soft: #ecfdf5;
    }
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; }

    body {
      background: #f4f5f7;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      color: var(--ink);
      padding: 18px;
      font-feature-settings: "ss01","cv11","kern","liga","tnum";
      -webkit-font-smoothing: antialiased;
    }
    .mono { font-family: 'JetBrains Mono', 'SFMono-Regular', Menlo, monospace; }

    /* ----- A4 sheet ----- */
    .permit-page {
      width: 210mm;
      min-height: 297mm;
      max-height: 297mm;
      padding: 22mm 22mm 18mm;
      margin: 0 auto 18px;
      background: #fff;
      border: 1px solid var(--hair);
      box-shadow: 0 8px 28px rgba(15, 23, 42, .06);
      page-break-after: always;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      position: relative;
      font-size: 10pt;
      line-height: 1.55;
    }
    .permit-page:last-of-type { page-break-after: auto; margin-bottom: 0; }

    .permit-page::before {
      content: "";
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 4px;
      background: var(--accent);
    }

    /* ----- Top header ----- */
    .pe-head {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 22px;
      padding-bottom: 10px;
      border-bottom: 1px solid var(--hair);
    }
    .pe-brand { display: flex; align-items: center; gap: 14px; flex: 1; min-width: 0; }
    .pe-brand__logo {
      width: 56px;
      height: 56px;
      object-fit: contain;
      flex-shrink: 0;
    }
    .pe-brand__logo--ph {
      width: 56px; height: 56px;
      border: 1px solid var(--hair);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      color: var(--soft);
      font-size: 1.4rem;
      font-weight: 600;
    }
    .pe-brand__name {
      font-size: 14pt;
      font-weight: 700;
      letter-spacing: 0.04em;
      line-height: 1.15;
      text-transform: uppercase;
    }
    .pe-brand__contact {
      margin-top: 3px;
      font-size: 7.5pt;
      color: var(--muted);
      line-height: 1.45;
    }

    .pe-doctype {
      text-align: right;
      flex-shrink: 0;
    }
    .pe-doctype__lbl {
      font-size: 7.5pt;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: var(--muted);
      font-weight: 600;
    }
    .pe-doctype__title {
      font-size: 13pt;
      font-weight: 700;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: var(--ink);
      line-height: 1;
      margin-top: 2px;
    }
    .pe-doctype__valid {
      margin-top: 4px;
      font-size: 9pt;
      color: var(--muted);
    }
    .pe-doctype__valid strong { color: var(--ink); font-weight: 600; }

    /* ----- Reference / status row ----- */
    .pe-meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 16px;
      margin-top: 12px;
      padding: 10px 0;
      border-bottom: 1px solid var(--hair);
    }
    .pe-meta__lbl {
      font-size: 7.5pt;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: var(--soft);
      font-weight: 600;
    }
    .pe-meta__val {
      font-size: 11pt;
      font-weight: 600;
    }
    .pe-status {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 12px;
      border: 1px solid var(--accent);
      color: var(--accent);
      background: var(--accent-soft);
      font-size: 8pt;
      font-weight: 700;
      letter-spacing: 0.16em;
      text-transform: uppercase;
    }
    .pe-status__dot {
      width: 6px; height: 6px;
      border-radius: 50%;
      background: var(--accent);
    }

    /* ----- Body grid ----- */
    .pe-body {
      flex: 1 1 auto;
      display: grid;
      grid-template-columns: 130px 1fr;
      gap: 28px;
      padding: 18px 0;
    }

    .pe-photo {
      width: 130px;
      height: 168px;
      border: 1px solid var(--ink);
      background: #f8fafc;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }
    .pe-photo img { width: 100%; height: 100%; object-fit: cover; }
    .pe-photo__ph {
      color: var(--soft);
      font-size: 7.5pt;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      text-align: center;
      padding: 8px;
      line-height: 1.4;
    }

    .pe-name {
      font-size: 16pt;
      font-weight: 700;
      letter-spacing: -0.01em;
      color: var(--ink);
      margin-bottom: 2px;
      line-height: 1.1;
    }
    .pe-adm {
      font-size: 9pt;
      color: var(--muted);
      letter-spacing: 0.04em;
      margin-bottom: 14px;
    }
    .pe-adm strong { color: var(--ink); font-weight: 600; }

    .pe-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      column-gap: 24px;
      row-gap: 6px;
    }
    .pe-grid__cell { display: flex; flex-direction: column; gap: 1px; }
    .pe-grid__lbl {
      font-size: 7.5pt;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      color: var(--soft);
      font-weight: 600;
    }
    .pe-grid__val { font-size: 9.5pt; font-weight: 500; }
    .pe-grid__val--accent { color: var(--accent); font-weight: 600; }

    /* ----- Clearance note ----- */
    .pe-note {
      margin-top: 12px;
      padding: 10px 14px;
      border-left: 2px solid var(--accent);
      background: var(--accent-soft);
      font-size: 9pt;
      line-height: 1.55;
      color: var(--ink);
    }

    /* ----- Signatures ----- */
    .pe-sigs {
      margin-top: 16px;
      padding-top: 12px;
      border-top: 1px solid var(--hair);
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 32px;
    }
    .pe-sig { display: flex; flex-direction: column; }
    .pe-sig__img {
      display: block;
      height: 36px;
      max-width: 180px;
      object-fit: contain;
      margin-bottom: 4px;
    }
    .pe-sig__placeholder { height: 36px; }
    .pe-sig__line {
      border-top: 1px solid var(--ink);
      padding-top: 4px;
      font-size: 9pt;
      font-weight: 600;
    }
    .pe-sig__role {
      font-size: 7.5pt;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      color: var(--soft);
      margin-top: 1px;
    }

    /* ----- Footer ----- */
    .pe-foot {
      margin-top: 14px;
      padding-top: 10px;
      border-top: 1px solid var(--hair);
      display: flex;
      justify-content: space-between;
      gap: 16px;
      font-size: 7.5pt;
      color: var(--soft);
      letter-spacing: 0.04em;
    }

    .empty-state {
      max-width: 600px;
      margin: 80px auto;
      text-align: center;
      background: #fff;
      padding: 40px;
      border: 1px solid var(--hair);
    }

    @media print {
      body { background: #fff; padding: 0; }
      .permit-page {
        width: auto;
        min-height: 0;
        max-height: none;
        margin: 0;
        padding: 22mm 22mm 18mm;
        box-shadow: none;
        border: 0;
      }
      .no-print { display: none !important; }
    }
  </style>
</head>
<body>

<?php if (empty($rows)): ?>
  <div class="empty-state">
    <h2 style="margin-top:0; color: var(--accent);">No permits to print</h2>
    <p style="color: var(--muted);">
      No fully-paid students for the active period. Permits are issued
      automatically only when a student's term balance reaches zero.
    </p>
    <button class="no-print" onclick="window.close()">Close</button>
  </div>
<?php else: foreach ($rows as $r):
  $photo = trim((string) ($r['photo_path'] ?? ''));
  $section = strtolower((string) ($r['section'] ?? 'day'));
  $sectionL = $section === 'boarding' ? 'Boarding' : 'Day';
  $stream = strtolower((string) ($r['stream'] ?? 'none'));
  $streamL = $stream === 'science' ? 'Science' : ($stream === 'arts' ? 'Arts' : '—');
  $cls = trim((string) ($r['class_name'] ?? '')) ?: trim((string) ($r['level'] ?? '—'));
  $termSlug = strtoupper(str_replace(' ', '', (string) $term));
  $permitNo = ($r['admission_no'] ?? '—') . '/' . $termSlug . '/' . date('Y');
?>
  <div class="permit-page">

    <!-- Letterhead -->
    <header class="pe-head">
      <div class="pe-brand">
        <?php if ($schoolLogo): ?>
          <img class="pe-brand__logo" src="<?= $base ?>/<?= View::e($schoolLogo) ?>" alt="">
        <?php else: ?>
          <span class="pe-brand__logo--ph">S</span>
        <?php endif; ?>
        <div style="min-width: 0;">
          <div class="pe-brand__name"><?= View::e($schoolName) ?></div>
          <div class="pe-brand__contact">
            <?php
              $bits = array_values(array_filter([
                $schoolAddress,
                $schoolPhone !== '' ? 'T ' . $schoolPhone : '',
                $schoolEmail !== '' ? 'E ' . $schoolEmail : '',
              ], fn($v) => $v !== ''));
              echo View::e(implode(' · ', $bits));
            ?>
          </div>
        </div>
      </div>

      <div class="pe-doctype">
        <div class="pe-doctype__lbl">Bursar's Office</div>
        <div class="pe-doctype__title">Examination Permit</div>
        <div class="pe-doctype__valid">Valid for <strong><?= View::e($validFor) ?></strong></div>
      </div>
    </header>

    <!-- Reference / status -->
    <div class="pe-meta">
      <div>
        <div class="pe-meta__lbl">Permit Number</div>
        <div class="pe-meta__val mono"><?= View::e($permitNo) ?></div>
      </div>
      <div class="pe-status">
        <span class="pe-status__dot"></span>
        Cleared — Authorised to Sit Examinations
      </div>
      <div style="text-align: right;">
        <div class="pe-meta__lbl">Issued</div>
        <div class="pe-meta__val"><?= View::e($today) ?></div>
      </div>
    </div>

    <!-- Body -->
    <div class="pe-body">
      <div class="pe-photo">
        <?php if ($photo !== ''): ?>
          <img src="<?= $base ?>/<?= View::e($photo) ?>" alt="">
        <?php else: ?>
          <span class="pe-photo__ph">Passport<br>photo<br>on file</span>
        <?php endif; ?>
      </div>

      <div>
        <div class="pe-name"><?= View::e(trim($r['first_name'] . ' ' . $r['last_name'])) ?></div>
        <div class="pe-adm">Admission No. <strong><?= View::e($r['admission_no']) ?></strong></div>

        <div class="pe-grid">
          <div class="pe-grid__cell">
            <span class="pe-grid__lbl">Class</span>
            <span class="pe-grid__val"><?= View::e($cls) ?></span>
          </div>
          <div class="pe-grid__cell">
            <span class="pe-grid__lbl">Section</span>
            <span class="pe-grid__val"><?= View::e($sectionL) ?></span>
          </div>
          <div class="pe-grid__cell">
            <span class="pe-grid__lbl">Stream</span>
            <span class="pe-grid__val"><?= View::e($streamL) ?></span>
          </div>
          <div class="pe-grid__cell">
            <span class="pe-grid__lbl">Academic Year</span>
            <span class="pe-grid__val"><?= View::e($year) ?></span>
          </div>
          <div class="pe-grid__cell">
            <span class="pe-grid__lbl">Term</span>
            <span class="pe-grid__val"><?= View::e($term) ?></span>
          </div>
          <div class="pe-grid__cell">
            <span class="pe-grid__lbl">Term Fees</span>
            <span class="pe-grid__val mono"><?= number_format((float) $r['total_amount'], 2) ?></span>
          </div>
          <div class="pe-grid__cell">
            <span class="pe-grid__lbl">Paid</span>
            <span class="pe-grid__val mono pe-grid__val--accent"><?= number_format((float) $r['paid_amount'], 2) ?></span>
          </div>
          <div class="pe-grid__cell">
            <span class="pe-grid__lbl">Outstanding</span>
            <span class="pe-grid__val mono pe-grid__val--accent">0.00</span>
          </div>
        </div>

        <div class="pe-note">
          This permit certifies that the above-named student has cleared
          all <strong><?= View::e($term) ?></strong> fees for the
          academic year <strong><?= View::e($year) ?></strong> and is
          authorised to sit every examination scheduled during the term.
          Present this permit to the invigilator before each paper.
        </div>
      </div>
    </div>

    <!-- Signatures -->
    <div class="pe-sigs">
      <div class="pe-sig">
        <div class="pe-sig__placeholder"></div>
        <div class="pe-sig__line">Bursar</div>
        <div class="pe-sig__role">Fees Office</div>
      </div>
      <div class="pe-sig">
        <?php if ($htSignature): ?>
          <img class="pe-sig__img" src="<?= $base ?>/<?= View::e($htSignature) ?>" alt="">
        <?php else: ?>
          <div class="pe-sig__placeholder"></div>
        <?php endif; ?>
        <div class="pe-sig__line"><?= View::e($htName !== '' ? $htName : '—') ?></div>
        <div class="pe-sig__role"><?= View::e($htTitle) ?></div>
      </div>
    </div>

    <!-- Footer -->
    <footer class="pe-foot">
      <span>System-generated permit · Ref <span class="mono"><?= View::e($permitNo) ?></span></span>
      <span>Forgery, alteration or unauthorised duplication is a disciplinary offence.</span>
    </footer>
  </div>
<?php endforeach; endif; ?>

<?php if (!empty($rows)): ?>
  <div class="no-print" style="text-align:center; margin: 14px 0;">
    <button onclick="window.print()" style="background:#047857; color:#fff; border:0; padding:10px 22px; font-size:.95rem; letter-spacing: .04em; cursor:pointer;">
      <i class="bi bi-printer"></i>&nbsp; PRINT
    </button>
    <button onclick="window.close()" style="background:#fff; color:#0f172a; border:1px solid #cbd5e1; padding:10px 22px; font-size:.95rem; letter-spacing: .04em; cursor:pointer; margin-left:8px;">
      CLOSE
    </button>
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
