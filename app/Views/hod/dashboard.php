<?php
use App\Core\View;

$layout = 'app';
$title  = 'Department Dashboard';

$catLabel = ['core' => 'Compulsory Core', 'science' => 'Science', 'arts' => 'Arts', 'optional' => 'Optional'];
$catShort = ['core' => 'Core', 'science' => 'Science', 'arts' => 'Arts', 'optional' => 'Optional'];

// Default academic year: Sept-Dec uses next year, Jan-Aug uses prior year.
$defaultYear = (date('n') >= 9)
    ? date('Y') . '/' . (date('Y') + 1)
    : (date('Y') - 1) . '/' . date('Y');
[$startStr] = explode('/', $defaultYear);
$start = (int) $startStr;

$availableYears = [];
for ($i = -2; $i <= 2; $i++) {
    $a = $start + $i;
    $availableYears[] = $a . '/' . ($a + 1);
}
$availableTerms = ['Term 1', 'Term 2', 'Term 3'];

$selYear = trim((string) ($_GET['year'] ?? ''));
$selTerm = trim((string) ($_GET['term'] ?? ''));
$periodSet = ($selYear !== '' && $selTerm !== ''
    && in_array($selYear, $availableYears, true)
    && in_array($selTerm, $availableTerms, true));
$year = $periodSet ? $selYear : $defaultYear;
$periodQs = $periodSet ? ('&year=' . rawurlencode($selYear) . '&term=' . rawurlencode($selTerm)) : '';

$formLabels = ['Form 1' => 'Form 1', 'Form 2' => 'Form 2', 'Form 3' => 'Form 3', 'Form 4' => 'Form 4'];
$formBadgeTones = ['Form 1' => 'orange', 'Form 2' => 'green', 'Form 3' => 'blue', 'Form 4' => 'purple'];

// Subjects grouped by category (for mark-entry lists under each class)
$subjectsByCategory = [];
foreach ($subjects as $_sub) {
    $ck = (string) ($_sub['category'] ?? 'optional');
    $subjectsByCategory[$ck][] = $_sub;
}

$h = (int) date('G');
$greeting  = $h < 12 ? 'Good morning' : ($h < 17 ? 'Good afternoon' : 'Good evening');
$greetIcon = $h < 12 ? 'bi-sunrise' : ($h < 17 ? 'bi-sun' : 'bi-moon-stars');
$greetTone = $h < 12 ? 'orange' : ($h < 17 ? 'yellow' : 'purple');
?>

<!-- ============================================================
     Hero greeting (slim, single line of meta)
     ============================================================ -->
<section class="dash-hero dash-hero--slim">
  <div class="dash-hero__content d-flex align-items-center gap-3">
    <span class="icon-chip icon-chip--<?= $greetTone ?> d-none d-sm-inline-grid">
      <i class="bi <?= $greetIcon ?>"></i>
    </span>
    <div class="flex-grow-1" style="min-width:0;">
      <h2 class="dash-hero__title">
        <?= $greeting ?>, <?= View::e($user['name']) ?>.
      </h2>

      <p class="dash-hero__sub">
        <span class="dash-hero__date">
          <i class="bi bi-calendar3"></i><?= date('D, M j, Y') ?>
        </span>

        <span class="dept-pills">
          <?php if ($isAdmin): ?>
            <span class="dept-pill dept-pill--admin">
              <i class="bi bi-shield-check"></i> Admin preview
            </span>
          <?php elseif (!empty($isSharedHod)): ?>
            <?php if (!empty($hodDepartmentLabel)): ?>
              <span class="dept-pill dept-pill--accent">
                <i class="bi bi-bookmark-star-fill"></i>
                <?= View::e($hodDepartmentLabel) ?>
              </span>
            <?php endif; ?>
            <span class="dept-pill dept-pill--accent">
              <i class="bi bi-people"></i> All forms &amp; subjects
            </span>
          <?php else: foreach ($categories as $cat): ?>
            <span class="dept-pill dept-pill--<?= View::e($cat) ?>">
              <i class="bi bi-bookmark-star-fill"></i>
              <?= View::e($catLabel[$cat] ?? $cat) ?>
            </span>
          <?php endforeach; endif; ?>
        </span>
      </p>
    </div>
  </div>

  <div class="dash-hero__actions">
    <a href="<?= $base ?>/hod/overview" class="btn btn-primary btn-sm">
      <i class="bi bi-bar-chart-steps"></i> Performance overview
    </a>
    <a href="<?= $base ?>/hod/marks" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-pencil-square"></i> Department marks
    </a>
    <a href="<?= $base ?>/hod/reports" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-file-earmark-text"></i> Reports
    </a>
    <a href="<?= $base ?>/hod/students" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-people"></i> Students
    </a>
  </div>
