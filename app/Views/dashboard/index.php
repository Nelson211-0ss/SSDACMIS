<?php
use App\Core\View;

$layout = 'app';
$title  = 'Overview';

$role        = $auth['role'] ?? 'guest';
$isAdmin     = !empty($isAdmin) || $role === 'admin';
$isSchoolAdmin = ($role === 'school_admin');
$isStaff       = ($role === 'staff');
// Must match DashboardController scope: admins, per-school admins, and staff see analytics.
$isAdminish    = in_array($role, ['admin', 'school_admin', 'staff'], true);
$showOpsKpis   = ($isAdmin || $isSchoolAdmin);
$useOverviewUi = $isAdminish;
$showSchoolKpis = $isSchoolAdmin || $isStaff;

// School profile logo for overview panel (school admin).
$profileLogoUrl = '';
if (!empty($schoolProfile['logo'])) {
    $logoRel = trim((string) $schoolProfile['logo']);
    if ($logoRel !== '' && str_starts_with(ltrim($logoRel, '/'), 'uploads/')) {
        $abs = dirname(__DIR__, 3) . '/public/' . ltrim($logoRel, '/');
        if (is_file($abs)) {
            $profileLogoUrl = rtrim($base, '/') . '/' . ltrim($logoRel, '/');
        }
    }
}

$useClassEnrollment = !$isAdmin;

$classLabels = array_column($classDistribution ?? [], 'name');
$classCounts = array_map(fn($r) => (int) $r['total'], $classDistribution ?? []);
$classLevels = array_column($classDistribution ?? [], 'level');
$classTotal  = array_sum($classCounts);

$enrollmentChartTitle = $isAdmin ? 'Enrollment per school' : 'Enrollment per class';
$enrollmentChartSub   = $isAdmin
    ? 'Students enrolled at each school on the platform'
    : 'Students currently enrolled in each class';
$analyticsSub = $isAdmin
    ? 'Combined enrollment and demographics across all schools.'
    : 'Class enrollment and student demographics for your school.';
$flyCharts      = $isAdmin ? 4 : 3;
$flyProfile     = ($isSchoolAdmin && !empty($schoolProfile)) ? 5 : 0;
$flyActivity    = $isAdmin ? 6 : ($flyProfile ? 6 : 5);
$flyActivityRow = $flyActivity + 1;
$adminOps    = $adminOps ?? [];
$schoolProfile   = $schoolProfile   ?? null;
$schoolsOverview = $schoolsOverview ?? [];
$platformTotals  = $platformTotals  ?? [];

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

// ---- Enrollment per school (drives the bar chart) ----
$schoolLabels = array_column($schoolDistribution ?? [], 'name');
$schoolCounts = array_map(fn($r) => (int) $r['total'], $schoolDistribution ?? []);
$schoolCodes  = array_column($schoolDistribution ?? [], 'code');
$schoolTotal  = array_sum($schoolCounts);
$schoolCount  = count($schoolDistribution ?? []);

$enrollLabels = $useClassEnrollment ? $classLabels : $schoolLabels;
$enrollCounts = $useClassEnrollment ? $classCounts : $schoolCounts;
$enrollMeta   = $useClassEnrollment ? $classLevels : $schoolCodes;
$enrollTotal  = $useClassEnrollment ? $classTotal : $schoolTotal;
$hasEnrollmentChart = count($enrollLabels) > 0;

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

<div class="dash-fly-root<?= $useOverviewUi ? ' sa-dash' : '' ?>">

<!-- ============================================================
     Hero / greeting (compact, with inline quick actions)
     ============================================================ -->
