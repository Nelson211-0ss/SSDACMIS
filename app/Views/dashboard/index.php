<?php
use App\Core\View;

$layout = 'app';
$title  = 'Dashboard';

$role        = $auth['role'] ?? 'guest';
$isAdmin     = !empty($isAdmin) || $role === 'admin';
$isAdminish  = in_array($role, ['admin', 'staff'], true);
$adminOps    = $adminOps ?? [];

$hodCount            = (int) ($adminOps['hod_count'] ?? 0);
$bursarCount         = (int) ($adminOps['bursar_count'] ?? 0);
$teachingCount       = (int) ($adminOps['teaching_assignments'] ?? 0);
$unassignedCount     = (int) ($adminOps['unassigned_students'] ?? 0);
$attToday            = $adminOps['attendance_today'] ?? ['present' => 0, 'absent' => 0, 'late' => 0, 'total' => 0];
$attPresent          = (int) ($attToday['present'] ?? 0);
$attAbsent           = (int) ($attToday['absent'] ?? 0);
$attLate             = (int) ($attToday['late'] ?? 0);
$attTotal            = (int) ($attToday['total'] ?? 0);
$attRate             = $attTotal > 0 ? (int) round(($attPresent / $attTotal) * 100) : null;

$feesSnap            = $adminOps['fees'] ?? null;
$feesExpected        = (float) ($feesSnap['expected'] ?? 0);
$feesCollected       = (float) ($feesSnap['collected'] ?? 0);
$feesOutstanding     = (float) ($feesSnap['outstanding'] ?? 0);
$feesPaidCount       = (int) ($feesSnap['paid_count'] ?? 0);
$feesPartialCount    = (int) ($feesSnap['partial_count'] ?? 0);
$feesUnpaidCount     = (int) ($feesSnap['unpaid_count'] ?? 0);
$feesYear            = (string) ($feesSnap['year'] ?? '');
$feesTerm            = (string) ($feesSnap['term'] ?? '');
$feesCollectedPct    = $feesExpected > 0 ? min(100, round(($feesCollected / $feesExpected) * 100, 1)) : 0;
$recentPaymentsAdmin = $adminOps['recent_payments'] ?? [];

$studentsTotal   = (int) ($stats['students'] ?? 0);
$staffTotal      = (int) ($stats['staff']    ?? 0);
$classesTotal    = (int) ($stats['classes']  ?? 0);
$subjectsTotal   = (int) ($stats['subjects'] ?? 0);
$subjectsOffered = (int) ($deltas['subjects_offered'] ?? $subjectsTotal);

$studThisMonth  = (int) ($deltas['students_this_month'] ?? 0);
$studLastMonth  = (int) ($deltas['students_last_month'] ?? 0);
$staffThisMonth = (int) ($deltas['staff_this_month']    ?? 0);

$studDeltaDir = $studThisMonth > $studLastMonth ? 'up'
              : ($studThisMonth < $studLastMonth ? 'down' : 'flat');

// ---- Class distribution (drives the bar chart) ----
$classLabels = array_column($classDistribution ?? [], 'name');
$classCounts = array_map(fn($r) => (int) $r['total'], $classDistribution ?? []);
$classLevels = array_column($classDistribution ?? [], 'level');
$classTotal  = array_sum($classCounts);

// ---- Demographics ----
$gMale   = (int) ($genderBreakdown['male']   ?? 0);
$gFemale = (int) ($genderBreakdown['female'] ?? 0);
$gOther  = (int) ($genderBreakdown['other']  ?? 0);
$gTotal  = $gMale + $gFemale + $gOther;

$sectionDay      = (int) ($sectionBreakdown['day']      ?? 0);
$sectionBoarding = (int) ($sectionBreakdown['boarding'] ?? 0);
$streamScience   = (int) ($streamBreakdown['science']   ?? 0);
$streamArts      = (int) ($streamBreakdown['arts']      ?? 0);
$sectionTotal    = $sectionDay + $sectionBoarding;
?>

<?php
// Time-of-day greeting for a warmer hero
$h = (int) date('G');
$greeting   = $h < 12 ? 'Good morning' : ($h < 17 ? 'Good afternoon' : 'Good evening');
$greetIcon  = $h < 12 ? 'bi-sunrise'   : ($h < 17 ? 'bi-sun'         : 'bi-moon-stars');
$greetTone  = $h < 12 ? 'orange'       : ($h < 17 ? 'yellow'         : 'purple');
?>

