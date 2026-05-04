<?php
use App\Core\View;
use App\Core\App;
use App\Core\Settings;

/**
 * Official admission letter — exactly one A4 sheet per student.
 *
 * Design language: contemporary international correspondence.
 *   - Single accent rule (navy) across the top of the page.
 *   - Compact, left-aligned letterhead (small logo, sans-serif name).
 *   - Generous whitespace, justified serif-free body.
 *   - Minimal ornamentation: no gradients, shadows, dashed shapes, or
 *     decorative typography — just thin rules and small-caps labels.
 *
 * @var array|null              $student
 * @var array<int, array>|null  $students
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

$today = date('F j, Y');

$letters = isset($students) && is_array($students)
    ? $students
    : (isset($student) && is_array($student) ? [$student] : []);

$classLabel = function (array $s): string {
    $cls = trim((string) ($s['class_name'] ?? ''));
    $lvl = trim((string) ($s['level'] ?? ''));
    return $cls !== '' ? $cls : ($lvl !== '' ? $lvl : '—');
};
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admission Letter · <?= View::e($schoolName) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    @page { size: A4; margin: 0; }

    :root {
      --ink:     #111827;
      --muted:   #6b7280;
      --soft:    #9ca3af;
      --hair:    #e5e7eb;
      --accent:  #0f172a;        /* deep navy ink */
      --accent2: #b08a37;        /* understated gold for the seal */
    }
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; }

    body {
      background: #f4f5f7;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      color: var(--ink);
      padding: 18px;
      font-feature-settings: "ss01","cv11","kern","liga";
      -webkit-font-smoothing: antialiased;
    }

    /* ----- A4 sheet ----- */
    .letter-page {
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
      font-size: 10.5pt;
      line-height: 1.55;
      color: var(--ink);
    }
    .letter-page:last-of-type { page-break-after: auto; margin-bottom: 0; }

    /* Single accent rule across the top of every sheet */
    .letter-page::before {
      content: "";
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 4px;
      background: var(--accent);
    }

    /* ----- Letterhead ----- */
    .head {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 22px;
      padding-bottom: 10px;
      border-bottom: 1px solid var(--hair);
    }
    .head__brand {
      display: flex;
      align-items: center;
      gap: 14px;
      flex: 1;
      min-width: 0;
    }
    .head__logo {
      width: 56px;
      height: 56px;
      object-fit: contain;
      flex-shrink: 0;
    }
    .head__logo--ph {
      width: 56px; height: 56px;
      border: 1px solid var(--hair);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      color: var(--soft);
      font-family: 'Cormorant Garamond', serif;
      font-size: 1.6rem;
      font-weight: 600;
    }
    .head__name {
      font-size: 14pt;
      font-weight: 700;
      letter-spacing: 0.04em;
      line-height: 1.15;
      text-transform: uppercase;
      color: var(--ink);
    }
    .head__motto {
      margin-top: 2px;
      font-family: 'Cormorant Garamond', serif;
      font-style: italic;
      font-size: 10.5pt;
      color: var(--muted);
      line-height: 1.2;
    }
    .head__contact {
      text-align: right;
      font-size: 8pt;
      color: var(--muted);
      line-height: 1.5;
      flex-shrink: 0;
    }
    .head__contact .row { white-space: nowrap; }
    .head__contact strong { color: var(--ink); font-weight: 600; }

    /* ----- Reference / date strip ----- */
    .meta {
      margin-top: 14px;
      display: flex;
      justify-content: space-between;
      gap: 24px;
      padding-bottom: 12px;
    }
    .meta__cell { display: flex; flex-direction: column; gap: 2px; }
    .meta__lbl {
      font-size: 7.5pt;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      color: var(--soft);
      font-weight: 600;
    }
    .meta__val { font-size: 10pt; font-weight: 600; color: var(--ink); }

    /* ----- Recipient ----- */
    .recipient {
      margin-bottom: 14px;
      line-height: 1.45;
      font-size: 10pt;
    }
    .recipient__lbl {
      font-size: 7.5pt;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      color: var(--soft);
      font-weight: 600;
      margin-bottom: 3px;
    }
    .recipient__name { font-weight: 600; }

    /* ----- Subject ----- */
    .subject {
      margin: 8px 0 14px;
      font-size: 11pt;
      font-weight: 600;
      color: var(--accent);
      letter-spacing: 0.06em;
    }
    .subject::before { content: "Subject — "; color: var(--soft); font-weight: 500; }

    /* ----- Body ----- */
    .body { flex: 1 1 auto; }
    .body p {
      font-size: 10.5pt;
      line-height: 1.62;
      margin: 0 0 10px;
      text-align: justify;
      hyphens: auto;
    }
    .body strong { font-weight: 600; }

    .name-tag {
      font-weight: 600;
      color: var(--accent);
    }

    /* ----- Particulars list ----- */
    .particulars {
      width: 100%;
      border-collapse: collapse;
      margin: 4px 0 12px;
      font-size: 9.5pt;
    }
    .particulars td {
      padding: 5px 0;
      border-bottom: 1px solid var(--hair);
      vertical-align: top;
    }
    .particulars td:first-child {
      width: 38%;
      font-size: 8pt;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: var(--soft);
      font-weight: 600;
      padding-right: 12px;
    }
    .particulars td:last-child {
      font-weight: 500;
      color: var(--ink);
    }

    /* ----- Sign-off ----- */
    .signoff {
      margin-top: 14px;
      display: flex;
      align-items: flex-end;
      justify-content: space-between;
      gap: 36px;
    }
    .signoff__col { line-height: 1.35; flex: 1; min-width: 0; }
    .signoff__sig-img {
      display: block;
      height: 46px;
      max-width: 220px;
      width: auto;
      object-fit: contain;
      margin-bottom: 2px;
    }
    .signoff__sig-line {
      border-top: 1px solid var(--ink);
      width: 220px;
      padding-top: 4px;
      font-size: 9.5pt;
      font-weight: 600;
    }
    .signoff__role {
      font-size: 7.5pt;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      color: var(--soft);
      margin-top: 1px;
    }

    /* Subtle round seal — flat, single colour outline only. No 3D, no glow. */
    .seal {
      width: 92px;
      height: 92px;
      border: 1px solid var(--accent2);
      border-radius: 50%;
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .seal::before {
      content: "";
      position: absolute;
      inset: 5px;
      border: 1px solid var(--accent2);
      border-radius: 50%;
    }
    .seal__text {
      font-size: 7pt;
      letter-spacing: 0.18em;
      color: var(--accent2);
      text-transform: uppercase;
      font-weight: 600;
      text-align: center;
      line-height: 1.2;
    }

    /* ----- Footer ----- */
    .foot {
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

    /* ----- Print ----- */
    @media print {
      body { background: #fff; padding: 0; }
      .letter-page {
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

<?php if (empty($letters)): ?>
  <div class="empty-state">
    <h2 style="margin-top:0; color: var(--accent);">No admission letters to print</h2>
    <p style="color: var(--muted);">There are no students matching the selected filter.</p>
    <button class="no-print" onclick="window.close()">Close</button>
  </div>
<?php else: foreach ($letters as $s):
  $studentName = trim((string) $s['first_name'] . ' ' . (string) $s['last_name']);
  $recipient = trim((string) ($s['guardian_name'] ?? ''));
  if ($recipient === '') $recipient = 'The Parent / Guardian of ' . $studentName;
  $recipientAddress = trim((string) ($s['address'] ?? ''));

  $level    = trim((string) ($s['level'] ?? ''));
  $section  = strtolower((string) ($s['section'] ?? 'day'));
  $sectionL = $section === 'boarding' ? 'Boarding' : 'Day';
  $stream   = strtolower((string) ($s['stream'] ?? 'none'));
  $streamL  = $stream === 'science' ? 'Science' : ($stream === 'arts' ? 'Arts' : '');
  $cls      = $classLabel($s);
  $admittedDate = !empty($s['created_at']) ? date('F j, Y', strtotime((string) $s['created_at'])) : $today;
?>
  <div class="letter-page">

    <!-- Letterhead -->
    <header class="head">
      <div class="head__brand">
        <?php if ($schoolLogo): ?>
          <img class="head__logo" src="<?= $base ?>/<?= View::e($schoolLogo) ?>" alt="">
        <?php else: ?>
          <span class="head__logo--ph">S</span>
        <?php endif; ?>
        <div style="min-width: 0;">
          <div class="head__name"><?= View::e($schoolName) ?></div>
          <?php if ($schoolMotto !== ''): ?>
            <div class="head__motto"><?= View::e($schoolMotto) ?></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="head__contact">
        <?php if ($schoolAddress !== ''): ?>
          <div class="row"><?= nl2br(View::e($schoolAddress)) ?></div>
        <?php endif; ?>
        <?php if ($schoolPhone !== ''): ?>
          <div class="row"><strong>T</strong> <?= View::e($schoolPhone) ?></div>
        <?php endif; ?>
        <?php if ($schoolEmail !== ''): ?>
          <div class="row"><strong>E</strong> <?= View::e($schoolEmail) ?></div>
        <?php endif; ?>
      </div>
    </header>

    <!-- Reference + date -->
    <div class="meta">
      <div class="meta__cell">
        <span class="meta__lbl">Our Reference</span>
        <span class="meta__val"><?= View::e($s['admission_no']) ?></span>
      </div>
      <div class="meta__cell" style="text-align: right;">
        <span class="meta__lbl">Date of Issue</span>
        <span class="meta__val"><?= View::e($today) ?></span>
      </div>
    </div>

    <!-- Recipient -->
    <div class="recipient">
      <div class="recipient__lbl">Addressed to</div>
      <div class="recipient__name"><?= View::e($recipient) ?></div>
      <?php if ($recipientAddress !== ''): ?>
        <div style="color: var(--muted);"><?= nl2br(View::e($recipientAddress)) ?></div>
      <?php endif; ?>
    </div>

    <!-- Subject -->
    <div class="subject">Offer of Admission for the <?= View::e(date('Y')) ?> Academic Year</div>

    <!-- Body -->
    <div class="body">
      <p>Dear <?= View::e($recipient) ?>,</p>

      <p>
        It gives me great pleasure to confirm, on behalf of <strong><?= View::e($schoolName) ?></strong>,
        the admission of
        <span class="name-tag"><?= View::e($studentName) ?></span>
        to our school. After a careful review of the application, the
        admissions committee is satisfied that the candidate meets the
        academic and character requirements for the place offered.
      </p>

      <table class="particulars">
        <tr><td>Full Name</td>           <td><?= View::e($studentName) ?></td></tr>
        <tr><td>Admission Number</td>    <td><?= View::e($s['admission_no']) ?></td></tr>
        <tr><td>Class</td>               <td><?= View::e($cls) ?><?= $level !== '' && $level !== $cls ? ' (' . View::e($level) . ')' : '' ?></td></tr>
        <tr><td>Section</td>             <td><?= View::e($sectionL) ?></td></tr>
        <?php if ($streamL !== ''): ?>
        <tr><td>Stream</td>              <td><?= View::e($streamL) ?></td></tr>
        <?php endif; ?>
        <tr><td>Date of Admission</td>   <td><?= View::e($admittedDate) ?></td></tr>
        <?php if (!empty($s['guardian_phone'])): ?>
        <tr><td>Guardian Telephone</td>  <td><?= View::e($s['guardian_phone']) ?></td></tr>
        <?php endif; ?>
      </table>

      <p>
        The student is expected to abide by the school rules at all times
        and to attend every scheduled class punctually and in the
        prescribed uniform. Examination permits are issued by the
        Bursar's Office only after the term's fees have been settled in
        full; please therefore ensure that fees are cleared with the
        Bursar before teaching begins.
      </p>

      <p style="margin-bottom: 0;">
        On behalf of the staff and students, I warmly welcome you to the
        <?= View::e($schoolName) ?> community and look forward to the
        candidate's contribution to our school.
      </p>

      <p style="margin: 24px 0 4px; color: var(--muted);">Yours sincerely,</p>
    </div>

    <!-- Signature row -->
    <div class="signoff">
      <div class="signoff__col">
        <?php if ($htSignature): ?>
          <img class="signoff__sig-img" src="<?= $base ?>/<?= View::e($htSignature) ?>" alt="">
        <?php endif; ?>
        <div class="signoff__sig-line"><?= View::e($htName !== '' ? $htName : '—') ?></div>
        <div class="signoff__role"><?= View::e($htTitle) ?></div>
      </div>

      <div class="seal">
        <span class="seal__text">Official<br>Seal</span>
      </div>
    </div>

    <!-- Footer -->
    <footer class="foot">
      <span>Issued <?= View::e($today) ?> · Ref <?= View::e($s['admission_no']) ?></span>
      <span>This letter is valid only with the school stamp and signature of the <?= View::e(strtolower($htTitle)) ?>.</span>
    </footer>
  </div>
<?php endforeach; endif; ?>

<?php if (!empty($letters)): ?>
  <div class="no-print" style="text-align:center; margin: 14px 0;">
    <button onclick="window.print()" style="background:#0f172a; color:#fff; border:0; padding:10px 22px; font-size:.95rem; letter-spacing: .04em; cursor:pointer;">
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