<section class="dash-hero dash-fly dash-fly--1<?= ($isAdmin || $isSchoolAdmin) ? ' dash-hero--admin' : '' ?><?= $useOverviewUi ? ' dash-hero--slim dash-hero--sa' : '' ?>">
  <div class="dash-hero__content d-flex align-items-center gap-3">
    <span class="icon-chip icon-chip--<?= $greetTone ?> d-none d-sm-inline-grid">
      <i class="bi <?= $greetIcon ?>"></i>
    </span>
    <div class="flex-grow-1" style="min-width:0;">
      <h2 class="dash-hero__title">
        <?= $greeting ?>, <?= View::e($auth['name']) ?>.
        <?php if ($isAdmin): ?>
          <span class="dash-hero__role">Super admin</span>
        <?php elseif ($isSchoolAdmin): ?>
          <span class="dash-hero__role">School admin</span>
        <?php endif; ?>
      </h2>
      <p class="dash-hero__sub">
        <span class="dash-hero__date">
          <i class="bi bi-calendar3"></i><?= date('l, M j, Y') ?>
        </span>
        <?php if ($isSchoolAdmin && empty($schoolProfile)): ?>
          <span class="dash-hero__inline">
            <i class="bi bi-building-check"></i> Your school
          </span>
        <?php endif; ?>
      </p>
    </div>
  </div>

  <?php if ($isAdminish): ?>
    <div class="dash-hero__actions">
      <?php if ($isAdmin): ?>
        <a href="<?= $base ?>/schools/create" class="btn btn-primary btn-sm">
          <i class="bi bi-building"></i> Add school
        </a>
        <a href="<?= $base ?>/schools" class="btn btn-sm dash-hero__btn dash-hero__btn--attendance">
          <i class="bi bi-buildings"></i> Schools
        </a>
        <a href="<?= $base ?>/settings" class="btn btn-sm dash-hero__btn dash-hero__btn--marks">
          <i class="bi bi-gear"></i> Settings
        </a>
        <a href="<?= $base ?>/announcements" class="btn btn-sm dash-hero__btn dash-hero__btn--announce">
          <i class="bi bi-megaphone"></i> Announce
        </a>
      <?php else: ?>
      <a href="<?= $base ?>/students/create" class="btn btn-primary btn-sm">
        <i class="bi bi-person-plus"></i> Add student
      </a>
      <?php if ($isSchoolAdmin): ?>
        <a href="<?= $base ?>/teaching" class="btn btn-sm dash-hero__btn dash-hero__btn--attendance">
          <i class="bi bi-diagram-3"></i> Teaching
        </a>
        <a href="<?= $base ?>/attendance"
           class="btn btn-sm dash-hero__btn dash-hero__btn--marks">
          <i class="bi bi-calendar-check"></i> Attendance
        </a>
        <a href="<?= $base ?>/marks"
           class="btn btn-sm dash-hero__btn dash-hero__btn--marks">
          <i class="bi bi-pencil-square"></i> Marks
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
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>


<?php if ($isAdmin && !empty($schoolsOverview)): ?>
<?php
  $ptSchools   = (int) ($platformTotals['schools']   ?? 0);
  $ptActive    = (int) ($platformTotals['active']    ?? 0);
  $ptStudents  = (int) ($platformTotals['students']  ?? 0);
  $ptStaff     = (int) ($platformTotals['staff']     ?? 0);
  $ptHods      = (int) ($platformTotals['hods']      ?? 0);
  $ptBursars   = (int) ($platformTotals['bursars']   ?? 0);
?>