<div class="<?= $isAdmin ? 'admin-dash' : '' ?>">

<!-- ============================================================
     Hero / greeting (compact, with inline quick actions)
     ============================================================ -->
<section class="dash-hero<?= $isAdmin ? ' dash-hero--admin' : '' ?>">
  <div class="dash-hero__content d-flex align-items-center gap-3">
    <span class="icon-chip icon-chip--<?= $greetTone ?> d-none d-sm-inline-grid">
      <i class="bi <?= $greetIcon ?>"></i>
    </span>
    <div class="flex-grow-1" style="min-width:0;">
      <h2 class="dash-hero__title">
        <?= $greeting ?>, <?= View::e($auth['name']) ?>.
        <?php if ($isAdmin): ?>
          <span class="dash-hero__role">Administrator</span>
        <?php endif; ?>
      </h2>
      <p class="dash-hero__sub">
        <span class="dash-hero__date">
          <i class="bi bi-calendar3"></i><?= date('l, M j, Y') ?>
        </span>
        <?php if ($isAdmin): ?>
          <span class="dash-hero__inline">
            <i class="bi bi-shield-check"></i>
            School-wide control centre
          </span>
        <?php endif; ?>
        <?php if ($isAdminish): ?>
          <span class="dash-hero__inline">
            <i class="bi bi-people"></i>
            <?= number_format($studentsTotal) ?>
            student<?= $studentsTotal === 1 ? '' : 's' ?>
          </span>
          <span class="dash-hero__inline">
            <i class="bi bi-building"></i>
            <?= number_format($classesTotal) ?>
            class<?= $classesTotal === 1 ? '' : 'es' ?>
          </span>
        <?php endif; ?>
      </p>
    </div>
  </div>

  <?php if ($isAdminish): ?>
    <div class="dash-hero__actions">
      <a href="<?= $base ?>/students/create" class="btn btn-primary btn-sm">
        <i class="bi bi-person-plus"></i> Add student
      </a>
      <?php if ($isAdmin): ?>
        <a href="<?= $base ?>/teaching" class="btn btn-sm dash-hero__btn dash-hero__btn--attendance">
          <i class="bi bi-diagram-3"></i> Teaching
        </a>
        <a href="<?= $base ?>/settings" class="btn btn-sm dash-hero__btn dash-hero__btn--marks">
          <i class="bi bi-gear"></i> Settings
        </a>
      <?php else: ?>
        <a href="<?= $base ?>/attendance"
           class="btn btn-sm dash-hero__btn dash-hero__btn--attendance">
          <i class="bi bi-calendar-check"></i> Attendance
        </a>
        <a href="<?= $base ?>/marks"
           class="btn btn-sm dash-hero__btn dash-hero__btn--marks">
          <i class="bi bi-pencil-square"></i> Marks
        </a>
      <?php endif; ?>
      <a href="<?= $base ?>/announcements"
         class="btn btn-sm dash-hero__btn dash-hero__btn--announce">
        <i class="bi bi-megaphone"></i> Announce
      </a>
    </div>
  <?php endif; ?>
</section>


