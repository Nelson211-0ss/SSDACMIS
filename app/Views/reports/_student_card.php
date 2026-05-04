<?php
/**
 * Single-student report — shares the same visual system as the class
 * result matrix: .report-page, .report-head, .report-meta, .report-table.
 *
 * Expects: $student, $sheet, $position, $year, $term, $base,
 *          $schoolName, $schoolMotto, $schoolLogo
 */
use App\Core\View;
use App\Core\Settings;

// Optional scanned head-teacher signature — printed on the "Head teacher"
// signature slot when uploaded in admin Settings. Falls back to a blank
// line when none on file.
$htSignature = Settings::headteacherSignatureUrl();
$htName      = trim((string) (Settings::get('school_headteacher_name') ?? ''));

$rows = [];
foreach (($sheet['groups'] ?? []) as $grp) {
    foreach (($grp['rows'] ?? []) as $r) {
        $rows[] = $r;
    }
}

$studentStream = $student['stream'] ?? 'none';
$cohortLabel   = $position['cohort_label'] ?? 'class';
$fullName      = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
$teacherName   = trim(($student['teacher_first'] ?? '') . ' ' . ($student['teacher_last'] ?? ''));
$termCode = preg_match('/(\d+)/', (string) $term, $m) ? 'T' . $m[1] : (string) $term;
$yearCode = preg_replace('/[^0-9\/\-]/', '', (string) $year);
$refCode  = strtoupper(($student['admission_no'] ?? '—') . '/' . $termCode . '/' . $yearCode);
$sectionDisplay = trim((string) ($student['section'] ?? '')) !== ''
    ? View::studentEnumUpper('section', $student['section'])
    : '';
$genderDisplay = trim((string) ($student['gender'] ?? '')) !== ''
    ? View::studentEnumUpper('gender', $student['gender'])
    : '';