<section class="sa-metrics sa-metrics--compact dash-fly dash-fly--2" aria-label="Platform totals">
  <div class="dash-kpi-grid dash-kpi-grid--sa mb-3">
    <div class="dash-kpi-grid__item">
      <a href="<?= $base ?>/schools" class="kpi-card kpi-card--dash">
        <div class="kpi-card__icon kpi-card__icon--blue"><i class="bi bi-buildings"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Schools</div>
          <div class="kpi-card__value"><?= number_format($ptSchools) ?></div>
          <?php if ($ptActive < $ptSchools): ?>
            <div class="kpi-card__delta kpi-card__delta--flat"><?= number_format($ptActive) ?> active</div>
          <?php endif; ?>
        </div>
      </a>
    </div>
    <div class="dash-kpi-grid__item">
      <a href="<?= $base ?>/students" class="kpi-card kpi-card--dash">
        <div class="kpi-card__icon kpi-card__icon--orange"><i class="bi bi-people-fill"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Students</div>
          <div class="kpi-card__value"><?= number_format($ptStudents) ?></div>
          <?php if ($studThisMonth > 0): ?>
            <div class="kpi-card__delta kpi-card__delta--up"><i class="bi bi-arrow-up-right"></i><?= number_format($studThisMonth) ?> this month</div>
          <?php endif; ?>
        </div>
      </a>
    </div>
    <div class="dash-kpi-grid__item">
      <a href="<?= $base ?>/staff" class="kpi-card kpi-card--dash">
        <div class="kpi-card__icon kpi-card__icon--green"><i class="bi bi-person-workspace"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Staff</div>
          <div class="kpi-card__value"><?= number_format($ptStaff) ?></div>
        </div>
      </a>
    </div>
    <div class="dash-kpi-grid__item">
      <a href="<?= $base ?>/classes" class="kpi-card kpi-card--dash">
        <div class="kpi-card__icon kpi-card__icon--purple"><i class="bi bi-grid"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Classes</div>
          <div class="kpi-card__value"><?= number_format($classesTotal) ?></div>
        </div>
      </a>
    </div>
    <div class="dash-kpi-grid__item">
      <a href="<?= $base ?>/hods" class="kpi-card kpi-card--dash">
        <div class="kpi-card__icon kpi-card__icon--indigo"><i class="bi bi-mortarboard-fill"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">HODs</div>
          <div class="kpi-card__value"><?= number_format($ptHods) ?></div>
        </div>
      </a>
    </div>
    <div class="dash-kpi-grid__item">
      <a href="<?= $base ?>/bursars" class="kpi-card kpi-card--dash">
        <div class="kpi-card__icon kpi-card__icon--teal"><i class="bi bi-cash-coin"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Bursars</div>
          <div class="kpi-card__value"><?= number_format($ptBursars) ?></div>
        </div>
      </a>
    </div>
  </div>

  <?php if ($showOpsKpis): ?>
  <div class="dash-kpi-grid dash-kpi-grid--4 mb-3">
    <div class="dash-kpi-grid__item">
      <a href="<?= $base ?>/attendance" class="kpi-card kpi-card--dash">
        <div class="kpi-card__icon kpi-card__icon--<?= $attRate !== null && $attRate >= 80 ? 'success' : ($attRate !== null && $attRate >= 60 ? 'warning' : 'danger') ?>">
          <i class="bi bi-calendar-check"></i>
        </div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Attendance today</div>
          <div class="kpi-card__value"><?= $attRate !== null ? $attRate . '%' : '—' ?></div>
          <?php if ($attTotal > 0): ?>
            <div class="kpi-card__delta kpi-card__delta--flat"><?= number_format($attPresent) ?> present · <?= number_format($attAbsent) ?> absent</div>
          <?php endif; ?>
        </div>
      </a>
    </div>
    <div class="dash-kpi-grid__item">
      <a href="<?= $base ?>/teaching" class="kpi-card kpi-card--dash">
        <div class="kpi-card__icon kpi-card__icon--warning"><i class="bi bi-diagram-3"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Teaching slots</div>
          <div class="kpi-card__value"><?= number_format($teachingCount) ?></div>
        </div>
      </a>
    </div>
    <div class="dash-kpi-grid__item">
      <a href="<?= $base ?>/students" class="kpi-card kpi-card--dash">
        <div class="kpi-card__icon kpi-card__icon--<?= $unassignedCount > 0 ? 'danger' : 'success' ?>">
          <i class="bi bi-person-exclamation"></i>
        </div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Unassigned students</div>
          <div class="kpi-card__value"><?= number_format($unassignedCount) ?></div>
        </div>
      </a>
    </div>
    <div class="dash-kpi-grid__item">
      <a href="<?= $base ?>/subjects" class="kpi-card kpi-card--dash">
        <div class="kpi-card__icon kpi-card__icon--accent"><i class="bi bi-book-half"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Subjects offered</div>
          <div class="kpi-card__value"><?= number_format($subjectsOffered) ?></div>
        </div>
      </a>
    </div>
  </div>
  <?php endif; ?>
</section>