<?php if ($isAdminish): ?>
  <!-- ============================================================
       KPI cards (compact)
       ============================================================ -->
  <div class="section-block mb-2">
    <div class="section-block__head">
      <div>
        <h3 class="section-block__title"><i class="bi bi-bar-chart-line"></i> At a glance</h3>
        <p class="section-block__sub">
          <?php if ($isAdmin): ?>
            Key counts and operations across the school today.
          <?php else: ?>
            Key counts across the school today.
          <?php endif; ?>
        </p>
      </div>
    </div>
  </div>
  <div class="dash-kpi-grid mb-4<?= $isAdmin ? ' dash-kpi-grid--8' : ' dash-kpi-grid--4' ?>">
    <div class="dash-kpi-grid__item">
      <a href="<?= $base ?>/students" class="kpi-card kpi-card--compact">
        <div class="kpi-card__icon kpi-card__icon--orange"><i class="bi bi-people-fill"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Students</div>
          <div class="kpi-card__value"><?= number_format($studentsTotal) ?></div>
          <div class="kpi-card__delta kpi-card__delta--<?= $studDeltaDir ?>">
            <?php if ($studDeltaDir === 'up'): ?><i class="bi bi-arrow-up-right"></i>
            <?php elseif ($studDeltaDir === 'down'): ?><i class="bi bi-arrow-down-right"></i>
            <?php else: ?><i class="bi bi-dash"></i>
            <?php endif; ?>
            <?= number_format($studThisMonth) ?> this month
          </div>
        </div>
      </a>
    </div>

    <div class="dash-kpi-grid__item">
      <a href="<?= $base ?>/staff" class="kpi-card kpi-card--compact">
        <div class="kpi-card__icon kpi-card__icon--green"><i class="bi bi-person-workspace"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Staff</div>
          <div class="kpi-card__value"><?= number_format($staffTotal) ?></div>
          <div class="kpi-card__delta kpi-card__delta--<?= $staffThisMonth > 0 ? 'up' : 'flat' ?>">
            <?php if ($staffThisMonth > 0): ?>
              <i class="bi bi-arrow-up-right"></i>
              <?= number_format($staffThisMonth) ?> this month
            <?php else: ?>
              <i class="bi bi-dash"></i> No new this month
            <?php endif; ?>
          </div>
        </div>
      </a>
    </div>

    <div class="dash-kpi-grid__item">
      <a href="<?= $base ?>/classes" class="kpi-card kpi-card--compact">
        <div class="kpi-card__icon kpi-card__icon--blue"><i class="bi bi-building-fill"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Classes</div>
          <div class="kpi-card__value"><?= number_format($classesTotal) ?></div>
          <?php $avg = $classesTotal > 0 ? (int) round($studentsTotal / $classesTotal) : 0; ?>
          <div class="kpi-card__delta kpi-card__delta--flat">
            <i class="bi bi-people"></i> ~<?= number_format($avg) ?> / class
          </div>
        </div>
      </a>
    </div>

    <div class="dash-kpi-grid__item">
      <a href="<?= $base ?>/subjects" class="kpi-card kpi-card--compact">
        <div class="kpi-card__icon kpi-card__icon--purple"><i class="bi bi-book-half"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Subjects</div>
          <div class="kpi-card__value"><?= number_format($subjectsTotal) ?></div>
          <div class="kpi-card__delta kpi-card__delta--flat">
            <i class="bi bi-check2-circle"></i>
            <?= number_format($subjectsOffered) ?> offered
          </div>
        </div>
      </a>
    </div>
    <?php if ($isAdmin): include __DIR__ . '/_admin_kpi_ops.php'; endif; ?>
  </div>

  <?php if ($isAdmin): include __DIR__ . '/_admin_ops.php'; endif; ?>

  <!-- ============================================================
       Charts row: Enrollment per class + Demographics
       ============================================================ -->
  <div class="section-block mb-2">
    <div class="section-block__head">
      <div>
        <h3 class="section-block__title"><i class="bi bi-graph-up-arrow"></i> Insights</h3>
        <p class="section-block__sub">Enrollment, demographics, and section distribution.</p>
      </div>
    </div>
  </div>
  <div class="row g-3 mb-4">
    <div class="col-xl-6">
      <div class="card chart-card h-100">
        <div class="card-body">
          <div class="chart-card__head">
            <div>
              <h3 class="chart-card__title">Enrollment overview</h3>
              <p class="chart-card__sub">Students currently enrolled in each class</p>
            </div>
            <div class="chart-card__badge">
              <i class="bi bi-bar-chart-fill"></i>
              <?= number_format($classTotal) ?> enrolled
            </div>
          </div>
          <div class="chart-wrap chart-wrap--md">
            <?php if (empty($classLabels) || $classTotal === 0): ?>
              <div class="chart-empty">
                <div class="text-center">
                  <i class="bi bi-bar-chart d-block mb-2 fs-3 text-subtle"></i>
                  <?= empty($classLabels)
                    ? 'No classes set up yet.'
                    : 'No students enrolled in any class yet.' ?>
                </div>
              </div>
            <?php else: ?>
              <canvas id="enrollmentChart" aria-label="Enrollment per class" role="img"></canvas>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-6 col-xl-3">
      <div class="card chart-card h-100">
        <div class="card-body">
          <div class="chart-card__head">
            <div>
              <h3 class="chart-card__title">Demographics</h3>
              <p class="chart-card__sub">Gender split &middot; <?= number_format($gTotal) ?> total</p>
            </div>
          </div>

          <div class="chart-wrap chart-wrap--sm">
            <?php if ($gTotal === 0): ?>
              <div class="chart-empty">No student data yet.</div>
            <?php else: ?>
              <canvas id="genderChart" aria-label="Gender distribution" role="img"></canvas>
            <?php endif; ?>
          </div>

          <ul class="donut-legend mt-2">
            <li>
              <span class="donut-legend__swatch" style="background:#3b82f6"></span>
              <span class="donut-legend__label">Male</span>
              <span class="donut-legend__value"><?= number_format($gMale) ?></span>
            </li>
            <li>
              <span class="donut-legend__swatch" style="background:#ec4899"></span>
              <span class="donut-legend__label">Female</span>
              <span class="donut-legend__value"><?= number_format($gFemale) ?></span>
            </li>
            <?php if ($gOther > 0): ?>
              <li>
                <span class="donut-legend__swatch" style="background:#6b7280"></span>
                <span class="donut-legend__label">Other</span>
                <span class="donut-legend__value"><?= number_format($gOther) ?></span>
              </li>
            <?php endif; ?>
          </ul>

          <div class="mini-stats mt-2">
            <div class="mini-stat">
              <div class="mini-stat__label">Day</div>
              <div class="mini-stat__value"><?= number_format($sectionDay) ?></div>
            </div>
            <div class="mini-stat">
              <div class="mini-stat__label">Boarding</div>
              <div class="mini-stat__value"><?= number_format($sectionBoarding) ?></div>
            </div>
            <div class="mini-stat">
              <div class="mini-stat__label">Science</div>
              <div class="mini-stat__value"><?= number_format($streamScience) ?></div>
            </div>
            <div class="mini-stat">
              <div class="mini-stat__label">Arts</div>
              <div class="mini-stat__value"><?= number_format($streamArts) ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-6 col-xl-3">
      <div class="card chart-card h-100">
        <div class="card-body">
          <div class="chart-card__head">
            <div>
              <h3 class="chart-card__title">Section split</h3>
              <p class="chart-card__sub">Day vs Boarding &middot; <?= number_format($sectionTotal) ?> total</p>
            </div>
          </div>

          <div class="chart-wrap chart-wrap--sm">
            <?php if ($sectionTotal === 0): ?>
              <div class="chart-empty">No section data yet.</div>
            <?php else: ?>
              <canvas id="sectionChart" aria-label="Section distribution" role="img"></canvas>
            <?php endif; ?>
          </div>

          <ul class="donut-legend mt-2">
            <li>
              <span class="donut-legend__swatch" style="background:#14b8a6"></span>
              <span class="donut-legend__label">Day</span>
              <span class="donut-legend__value"><?= number_format($sectionDay) ?></span>
            </li>
            <li>
              <span class="donut-legend__swatch" style="background:#f59e0b"></span>
              <span class="donut-legend__label">Boarding</span>
              <span class="donut-legend__value"><?= number_format($sectionBoarding) ?></span>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <!-- ============================================================
       Bottom row: Recent enrollments + Announcements
       ============================================================ -->
  <div class="section-block mb-2">
    <div class="section-block__head">
      <div>
        <h3 class="section-block__title"><i class="bi bi-activity"></i> Recent activity</h3>
        <p class="section-block__sub">Newly enrolled students and the latest announcements.</p>
      </div>
    </div>
  </div>
  <div class="row g-3">
    <div class="col-xl-7">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center justify-content-between">
          <span class="d-flex align-items-center fw-semibold mb-0">
            <span class="card-header-icon card-header-icon--orange me-2" aria-hidden="true"><i class="bi bi-person-plus"></i></span>
            Recently enrolled students
          </span>
          <a href="<?= $base ?>/students" class="small text-decoration-none">View all</a>
        </div>
        <?php if (empty($recentStudents)): ?>
          <div class="card-body">
            <div class="empty-state py-4">
              <i class="bi bi-person-plus d-block"></i>
              <div>No students yet. Add your first student to see them here.</div>
              <a href="<?= $base ?>/students/create" class="btn btn-sm btn-outline-primary mt-3">
                <i class="bi bi-person-plus"></i> Add student
              </a>
            </div>
          </div>
        <?php else: ?>
          <ul class="recent-list">
            <?php foreach (array_slice($recentStudents, 0, 5) as $s):
              $first    = (string) ($s['first_name'] ?? '');
              $last     = (string) ($s['last_name']  ?? '');
              $initials = strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1));
              if ($initials === '') { $initials = '?'; }
              $genderClass = 'recent-list__avatar--' . View::e($s['gender'] ?? 'other');
              $diffSec = max(0, time() - strtotime($s['created_at'] ?? 'now'));
              if ($diffSec < 60)            { $when = 'just now'; }
              elseif ($diffSec < 3600)      { $when = floor($diffSec / 60)    . ' min ago'; }
              elseif ($diffSec < 86400)     { $when = floor($diffSec / 3600)  . ' h ago'; }
              elseif ($diffSec < 7 * 86400) { $when = floor($diffSec / 86400) . ' d ago'; }
              else                          { $when = date('M j', strtotime($s['created_at'])); }
            ?>
              <li class="recent-list__item">
                <div class="recent-list__avatar <?= $genderClass ?>"><?= View::e($initials) ?></div>
                <div class="recent-list__body">
                  <div class="recent-list__name">
                    <?= View::e(trim($first . ' ' . $last)) ?: 'Unnamed student' ?>
                  </div>
                  <div class="recent-list__meta">
                    <span class="badge-soft"><?= View::e($s['admission_no'] ?? '—') ?></span>
                    <span class="text-muted">
                      &middot;
                      <?= View::e($s['class_name'] ?? 'Unassigned') ?>
                      &middot;
                      <?= View::studentEnumUpper('section', $s['section'] ?? 'day') ?>
                    </span>
                  </div>
                </div>
                <div class="recent-list__date"><?= View::e($when) ?></div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-xl-5">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center justify-content-between">
          <span class="d-flex align-items-center fw-semibold mb-0">
            <span class="card-header-icon card-header-icon--yellow me-2" aria-hidden="true"><i class="bi bi-megaphone-fill"></i></span>
            Latest announcements
          </span>
          <a href="<?= $base ?>/announcements" class="small text-decoration-none">View all</a>
        </div>
        <ul class="list-group list-group-flush">
          <?php if (empty($announcements)): ?>
            <li class="list-group-item">
              <div class="empty-state py-4">
                <i class="bi bi-inbox d-block"></i>
                <div>No announcements yet.</div>
              </div>
            </li>
          <?php else: foreach (array_slice($announcements, 0, 4) as $a): ?>
            <li class="list-group-item">
              <div class="d-flex justify-content-between align-items-start gap-3">
                <div>
                  <div class="fw-semibold"><?= View::e($a['title']) ?></div>
                  <div class="small text-muted mt-1">
                    <?= View::e(mb_strimwidth($a['body'], 0, 120, '…')) ?>
                  </div>
                  <div class="small text-subtle mt-1">
                    <i class="bi bi-person-circle"></i>
                    <?= View::e($a['author'] ?? 'System') ?>
                  </div>
                </div>
                <small class="text-muted text-nowrap">
                  <?= View::e(date('M j', strtotime($a['created_at']))) ?>
                </small>
              </div>
            </li>
          <?php endforeach; endif; ?>
        </ul>
      </div>
    </div>
  </div>