</section>

<!-- ============================================================
     Period bar (horizontal, single row)
     ============================================================ -->
<form method="get"
      action="<?= $base ?>/hod"
      class="period-bar <?= $periodSet ? 'period-bar--ok' : 'period-bar--warn' ?>">

  <span class="period-bar__icon">
    <i class="bi bi-calendar-event"></i>
  </span>

  <div class="period-bar__lead">
    <div class="period-bar__title">Marking period</div>
    <div class="period-bar__hint">
      <?= $periodSet
        ? 'All quick mark-entry links record under the active period.'
        : 'Pick year &amp; term to unlock the mark-entry links below.' ?>
    </div>
  </div>

  <div class="period-bar__field">
    <label class="form-label small mb-1">Academic year</label>
    <select name="year" class="form-select form-select-sm" required>
      <option value="">— Year —</option>
      <?php foreach ($availableYears as $y): ?>
        <option value="<?= View::e($y) ?>" <?= $y === $selYear ? 'selected' : '' ?>>
          <?= View::e($y) ?><?= $y === $defaultYear ? ' (current)' : '' ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="period-bar__field">
    <label class="form-label small mb-1">Term</label>
    <select name="term" class="form-select form-select-sm" required>
      <option value="">— Term —</option>
      <?php foreach ($availableTerms as $t): ?>
        <option value="<?= View::e($t) ?>" <?= $t === $selTerm ? 'selected' : '' ?>>
          <?= View::e($t) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <button class="btn btn-primary btn-sm">
    <i class="bi bi-check2-circle"></i>
    <?= $periodSet ? 'Update' : 'Set' ?>
  </button>

  <span class="period-bar__status">
    <?php if ($periodSet): ?>
      <i class="bi bi-check2-circle-fill"></i>
      <span><strong><?= View::e($selYear) ?></strong> &middot; <?= View::e($selTerm) ?></span>
    <?php else: ?>
      <i class="bi bi-exclamation-triangle-fill"></i>
      <span>Period not set</span>
    <?php endif; ?>
  </span>
</form>

<!-- ============================================================
     KPI strip
     ============================================================ -->
<div class="section-block mb-2">
  <div class="section-block__head">
    <div>
      <h3 class="section-block__title"><i class="bi bi-bar-chart-line"></i> Department at a glance</h3>
      <p class="section-block__sub">Counts across the departments and forms you oversee.</p>
    </div>
  </div>