<?php elseif ($showSchoolKpis): ?>
<section class="sa-metrics sa-metrics--compact dash-fly dash-fly--2" aria-label="School totals">
  <div class="dash-kpi-grid <?= $isSchoolAdmin ? 'dash-kpi-grid--sa' : 'dash-kpi-grid--4' ?> mb-3">
    <div class="dash-kpi-grid__item">
      <a href="<?= $base ?>/students" class="kpi-card kpi-card--dash">
        <div class="kpi-card__icon kpi-card__icon--orange"><i class="bi bi-people-fill"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Students</div>
          <div class="kpi-card__value"><?= number_format($studentsTotal) ?></div>
          <?php if ($studThisMonth > 0): ?>
            <div class="kpi-card__delta kpi-card__delta--up"><i class="bi bi-arrow-up-right"></i><?= number_format($studThisMonth) ?> this month</div>
          <?php endif; ?>
        </div>
      </a>
    </div>
    <div class="dash-kpi-grid__item">
      <a href="<?= $base ?>/staff" class="kpi-card kpi-card--dash">
        <div class="kpi-card__icon kpi-card__icon--green"><i class="bi bi-person-workspace"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Staff</div>
          <div class="kpi-card__value"><?= number_format($staffTotal) ?></div>
          <?php if ($staffThisMonth > 0): ?>
            <div class="kpi-card__delta kpi-card__delta--flat"><?= number_format($staffThisMonth) ?> new this month</div>
          <?php endif; ?>
        </div>
      </a>
    </div>
    <div class="dash-kpi-grid__item">
      <a href="<?= $base ?>/classes" class="kpi-card kpi-card--dash">
        <div class="kpi-card__icon kpi-card__icon--purple"><i class="bi bi-grid"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Classes</div>
          <div class="kpi-card__value"><?= number_format($classesTotal) ?></div>
        </div>
      </a>
    </div>
    <div class="dash-kpi-grid__item">
      <a href="<?= $base ?>/subjects" class="kpi-card kpi-card--dash">
        <div class="kpi-card__icon kpi-card__icon--accent"><i class="bi bi-book-half"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Subjects offered</div>
          <div class="kpi-card__value"><?= number_format($subjectsOffered) ?></div>
        </div>
      </a>
    </div>
    <?php if ($isSchoolAdmin): ?>
    <div class="dash-kpi-grid__item">
      <a href="<?= $base ?>/hods" class="kpi-card kpi-card--dash">
        <div class="kpi-card__icon kpi-card__icon--indigo"><i class="bi bi-mortarboard-fill"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">HODs</div>
          <div class="kpi-card__value"><?= number_format($hodCount) ?></div>
        </div>
      </a>
    </div>
    <div class="dash-kpi-grid__item">
      <a href="<?= $base ?>/bursars" class="kpi-card kpi-card--dash">
        <div class="kpi-card__icon kpi-card__icon--teal"><i class="bi bi-cash-coin"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Bursars</div>
          <div class="kpi-card__value"><?= number_format($bursarCount) ?></div>
        </div>
      </a>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($showOpsKpis): ?>
  <div class="dash-kpi-grid dash-kpi-grid--4 mb-3">
    <div class="dash-kpi-grid__item">
      <a href="<?= $base ?>/attendance" class="kpi-card kpi-card--dash">
        <div class="kpi-card__icon kpi-card__icon--<?= $attRate !== null && $attRate >= 80 ? 'success' : ($attRate !== null && $attRate >= 60 ? 'warning' : 'danger') ?>">
          <i class="bi bi-calendar-check"></i>
        </div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Attendance today</div>
          <div class="kpi-card__value"><?= $attRate !== null ? $attRate . '%' : '—' ?></div>
          <?php if ($attTotal > 0): ?>
            <div class="kpi-card__delta kpi-card__delta--flat"><?= number_format($attPresent) ?> present · <?= number_format($attAbsent) ?> absent</div>
          <?php endif; ?>
        </div>
      </a>
    </div>
    <div class="dash-kpi-grid__item">
      <a href="<?= $base ?>/teaching" class="kpi-card kpi-card--dash">
        <div class="kpi-card__icon kpi-card__icon--warning"><i class="bi bi-diagram-3"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Teaching slots</div>
          <div class="kpi-card__value"><?= number_format($teachingCount) ?></div>
        </div>
      </a>
    </div>
    <div class="dash-kpi-grid__item">
      <a href="<?= $base ?>/students" class="kpi-card kpi-card--dash">
        <div class="kpi-card__icon kpi-card__icon--<?= $unassignedCount > 0 ? 'danger' : 'success' ?>">
          <i class="bi bi-person-exclamation"></i>
        </div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Unassigned students</div>
          <div class="kpi-card__value"><?= number_format($unassignedCount) ?></div>
        </div>
      </a>
    </div>
    <div class="dash-kpi-grid__item">
      <a href="<?= $base ?>/announcements" class="kpi-card kpi-card--dash">
        <div class="kpi-card__icon kpi-card__icon--yellow"><i class="bi bi-megaphone-fill"></i></div>
        <div class="kpi-card__body">
          <div class="kpi-card__label">Announcements</div>
          <div class="kpi-card__value"><?= number_format(count($announcements ?? [])) ?></div>
          <div class="kpi-card__delta kpi-card__delta--flat">Latest notices</div>
        </div>
      </a>
    </div>
  </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($isAdminish): ?>

  <?php if ($useOverviewUi && !$isAdmin): ?>
  <div class="section-block mb-2 dash-fly dash-fly--3">
    <div class="section-block__head">
      <div>
        <h3 class="section-block__title"><i class="bi bi-bar-chart-line"></i> Analytics</h3>
        <p class="section-block__sub"><?= View::e($analyticsSub) ?></p>
      </div>
    </div>
  </div>
  <?php elseif ($isAdmin && !empty($schoolsOverview)): ?>
  <div class="section-block mb-2 dash-fly dash-fly--3">
    <div class="section-block__head">
      <div>
        <h3 class="section-block__title"><i class="bi bi-bar-chart-line"></i> Analytics</h3>
        <p class="section-block__sub"><?= View::e($analyticsSub) ?></p>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="row g-3 mb-2 overview-analytics-row dash-fly dash-fly--<?= (int) $flyCharts ?>" aria-label="Analytics charts">
    <div class="col-lg-7">
      <div class="card chart-card h-100">
        <div class="card-body">
          <div class="chart-card__head">
            <div>
              <h3 class="chart-card__title"><?= View::e($enrollmentChartTitle) ?></h3>
              <p class="chart-card__sub"><?= View::e($enrollmentChartSub) ?></p>
            </div>
            <div class="chart-card__badge">
              <i class="bi bi-<?= $useClassEnrollment ? 'bar-chart-fill' : 'buildings' ?>"></i>
              <?= number_format($enrollTotal) ?> enrolled
              <?php if ($isAdmin): ?>
                · <?= number_format($schoolCount) ?> school<?= $schoolCount === 1 ? '' : 's' ?>
              <?php else: ?>
                · <?= number_format(count($enrollLabels)) ?> class<?= count($enrollLabels) === 1 ? '' : 'es' ?>
              <?php endif; ?>
            </div>
          </div>
          <div class="chart-surface chart-surface--bar-side">
            <?php if (!$hasEnrollmentChart): ?>
              <div class="chart-empty">
                <div class="text-center">
                  <i class="bi bi-<?= $useClassEnrollment ? 'grid' : 'buildings' ?> d-block mb-2 fs-3 text-subtle"></i>
                  <?php if ($useClassEnrollment): ?>
                    No classes set up yet.
                    <div class="mt-2"><a href="<?= $base ?>/classes" class="btn btn-sm btn-outline-primary">Manage classes</a></div>
                  <?php else: ?>
                    No schools on the system yet.
                    <?php if ($isAdmin): ?>
                      <div class="mt-2"><a href="<?= $base ?>/schools/create" class="btn btn-sm btn-outline-primary">Add school</a></div>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
              </div>
            <?php else: ?>
              <canvas id="enrollmentChart" aria-label="<?= View::e($enrollmentChartTitle) ?>" role="img"></canvas>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-5 d-flex flex-column gap-3">
      <div class="card chart-card flex-fill">
        <div class="card-body pb-2">
          <div class="chart-card__head mb-1">
            <div>
              <h3 class="chart-card__title">Gender</h3>
              <p class="chart-card__sub"><?= number_format($gTotal) ?> students</p>
            </div>
          </div>
          <div class="row g-0 align-items-center">
            <div class="col-5">
              <div class="chart-surface chart-surface--donut-side">
                <?php if ($gTotal === 0): ?>
                  <div class="chart-empty">No data yet.</div>
                <?php else: ?>
                  <canvas id="genderChart" aria-label="Gender distribution" role="img"></canvas>
                <?php endif; ?>
              </div>
            </div>
            <div class="col-7 ps-3">
              <ul class="donut-legend mb-2">
                <li><span class="donut-legend__swatch" style="background:#3b82f6"></span><span class="donut-legend__label">Male</span><span class="donut-legend__value"><?= number_format($gMale) ?></span></li>
                <li><span class="donut-legend__swatch" style="background:#ec4899"></span><span class="donut-legend__label">Female</span><span class="donut-legend__value"><?= number_format($gFemale) ?></span></li>
                <?php if ($gOther > 0): ?>
                  <li><span class="donut-legend__swatch" style="background:#6b7280"></span><span class="donut-legend__label">Other</span><span class="donut-legend__value"><?= number_format($gOther) ?></span></li>
                <?php endif; ?>
              </ul>
              <div class="mini-stats">
                <div class="mini-stat"><div class="mini-stat__label">Science</div><div class="mini-stat__value"><?= number_format($streamScience) ?></div></div>
                <div class="mini-stat"><div class="mini-stat__label">Arts</div><div class="mini-stat__value"><?= number_format($streamArts) ?></div></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card chart-card flex-fill">
        <div class="card-body pb-2">
          <div class="chart-card__head mb-1">
            <div>
              <h3 class="chart-card__title">Section split</h3>
              <p class="chart-card__sub">Day vs boarding · <?= number_format($sectionTotal) ?></p>
            </div>
          </div>
          <div class="row g-0 align-items-center">
            <div class="col-5">
              <div class="chart-surface chart-surface--donut-side">
                <?php if ($sectionTotal === 0): ?>
                  <div class="chart-empty">No data yet.</div>
                <?php else: ?>
                  <canvas id="sectionChart" aria-label="Section distribution" role="img"></canvas>
                <?php endif; ?>
              </div>
            </div>
            <div class="col-7 ps-3">
              <ul class="donut-legend mb-0">
                <li><span class="donut-legend__swatch" style="background:#14b8a6"></span><span class="donut-legend__label">Day</span><span class="donut-legend__value"><?= number_format($sectionDay) ?></span></li>
                <li><span class="donut-legend__swatch" style="background:#f59e0b"></span><span class="donut-legend__label">Boarding</span><span class="donut-legend__value"><?= number_format($sectionBoarding) ?></span></li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php if ($isSchoolAdmin && !empty($schoolProfile)):
    $profileActive = ($schoolProfile['status'] ?? '') === 'active';
    $profileAddr   = trim((string) ($schoolProfile['address'] ?? ''));
    $profileEm     = trim((string) ($schoolProfile['email'] ?? ''));
    $profilePh     = trim((string) ($schoolProfile['phone'] ?? ''));
  ?>
  <section class="sa-panel sa-panel--profile mb-3 dash-fly dash-fly--<?= (int) $flyProfile ?>">
    <div class="card-body py-2 px-3">
      <div class="sa-profile__main sa-profile__main--compact">
        <?php if ($profileLogoUrl !== ''): ?>
          <img src="<?= View::e($profileLogoUrl) ?>" alt="" class="sa-profile__logo sa-profile__logo--sm">
        <?php else: ?>
          <span class="sa-profile__logo sa-profile__logo--sm sa-profile__logo--placeholder" aria-hidden="true"><i class="bi bi-building"></i></span>
        <?php endif; ?>
        <div class="sa-profile__body flex-grow-1" style="min-width:0;">
          <div class="d-flex flex-wrap align-items-center gap-2">
            <h3 class="sa-profile__title h6 mb-0"><?= View::e((string) $schoolProfile['name']) ?></h3>
            <span class="sa-status <?= $profileActive ? 'sa-status--on' : 'sa-status--off' ?>">
              <?= $profileActive ? 'Active' : 'Inactive' ?>
            </span>
            <code class="sa-code"><?= View::e((string) ($schoolProfile['code'] ?? '—')) ?></code>
          </div>
          <div class="small text-muted d-flex flex-wrap gap-2 mt-1">
            <?php if ($profileEm !== ''): ?>
              <span><i class="bi bi-envelope"></i> <a href="mailto:<?= View::e($profileEm) ?>"><?= View::e($profileEm) ?></a></span>
            <?php endif; ?>
            <?php if ($profilePh !== ''): ?>
              <span><i class="bi bi-telephone"></i> <?= View::e($profilePh) ?></span>
            <?php endif; ?>
            <?php if ($profileAddr !== ''): ?>
              <span><i class="bi bi-geo-alt"></i> <?= View::e($profileAddr) ?></span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($isAdmin && !empty($schoolsOverview)): ?>
  <section class="sa-panel mb-3 dash-fly dash-fly--5">
    <div class="sa-panel__head">
      <div>
        <h3 class="sa-panel__title">Schools</h3>
        <p class="sa-panel__sub"><?= number_format(count($schoolsOverview)) ?> registered · tap a row to manage</p>
      </div>
      <a href="<?= $base ?>/schools" class="btn btn-sm btn-outline-secondary">View all</a>
    </div>
    <div class="sa-table-wrap">
      <table class="table sa-table align-middle mb-0">
        <thead>
          <tr>
            <th>School</th>
            <th class="text-end d-none d-md-table-cell">Students</th>
            <th class="text-end d-none d-lg-table-cell">Staff</th>
            <th class="text-end d-none d-lg-table-cell">Classes</th>
            <th>Status</th>
            <th class="text-end"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($schoolsOverview as $sc):
            $logoPath = '';
            $logoRel  = trim((string)($sc['logo'] ?? ''));
            if ($logoRel !== '' && str_starts_with(ltrim($logoRel,'/'), 'uploads/')) {
              $abs = dirname(__DIR__, 3) . '/public/' . ltrim($logoRel, '/');
              if (is_file($abs)) {
                $logoPath = rtrim($base, '/') . '/' . ltrim($logoRel, '/');
              }
            }
            $students = (int)($sc['student_count'] ?? 0);
            $staff    = (int)($sc['staff_count']   ?? 0);
            $classes  = (int)($sc['class_count']   ?? 0);
            $isActive = ($sc['status'] ?? '') === 'active';
          ?>
          <tr class="<?= !$isActive ? 'sa-table__row--muted' : '' ?>">
            <td>
              <a href="<?= $base ?>/schools/<?= (int)$sc['id'] ?>" class="sa-school-cell">
                <?php if ($logoPath !== ''): ?>
                  <img src="<?= View::e($logoPath) ?>" alt="" class="sa-school-cell__logo" width="36" height="36">
                <?php else: ?>
                  <span class="sa-school-cell__logo sa-school-cell__logo--placeholder" aria-hidden="true"><i class="bi bi-building"></i></span>
                <?php endif; ?>
                <span class="sa-school-cell__text">
                  <span class="sa-school-cell__name"><?= View::e((string)$sc['name']) ?></span>
                  <span class="sa-school-cell__code"><?= View::e((string)$sc['code']) ?></span>
                </span>
              </a>
            </td>
            <td class="text-end fw-semibold d-none d-md-table-cell"><?= number_format($students) ?></td>
            <td class="text-end d-none d-lg-table-cell"><?= number_format($staff) ?></td>
            <td class="text-end d-none d-lg-table-cell"><?= number_format($classes) ?></td>
            <td>
              <span class="sa-status <?= $isActive ? 'sa-status--on' : 'sa-status--off' ?>">
                <?= $isActive ? 'Active' : 'Inactive' ?>
              </span>
            </td>
            <td class="text-end">
              <a href="<?= $base ?>/schools/<?= (int)$sc['id'] ?>" class="btn btn-sm btn-link sa-table__link">Open</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
  <?php endif; ?>

  <!-- ============================================================
       Bottom row: Recent enrollments + Announcements
       ============================================================ -->
  <div class="section-block mb-1 dash-fly dash-fly--<?= (int) $flyActivity ?>">
    <div class="section-block__head">
      <div>
        <h3 class="section-block__title"><i class="bi bi-activity"></i> Recent activity</h3>
        <p class="section-block__sub">Newly enrolled students and the latest announcements.</p>
      </div>
    </div>
  </div>
  <div class="row g-3 dash-fly dash-fly--<?= (int) $flyActivityRow ?>">
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
                      <?php $sName = trim((string)($s['school_name'] ?? '')); if ($isAdmin && $sName !== ''): ?>
                        &middot; <span class="text-primary fw-semibold"><?= View::e($sName) ?></span>
                      <?php endif; ?>
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

<?php if ($isAdminish && ($hasEnrollmentChart || $gTotal > 0 || $sectionTotal > 0)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" defer></script>
<script>
  // Theme-aware Chart.js bootstrapping. Reads CSS vars at draw time so
  // charts respect the active light/dark theme, and re-renders when the
  // theme is toggled.
  (function () {
    'use strict';

    var enrollmentData = <?= json_encode([
      'labels' => $enrollLabels,
      'data'   => $enrollCounts,
      'meta'   => $enrollMeta,
      'mode'   => $useClassEnrollment ? 'class' : 'school',
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
      var colors = enrollmentData.labels.map(function (_, i) {
        return palette[i % palette.length];
      });

      charts.enrollment = new Chart(ctx, {
        type: 'bar',
        data: {
          labels: enrollmentData.labels,
          datasets: [{
            label: 'Students',
            data: enrollmentData.data,
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
                  var meta = enrollmentData.meta[i] || '';
                  if (enrollmentData.mode === 'class') {
                    return enrollmentData.labels[i] + (meta ? ' (' + meta + ')' : '');
                  }
                  return enrollmentData.labels[i] + (meta ? ' (' + meta + ')' : '');
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