<?php else: ?>
  <!-- Simple dashboard for students (no analytics) -->
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center justify-content-between">
          <span class="d-flex align-items-center fw-semibold mb-0">
            <span class="card-header-icon card-header-icon--yellow me-2" aria-hidden="true"><i class="bi bi-megaphone-fill"></i></span>
            Latest announcements
          </span>
          <a href="<?= $base ?>/announcements" class="small text-decoration-none">View all</a>
        </div>
        <ul class="list-group list-group-flush">
          <?php if (empty($announcements)): ?>
            <li class="list-group-item">
              <div class="empty-state py-4">
                <i class="bi bi-inbox d-block"></i>
                <div>No announcements yet.</div>
              </div>
            </li>
          <?php else: foreach ($announcements as $a): ?>
            <li class="list-group-item">
              <div class="d-flex justify-content-between align-items-start gap-3">
                <div>
                  <div class="fw-semibold"><?= View::e($a['title']) ?></div>
                  <div class="small text-muted mt-1">
                    <?= View::e(mb_strimwidth($a['body'], 0, 160, '…')) ?>
                  </div>
                </div>
                <small class="text-muted text-nowrap">
                  <?= View::e(date('M j', strtotime($a['created_at']))) ?>
                </small>
              </div>
            </li>
          <?php endforeach; endif; ?>
        </ul>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center">
          <span class="card-header-icon card-header-icon--teal me-2" aria-hidden="true"><i class="bi bi-link-45deg"></i></span>
          <span class="fw-semibold mb-0">Quick links</span>
        </div>
        <div class="card-body d-grid gap-2">
          <a href="<?= $base ?>/reports" class="quick-action">
            <span class="quick-action__icon quick-action__icon--purple"><i class="bi bi-file-earmark-text"></i></span>
            <span class="quick-action__body">
              <span class="quick-action__title">My report cards</span>
              <span class="quick-action__sub">View term reports</span>
            </span>
          </a>
          <a href="<?= $base ?>/fees" class="quick-action">
            <span class="quick-action__icon quick-action__icon--success">
              <i class="bi bi-cash-coin"></i>
            </span>
            <span class="quick-action__body">
              <span class="quick-action__title">Fees</span>
              <span class="quick-action__sub">View balance &amp; payments</span>
            </span>
          </a>
          <a href="<?= $base ?>/announcements" class="quick-action">
            <span class="quick-action__icon quick-action__icon--orange">
              <i class="bi bi-megaphone"></i>
            </span>
            <span class="quick-action__body">
              <span class="quick-action__title">Announcements</span>
              <span class="quick-action__sub">School notices &amp; updates</span>
            </span>
          </a>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