</div>
<div class="row g-3 mb-4">
  <?php
    $kpis = [
      ['Departments', (int) $stats['departments'], 'bi-bookmark-star',     'purple', '#departments'],
      ['Subjects',    (int) $stats['subjects'],    'bi-book-half',         'blue',   '#subjects'],
      ['Teachers',    (int) $stats['teachers'],    'bi-person-workspace',  'green',  '#teachers'],
      ['Classes',     (int) $stats['classes'],     'bi-building-fill',     'yellow', '#classes'],
      ['Students',    (int) $stats['students'],    'bi-people-fill',       'orange', '#classes'],
    ];
    foreach ($kpis as [$label, $value, $icon, $tone, $href]):
  ?>
    <div class="col-6 col-md-4 col-xl">
      <a href="<?= View::e($href) ?>" class="kpi-card kpi-card--compact kpi-card--xs">
        <div class="kpi-card__icon kpi-card__icon--<?= View::e($tone) ?>">
          <i class="bi <?= View::e($icon) ?>"></i>
        </div>
        <div class="kpi-card__body">
          <div class="kpi-card__label"><?= View::e($label) ?></div>
          <div class="kpi-card__value"><?= number_format($value) ?></div>
        </div>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<!-- ============================================================
     Department mark entry — four columns (Form 1–4), classes stacked,
     categories & subjects listed under each class
     ============================================================ -->
<div class="section-block mb-3" id="upload-marks">
  <div class="section-block__head">
    <div>
      <h3 class="section-block__title">
        <i class="bi bi-ui-checks-grid"></i> Department mark entry
      </h3>
      <p class="section-block__sub">
        One column per form. Under each class: matrix links by category, then subjects grouped by category.
      </p>
    </div>
  </div>

  <div class="row row-cols-1 row-cols-lg-2 row-cols-xl-4 g-3 hod-mark-entry-grid">
    <?php foreach ($formLabels as $formKey => $formTitle):
      $rows = $classesByForm[$formKey] ?? [];
      $totalFormStudents = 0;
      foreach ($rows as $rc) { $totalFormStudents += (int) $rc['student_count']; }
      $badgeTone = $formBadgeTones[$formKey] ?? 'purple';
      $formSlug = 'hod-form-' . preg_replace('/\s+/', '-', strtolower($formKey));
    ?>
      <div class="col d-flex">
        <div class="form-card hod-mark-column w-100" id="<?= View::e($formSlug) ?>">
          <div class="form-card__head">
            <span class="form-card__badge form-card__badge--<?= View::e($badgeTone) ?>"><?= View::e($formTitle) ?></span>
            <?php if (empty($rows)): ?>
              <span class="form-card__meta form-card__meta--warn">
                <i class="bi bi-exclamation-circle"></i> No class
              </span>
            <?php else: ?>
              <span class="form-card__meta">
                <i class="bi bi-people"></i>
                <?= number_format($totalFormStudents) ?>
              </span>
            <?php endif; ?>
          </div>

          <div class="form-card__body hod-mark-column__body">
            <?php if (empty($rows)): ?>
              <div class="form-card__empty">
                Add a class with level &ldquo;<?= View::e($formKey) ?>&rdquo;
                <?php if (!empty($isAdmin)): ?>
                  under <a href="<?= $base ?>/classes">Classes</a>
                <?php endif; ?>
                so students can be enrolled and marked.
              </div>
            <?php else: foreach ($rows as $c):
              $reportTerm = $periodSet ? $selTerm : 'Term 1';
              $reportHref = $base . $portalPrefix . '/reports/class/' . (int) $c['id']
                          . '?year=' . rawurlencode($year) . '&term=' . rawurlencode($reportTerm);
            ?>
              <div class="form-card__class hod-mark-class">
                <div class="form-card__class-head<?= !empty($subjects) ? ' form-card__class-head--theme' : '' ?>">
                  <div class="form-card__class-title">
                    <span class="form-card__class-name"><?= View::e($c['name']) ?></span>
                    <span class="badge-soft"><?= (int) $c['student_count'] ?></span>
                  </div>
                  <a class="form-card__icon-link"
                     href="<?= View::e($reportHref) ?>"
                     title="Print class report">
                    <i class="bi bi-printer"></i>
                  </a>
                </div>

                <?php if (!empty($c['class_teacher']) && trim((string) $c['class_teacher']) !== ''): ?>
                  <div class="form-card__class-sub">
                    <i class="bi bi-person"></i>
                    <?= View::e(trim((string) $c['class_teacher'])) ?>
                  </div>
                <?php endif; ?>

                <div class="hod-mark-class__section">
                  <div class="hod-mark-class__label">Matrix (whole category)</div>
                  <div class="subject-chips subject-chips--cats hod-mark-class__chips">
                    <?php foreach ($categories as $cat):
                      $href = $base . $portalPrefix . '/marks/department?class_id=' . (int) $c['id']
                            . '&category=' . rawurlencode($cat) . $periodQs;
                    ?>
                      <?php if ($periodSet): ?>
                        <a class="subject-chip subject-chip--<?= View::e($cat) ?> subject-chip--solid"
                           href="<?= View::e($href) ?>">
                          <?= View::e($catShort[$cat] ?? $cat) ?>
                        </a>
                      <?php else: ?>
                        <span class="subject-chip subject-chip--disabled"
                              title="Choose academic year and term first">
                          <?= View::e($catShort[$cat] ?? $cat) ?>
                        </span>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </div>
                </div>

                <?php foreach ($categories as $cat):
                  $catSubs = $subjectsByCategory[$cat] ?? [];
                  if (empty($catSubs)) {
                      continue;
                  }
                ?>
                  <div class="hod-mark-class__section">
                    <div class="hod-mark-class__label"><?= View::e($catLabel[$cat] ?? ucfirst($cat)) ?></div>
                    <div class="subject-chips hod-mark-class__chips">
                      <?php foreach ($catSubs as $sub):
                        $href = $base . $portalPrefix . '/marks/entry?class_id=' . (int) $c['id']
                              . '&subject_id=' . (int) $sub['id'] . $periodQs;
                        $subCat = (string) ($sub['category'] ?? 'optional');
                      ?>
                        <?php if ($periodSet): ?>
                          <a class="subject-chip subject-chip--<?= View::e($subCat) ?>"
                             href="<?= View::e($href) ?>"
                             title="Enter <?= View::e($sub['name']) ?> marks">
                            <?= View::e($sub['name']) ?>
                          </a>
                        <?php else: ?>
                          <span class="subject-chip subject-chip--disabled"
                                title="Choose academic year and term first">
                            <?= View::e($sub['name']) ?>
                          </span>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ============================================================
     Subjects + Teachers (2-col, internally scrollable)
     ============================================================ -->