?>
<div class="report-page report-page--student">
  <header class="report-head">
    <div class="report-head__brand">
      <?php if (!empty($schoolLogo)): ?>
        <img src="<?= $base ?>/<?= View::e($schoolLogo) ?>" alt="">
      <?php else: ?>
        <i class="bi bi-mortarboard-fill" aria-hidden="true"></i>
      <?php endif; ?>
      <div>
        <div class="report-head__school"><?= View::e($schoolName) ?></div>
        <?php if (!empty($schoolMotto)): ?>
          <div class="report-head__motto fst-italic"><?= View::e($schoolMotto) ?></div>
        <?php endif; ?>
        <div class="report-head__sub">
          Student report card &middot; REF: <?= View::e($refCode) ?>
        </div>
      </div>
    </div>
    <div class="report-head__period">
      <div><strong>Year:</strong> <?= View::e($year) ?: '—' ?></div>
      <div><strong>Term:</strong> <?= View::e($term) ?: '—' ?></div>
      <div><strong>Class teacher:</strong> <?= View::e($teacherName) ?: '—' ?></div>
    </div>
  </header>

  <div class="report-meta report-meta--student" style="display:flex; gap: 16px; align-items: flex-start;">
    <?php
      $photo = trim((string) ($student['photo_path'] ?? ''));
    ?>
    <div class="report-student__photo" style="flex: 0 0 auto;">
      <?php if ($photo !== ''): ?>
        <img src="<?= View::e($base . '/' . ltrim($photo, '/')) ?>" alt=""
             style="width: 92px; height: 120px; object-fit: cover; border: 1px solid #1f2937; border-radius: 4px; background: #f1f5f9;">
      <?php else: ?>
        <div style="width: 92px; height: 120px; border: 1px dashed #94a3b8; border-radius: 4px;
                    display: flex; align-items: center; justify-content: center;
                    color: #64748b; font-size: 0.7rem; text-align: center; padding: 4px;
                    background: #f8fafc;">
          PASSPORT<br>PHOTO
        </div>
      <?php endif; ?>
    </div>
    <div class="report-meta__fields" style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px 16px; flex: 1 1 auto;">
      <div>
        <span class="meta-label">Student</span>
        <span class="meta-value"><?= View::e($fullName) ?: '—' ?></span>
      </div>
      <div>
        <span class="meta-label">Admission no.</span>
        <span class="meta-value font-monospace"><?= View::e($student['admission_no'] ?? '—') ?></span>
      </div>
      <div>
        <span class="meta-label">Class</span>
        <span class="meta-value"><?= View::e($student['class_name'] ?? '—') ?></span>
      </div>
      <div>
        <span class="meta-label">Stream</span>
        <span class="meta-value"><?= View::studentEnumUpper('stream', $studentStream) ?></span>
      </div>
      <?php if ($sectionDisplay !== ''): ?>
        <div>
          <span class="meta-label">Section</span>
          <span class="meta-value"><?= $sectionDisplay ?></span>
        </div>
      <?php endif; ?>
      <?php if ($genderDisplay !== ''): ?>
        <div>
          <span class="meta-label">Gender</span>
          <span class="meta-value"><?= $genderDisplay ?></span>
        </div>
      <?php endif; ?>
      <?php if (!empty($student['guardian_name'])): ?>
        <div style="grid-column: 1 / -1;">
          <span class="meta-label">Guardian</span>
          <span class="meta-value"><?= View::e($student['guardian_name']) ?></span>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <h3 class="report-student__section-h">Subject performance</h3>
  <table class="report-table report-student__marks">
    <thead>
      <tr>
        <th>Subject</th>
        <th class="t-num">Mid</th>
        <th class="t-num">End</th>
        <th class="t-num">Total</th>
        <th class="t-grade">Gr</th>
        <th class="t-remark">Remark</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr class="report-student__marks-row report-student__marks-row--empty">
          <td colspan="6" class="text-center text-muted">No marks recorded for this period yet.</td>
        </tr>
      <?php else: foreach ($rows as $r):
        $grade = (string) ($r['grade'] ?? '');
        $gKey  = strtoupper(substr($grade, 0, 1));
        if (!in_array($gKey, ['A', 'B', 'C', 'D', 'F'], true)) {
            $gKey = '';
        }
        ?>
        <tr class="report-student__marks-row">
          <td><?= View::e($r['subject']) ?></td>
          <td class="t-num"><?= $r['midterm'] !== null ? number_format((float) $r['midterm'], 1) : '—' ?></td>
          <td class="t-num"><?= $r['endterm'] !== null ? number_format((float) $r['endterm'], 1) : '—' ?></td>
          <td class="t-num">
            <?= $r['average'] !== null
                 ? '<strong>' . number_format((float) $r['average'], 1) . '</strong>'
                 : '—' ?>
          </td>
          <td class="t-grade">
            <?php if ($grade !== ''): ?>
              <span class="report-grade-pill" data-grade="<?= View::e($gKey) ?>"><?= View::e($grade) ?></span>
            <?php else: ?>
              <span class="report-grade-pill report-grade-pill--none">—</span>
            <?php endif; ?>
          </td>
          <td class="t-remark"><?= View::e($r['remark']) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>

  <?php if (!empty($sheet['count']) && (int) $sheet['count'] > 0):
    $overallGrade = (string) ($sheet['grade'] ?? '');
    $overallKey   = strtoupper(substr($overallGrade, 0, 1));
    if (!in_array($overallKey, ['A', 'B', 'C', 'D', 'F'], true)) {
        $overallKey = '';
    }
  ?>
    <h3 class="report-student__section-h">Summary</h3>
    <table class="report-table report-student__summary">
      <thead>
        <tr>
          <th class="t-num">Subjects</th>
          <th class="t-num">Total</th>
          <th class="t-num">Average</th>
          <th class="t-grade">Gr</th>
          <th>Position in <?= View::e($cohortLabel) ?></th>
        </tr>
      </thead>
      <tbody>
        <tr class="report-student__summary-row">
          <td class="t-num"><strong><?= (int) $sheet['count'] ?></strong></td>
          <td class="t-num"><strong><?= number_format((float) $sheet['total'], 0) ?></strong></td>
          <td class="t-num"><strong><?= number_format((float) $sheet['average'], 1) ?>%</strong></td>
          <td class="t-grade">
            <span class="report-grade-pill report-grade-pill--large" data-grade="<?= View::e($overallKey) ?>"><?= View::e($overallGrade ?: '—') ?></span>
          </td>
          <td>
            <?php if (!empty($position['position'])): ?>
              <strong><?= (int) $position['position'] ?></strong> / <?= (int) $position['cohort'] ?>
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
        </tr>
      </tbody>
    </table>
  <?php endif; ?>

  <div class="report-signature-row">
    <div>
      <div class="report-signature-line"></div>
      <div class="report-signature-lbl">Class teacher</div>
    </div>
    <div>
      <?php if ($htSignature): ?>
        <img class="report-signature-img" src="<?= $base ?>/<?= View::e($htSignature) ?>" alt="">
      <?php else: ?>
        <div class="report-signature-line"></div>
      <?php endif; ?>
      <div class="report-signature-lbl">
        Head teacher<?= $htName !== '' ? ' · <strong>' . View::e($htName) . '</strong>' : '' ?>
      </div>
    </div>
    <div>
      <div class="report-signature-line"></div>
      <div class="report-signature-lbl">Parent / Guardian</div>
    </div>
  </div>

  <div class="report-student__foot-block">
    <footer class="report-footer report-student__foot-legend">
      <span>Grading: <span class="report-grade-pill" data-grade="A">A</span> 80+ ·
      <span class="report-grade-pill" data-grade="B">B</span> 70–79 ·
      <span class="report-grade-pill" data-grade="C">C</span> 60–69 ·
      <span class="report-grade-pill" data-grade="D">D</span> 50–59 ·
      <span class="report-grade-pill" data-grade="F">F</span> &lt; 50</span>
      <span>Issued: <?= date('d M Y') ?></span>
    </footer>
    <p class="report-credit">
      SSD-ACMIS by Nelson O. Ochan &middot; SSD-iT Solutions
    </p>
  </div>
</div>