</div>

<?php if ($isAdminish && ($classTotal > 0 || $gTotal > 0 || $sectionTotal > 0)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" defer></script>
<script>
  // Theme-aware Chart.js bootstrapping. Reads CSS vars at draw time so
  // charts respect the active light/dark theme, and re-renders when the
  // theme is toggled.
  (function () {
    'use strict';

    var classData  = <?= json_encode([
      'labels' => $classLabels,
      'data'   => $classCounts,
      'levels' => $classLevels,
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    var genderData = <?= json_encode([
      'labels' => array_values(array_filter(['Male', 'Female', $gOther > 0 ? 'Other' : null])),
      'data'   => array_values(array_filter([$gMale, $gFemale, $gOther > 0 ? $gOther : null], fn($v) => $v !== null)),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    var sectionData = <?= json_encode([
      'labels' => ['Day', 'Boarding'],
      'data'   => [$sectionDay, $sectionBoarding],
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    var charts = { enrollment: null, gender: null, section: null };

    function readTheme() {
      var css = getComputedStyle(document.documentElement);
      function v(name, fallback) {
        var raw = css.getPropertyValue(name);
        return (raw && raw.trim()) || fallback;
      }
      var accentRgb = v('--accent-rgb', '37, 99, 235');
      return {
        accent:       v('--accent', '#2563eb'),
        accentSoft:   'rgba(' + accentRgb + ', 0.85)',
        accentSofter: 'rgba(' + accentRgb + ', 0.35)',
        border:       v('--border', '#e7e9ee'),
        muted:        v('--text-muted', '#6b7280'),
        text:         v('--text', '#1f2937'),
        surface:      v('--surface', '#ffffff'),
        chartFont:    v('--bs-body-font-family', 'system-ui, sans-serif'),
      };
    }

    function destroyAll() {
      Object.keys(charts).forEach(function (k) {
        if (charts[k]) { charts[k].destroy(); charts[k] = null; }
      });
    }

    function buildEnrollment(theme) {
      var el = document.getElementById('enrollmentChart');
      if (!el || !window.Chart) return;
      var ctx = el.getContext('2d');

      // Soft pastel palette — cycled per bar for visual variety.
      var palette = [
        '#3b82f6', // blue
        '#22c55e', // green
        '#eab308', // yellow
        '#a855f7', // purple
        '#f97316', // orange
        '#ec4899', // pink
        '#14b8a6'  // teal
      ];
      var colors = classData.labels.map(function (_, i) {
        return palette[i % palette.length];
      });

      charts.enrollment = new Chart(ctx, {
        type: 'bar',
        data: {
          labels: classData.labels,
          datasets: [{
            label: 'Students',
            data: classData.data,
            backgroundColor: colors,
            hoverBackgroundColor: colors,
            borderRadius: 10,
            borderSkipped: false,
            maxBarThickness: 46,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
            tooltip: {
              backgroundColor: theme.surface,
              titleColor: theme.text,
              bodyColor: theme.muted,
              borderColor: theme.border,
              borderWidth: 1,
              padding: 10,
              displayColors: false,
              callbacks: {
                title: function (items) {
                  var i = items[0].dataIndex;
                  var lvl = classData.levels[i];
                  return classData.labels[i] + (lvl ? ' (' + lvl + ')' : '');
                },
                label: function (ctx) {
                  var n = ctx.parsed.y;
                  return n + (n === 1 ? ' student enrolled' : ' students enrolled');
                }
              }
            }
          },
          scales: {
            x: {
              grid: { display: false, drawBorder: false },
              ticks: { color: theme.muted, font: { family: theme.chartFont, size: 11 } }
            },
            y: {
              beginAtZero: true,
              grid: { color: theme.border, drawBorder: false },
              ticks: { color: theme.muted, font: { family: theme.chartFont, size: 11 }, precision: 0 }
            }
          }
        }
      });
    }

    function buildGender(theme) {
      var el = document.getElementById('genderChart');
      if (!el || !window.Chart) return;

      // Stable gendered palette regardless of theme.
      var palette = ['#3b82f6', '#ec4899', '#6b7280'];

      charts.gender = new Chart(el, {
        type: 'doughnut',
        data: {
          labels: genderData.labels,
          datasets: [{
            data: genderData.data,
            backgroundColor: palette.slice(0, genderData.data.length),
            borderColor: theme.surface,
            borderWidth: 3,
            hoverOffset: 6,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '68%',
          plugins: {
            legend: { display: false },
            tooltip: {
              backgroundColor: theme.surface,
              titleColor: theme.text,
              bodyColor: theme.muted,
              borderColor: theme.border,
              borderWidth: 1,
              padding: 10,
              displayColors: true,
              callbacks: {
                label: function (ctx) {
                  var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                  var pct = total ? Math.round((ctx.parsed / total) * 100) : 0;
                  return ' ' + ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                }
              }
            }
          }
        }
      });
    }

    function buildSection(theme) {
      var el = document.getElementById('sectionChart');
      if (!el || !window.Chart) return;

      charts.section = new Chart(el, {
        type: 'doughnut',
        data: {
          labels: sectionData.labels,
          datasets: [{
            data: sectionData.data,
            backgroundColor: ['#14b8a6', '#f59e0b'],
            borderColor: theme.surface,
            borderWidth: 3,
            hoverOffset: 6,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '68%',
          plugins: {
            legend: { display: false },
            tooltip: {
              backgroundColor: theme.surface,
              titleColor: theme.text,
              bodyColor: theme.muted,
              borderColor: theme.border,
              borderWidth: 1,
              padding: 10,
              displayColors: true,
              callbacks: {
                label: function (ctx) {
                  var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                  var pct = total ? Math.round((ctx.parsed / total) * 100) : 0;
                  return ' ' + ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                }
              }
            }
          }
        }
      });
    }

    function renderAll() {
      if (!window.Chart) return;
      destroyAll();
      var theme = readTheme();
      buildEnrollment(theme);
      buildGender(theme);
      buildSection(theme);
    }

    function whenChartReady(cb) {
      if (window.Chart) { cb(); return; }
      var tries = 0;
      var poll = setInterval(function () {
        if (window.Chart || tries++ > 60) {
          clearInterval(poll);
          if (window.Chart) cb();
        }
      }, 50);
    }

    document.addEventListener('DOMContentLoaded', function () {
      whenChartReady(renderAll);

      // Re-render on theme toggle (data-bs-theme flips on <html>)
      var obs = new MutationObserver(function (mutations) {
        for (var i = 0; i < mutations.length; i++) {
          if (mutations[i].attributeName === 'data-bs-theme') {
            whenChartReady(renderAll);
            break;
          }
        }
      });
      obs.observe(document.documentElement, { attributes: true });
    });
  })();
</script>
<?php endif; ?>