<div class="section-block mb-2">
  <div class="section-block__head">
    <div>
      <h3 class="section-block__title"><i class="bi bi-people"></i> People &amp; subjects</h3>
      <p class="section-block__sub">Subjects in your department(s) and the teachers assigned to them.</p>
    </div>
  </div>
</div>
<div class="row g-3">
  <div class="col-xl-6" id="subjects">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between py-2">
        <span class="fw-semibold">
          <span class="card-header-icon card-header-icon--blue" aria-hidden="true"><i class="bi bi-book-half"></i></span>Subjects
        </span>
        <span class="small text-muted">
          <?= count($subjects) ?> in your department(s)
        </span>
      </div>
      <?php if (empty($subjects)): ?>
        <div class="card-body py-3">
          <div class="empty-state py-3">
            <i class="bi bi-inbox d-block"></i>
            <div>No subjects yet.</div>
          </div>
        </div>
      <?php else: ?>
        <ul class="recent-list scroll-list">
          <?php foreach ($subjects as $sub):
            $cat = (string) ($sub['category'] ?? 'optional');
          ?>
            <li class="recent-list__item recent-list__item--tight">
              <div class="recent-list__avatar avatar--<?= View::e($cat) ?>">
                <i class="bi bi-book"></i>
              </div>
              <div class="recent-list__body">
                <div class="recent-list__name">
                  <?= View::e($sub['name']) ?>
                  <?php if (!empty($sub['code'])): ?>
                    <span class="text-muted small fw-normal">(<?= View::e($sub['code']) ?>)</span>
                  <?php endif; ?>
                </div>
                <div class="recent-list__meta">
                  <?php if (!empty($sub['teachers'])): ?>
                    <i class="bi bi-person"></i>
                    <span class="text-muted text-truncate"><?= View::e($sub['teachers']) ?></span>
                  <?php else: ?>
                    <span class="text-warning">
                      <i class="bi bi-exclamation-triangle"></i> No teachers assigned
                    </span>
                  <?php endif; ?>
                </div>
              </div>
              <span class="dept-pill dept-pill--<?= View::e($cat) ?> dept-pill--sm">
                <?= View::e($catShort[$cat] ?? $cat) ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-xl-6" id="teachers">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between py-2">
        <span class="fw-semibold">
          <span class="card-header-icon card-header-icon--green" aria-hidden="true"><i class="bi bi-person-workspace"></i></span>Teachers
        </span>
        <span class="small text-muted">
          <?= count($teachers) ?> teaching in your department(s)
        </span>
      </div>
      <?php if (empty($teachers)): ?>
        <div class="card-body py-3">
          <div class="empty-state py-3">
            <i class="bi bi-inbox d-block"></i>
            <div>No teachers assigned yet.</div>
          </div>
        </div>
      <?php else: ?>
        <ul class="recent-list scroll-list">
          <?php foreach ($teachers as $t):
            $first = (string) ($t['first_name'] ?? '');
            $last  = (string) ($t['last_name']  ?? '');
            $initials = strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1));
            if ($initials === '') { $initials = '?'; }
          ?>
            <li class="recent-list__item recent-list__item--tight">
              <div class="recent-list__avatar"><?= View::e($initials) ?></div>
              <div class="recent-list__body">
                <div class="recent-list__name">
                  <?= View::e(trim($first . ' ' . $last)) ?>
                  <?php if (!empty($t['position'])): ?>
                    <span class="text-muted small fw-normal">&middot; <?= View::e($t['position']) ?></span>
                  <?php endif; ?>
                </div>
                <div class="recent-list__meta">
                  <i class="bi bi-book text-muted"></i>
                  <span class="text-muted text-truncate"><?= View::e($t['subjects']) ?></span>
                </div>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ============================================================
     Recently saved marks (compact, only if any)
     ============================================================ -->
<?php if (!empty($recent)): ?>
  <div class="card mt-3">
    <div class="card-header py-2 d-flex align-items-center justify-content-between">
      <span class="fw-semibold">
        <span class="card-header-icon card-header-icon--purple" aria-hidden="true"><i class="bi bi-clock-history"></i></span>Recently saved marks
      </span>
      <span class="small text-muted">Your last <?= count($recent) ?> entries</span>
    </div>
    <div class="table-responsive scroll-list scroll-list--xs">
      <table class="table table-sm table-hover mb-0 align-middle dash-table">
        <thead>
          <tr>
            <th>When</th>
            <th>Student</th>
            <th>Subject</th>
            <th>Period</th>
            <th class="text-end">Score</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent as $g):
            $score = (float) $g['score'];
            $scoreCls = $score >= 80 ? 'score-badge--gold'
                     : ($score >= 60 ? 'score-badge--good'
                     : ($score >= 40 ? 'score-badge--ok' : 'score-badge--low'));
            $scoreFmt = rtrim(rtrim(number_format($score, 2, '.', ''), '0'), '.');
          ?>
            <tr>
              <td class="small text-muted text-nowrap">
                <?= View::e(date('M j, H:i', strtotime((string) $g['created_at']))) ?>
              </td>
              <td><?= View::e(trim($g['first_name'] . ' ' . $g['last_name'])) ?></td>
              <td><?= View::e($g['subject_name']) ?></td>
              <td class="small text-muted">
                <?= View::e($g['academic_year']) ?> &middot;
                <?= View::e($g['term']) ?> &middot;
                <?= View::e(ucfirst($g['exam_type'])) ?>
              </td>
              <td class="text-end">
                <span class="score-badge <?= $scoreCls ?>"><?= View::e($scoreFmt) ?></span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
