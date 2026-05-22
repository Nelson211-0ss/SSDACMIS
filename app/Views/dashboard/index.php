<?php
use App\Core\View;

$layout = 'app';
$title  = 'Dashboard';

$role        = $auth['role'] ?? 'guest';
$isAdmin     = !empty($isAdmin) || $role === 'admin';
$isSchoolAdmin = ($role === 'school_admin');
// Must match DashboardController scope: admins, per-school admins, and staff see analytics.
$isAdminish  = in_array($role, ['admin', 'school_admin', 'staff'], true);
$showOpsKpis = ($isAdmin || $isSchoolAdmin);
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

<div class="<?= ($isAdmin || $isSchoolAdmin) ? 'admin-dash' : '' ?>">

<!-- ============================================================
     Hero / greeting (compact, with inline quick actions)
     ============================================================ -->
<section class="dash-hero<?= ($isAdmin || $isSchoolAdmin) ? ' dash-hero--admin' : '' ?>">
  <div class="dash-hero__content d-flex align-items-center gap-3">
    <span class="icon-chip icon-chip--<?= $greetTone ?> d-none d-sm-inline-grid">
      <i class="bi <?= $greetIcon ?>"></i>
    </span>
    <div class="flex-grow-1" style="min-width:0;">
      <h2 class="dash-hero__title">
        <?= $greeting ?>, <?= View::e($auth['name']) ?>.
        <?php if ($isAdmin): ?>
          <span class="dash-hero__role">Administrator</span>
        <?php elseif ($isSchoolAdmin): ?>
          <span class="dash-hero__role">School admin</span>
        <?php endif; ?>
      </h2>
      <p class="dash-hero__sub">
        <span class="dash-hero__date">
          <i class="bi bi-calendar3"></i><?= date('l, M j, Y') ?>
        </span>
        <?php if ($isAdmin): ?>
          <span class="dash-hero__inline">
            <i class="bi bi-shield-check"></i>
            <?= !empty($platformTotals) ? number_format((int)$platformTotals['schools']) . ' school' . ((int)$platformTotals['schools'] !== 1 ? 's' : '') . ' · ' . number_format((int)$platformTotals['students']) . ' students' : 'Platform overview' ?>
          </span>
        <?php elseif ($isSchoolAdmin): ?>
          <span class="dash-hero__inline">
            <i class="bi bi-building-check"></i>
            <?= $schoolProfile
              ? View::e((string) $schoolProfile['name'])
              : 'Your school dashboard' ?>
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
      <?php elseif ($isSchoolAdmin): ?>
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
    </div>
  <?php endif; ?>
</section>


<?php if ($isAdmin && !empty($schoolsOverview)): ?>
<!-- ============================================================
     Super-Admin Platform Overview
     ============================================================ -->
<style>
  /* Platform KPI pills */
  .platform-kpi-row { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:0.8rem; }
  .platform-kpi {
    display:inline-flex; align-items:center; gap:8px;
    padding:6px 14px; border-radius:999px; font-size:.8rem; font-weight:600;
    background:var(--surface,#fff); border:1.5px solid transparent;
    box-shadow:0 1px 4px rgba(0,0,0,.07); text-decoration:none; color:inherit;
    transition:box-shadow .15s, transform .1s;
  }
  .platform-kpi:hover { box-shadow:0 3px 10px rgba(0,0,0,.12); transform:translateY(-1px); }
  .platform-kpi > i { font-size:0.95rem; opacity:.85; }
  .platform-kpi__val { font-size:1rem; font-weight:700; }
  .platform-kpi__lbl { color:var(--text-muted,#6b7280); font-weight:400; font-size:.75rem; }
  .platform-kpi--blue   { border-color:#bfdbfe; } .platform-kpi--blue   > i { color:#2563eb; }
  .platform-kpi--green  { border-color:#bbf7d0; } .platform-kpi--green  > i { color:#16a34a; }
  .platform-kpi--orange { border-color:#fed7aa; } .platform-kpi--orange > i { color:#ea580c; }
  .platform-kpi--teal   { border-color:#99f6e4; } .platform-kpi--teal   > i { color:#0d9488; }
  .platform-kpi--purple { border-color:#e9d5ff; } .platform-kpi--purple > i { color:#7c3aed; }
  .platform-kpi--indigo { border-color:#c7d2fe; } .platform-kpi--indigo > i { color:#4f46e5; }

  /* Per-school cards grid */
  .schools-grid {
    display:grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap:10px;
    margin-bottom:1rem;
  }
  .school-card {
    background:var(--surface,#fff);
    border:1px solid var(--border,#e7e9ee);
    border-radius:10px;
    padding:12px;
    display:flex; flex-direction:column; gap:8px;
    box-shadow:0 1px 4px rgba(0,0,0,.05);
    transition:box-shadow .15s, transform .1s;
    text-decoration:none; color:inherit;
  }
  .school-card:hover { box-shadow:0 4px 14px rgba(0,0,0,.1); transform:translateY(-2px); }
  .school-card__top { display:flex; align-items:center; gap:10px; }
  .school-card__logo {
    width:40px; height:40px; border-radius:6px; object-fit:contain;
    border:1px solid var(--border,#e7e9ee); background:#f9fafb; flex-shrink:0;
  }
  .school-card__logo-icon {
    width:40px; height:40px; border-radius:6px; background:#eff6ff;
    display:flex; align-items:center; justify-content:center;
    font-size:1.25rem; color:#3b82f6; flex-shrink:0;
  }
  .school-card__name { font-weight:700; font-size:.9rem; line-height:1.3; }
  .school-card__code { font-size:.7rem; color:var(--text-muted,#6b7280); font-family:monospace; }
  .school-card__stats { display:flex; flex-wrap:wrap; gap:6px; }
  .school-card__stat {
    display:flex; align-items:center; gap:4px;
    font-size:.7rem; font-weight:600;
    background:var(--surface-2,#f8f9fa); border-radius:4px;
    padding:2px 6px; color:var(--text,#374151);
  }
  .school-card__stat > i { font-size:.75rem; opacity:.7; }
  .school-card__footer { display:flex; align-items:center; justify-content:space-between; }
  .school-card--inactive { opacity:.7; }

  /* Stat pills */
  .dash-stat-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
  }
  .dash-stat-pill {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 7px 14px;
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
    border: 1.5px solid transparent;
    background: var(--surface, #fff);
    color: inherit;
    box-shadow: 0 1px 4px rgba(0,0,0,.07);
    transition: box-shadow .15s, transform .1s;
  }
  .dash-stat-pill:hover { box-shadow: 0 3px 10px rgba(0,0,0,.12); transform: translateY(-1px); }
  .dash-stat-pill > i { font-size: 1rem; opacity: .85; }
  .dash-stat-pill__value { font-size: 1rem; font-weight: 700; }
  .dash-stat-pill__label { color: var(--text-muted, #6b7280); font-weight: 400; font-size: 0.8rem; }
  .dash-stat-pill__delta { font-size: 0.75rem; color: #16a34a; }
  .dash-stat-pill--orange { border-color: #fed7aa; }
  .dash-stat-pill--orange > i { color: #ea580c; }
  .dash-stat-pill--green  { border-color: #bbf7d0; }
  .dash-stat-pill--green  > i { color: #16a34a; }
  .dash-stat-pill--blue   { border-color: #bfdbfe; }
  .dash-stat-pill--blue   > i { color: #2563eb; }
  .dash-stat-pill--purple { border-color: #e9d5ff; }
  .dash-stat-pill--purple > i { color: #7c3aed; }
  .dash-stat-pill--indigo { border-color: #c7d2fe; }
  .dash-stat-pill--indigo > i { color: #4f46e5; }
  .dash-stat-pill--teal   { border-color: #99f6e4; }
  .dash-stat-pill--teal   > i { color: #0d9488; }
  .dash-stat-pill--red    { border-color: #fecaca; }
  .dash-stat-pill--red    > i { color: #dc2626; }

</style>

<!-- Platform KPI pills -->
<div class="platform-kpi-row">
  <a href="<?= $base ?>/schools" class="platform-kpi platform-kpi--blue">
    <i class="bi bi-building-fill"></i>
    <span class="platform-kpi__val"><?= number_format((int)($platformTotals['schools'] ?? 0)) ?></span>
    <span class="platform-kpi__lbl">School<?= ((int)($platformTotals['schools'] ?? 0)) !== 1 ? 's' : '' ?></span>
    <?php if ((int)($platformTotals['active'] ?? 0) < (int)($platformTotals['schools'] ?? 0)): ?>
      <span class="platform-kpi__lbl">(<?= (int)$platformTotals['active'] ?> active)</span>
    <?php endif; ?>
  </a>
  <a href="<?= $base ?>/students" class="platform-kpi platform-kpi--orange">
    <i class="bi bi-people-fill"></i>
    <span class="platform-kpi__val"><?= number_format((int)($platformTotals['students'] ?? 0)) ?></span>
    <span class="platform-kpi__lbl">Total students</span>
  </a>
  <a href="<?= $base ?>/staff" class="platform-kpi platform-kpi--green">
    <i class="bi bi-person-workspace"></i>
    <span class="platform-kpi__val"><?= number_format((int)($platformTotals['staff'] ?? 0)) ?></span>
    <span class="platform-kpi__lbl">Total staff</span>
  </a>
  <span class="platform-kpi platform-kpi--indigo">
    <i class="bi bi-mortarboard-fill"></i>
    <span class="platform-kpi__val"><?= number_format((int)($platformTotals['hods'] ?? 0)) ?></span>
    <span class="platform-kpi__lbl">HODs</span>
  </span>
  <span class="platform-kpi platform-kpi--teal">
    <i class="bi bi-cash-coin"></i>
    <span class="platform-kpi__val"><?= number_format((int)($platformTotals['bursars'] ?? 0)) ?></span>
    <span class="platform-kpi__lbl">Bursars</span>
  </span>
</div>

<?php if ($isAdminish): ?>
  <!-- School-level stat pills -->
  <div class="dash-stat-row mb-3">
    <a href="<?= $base ?>/students" class="dash-stat-pill dash-stat-pill--orange">
      <i class="bi bi-people-fill"></i>
      <span class="dash-stat-pill__value"><?= number_format($studentsTotal) ?></span>
      <span class="dash-stat-pill__label">Students</span>
      <?php if ($studThisMonth > 0): ?>
        <span class="dash-stat-pill__delta"><i class="bi bi-arrow-up-right"></i><?= number_format($studThisMonth) ?> new</span>
      <?php endif; ?>
    </a>
    <a href="<?= $base ?>/staff" class="dash-stat-pill dash-stat-pill--green">
      <i class="bi bi-person-workspace"></i>
      <span class="dash-stat-pill__value"><?= number_format($staffTotal) ?></span>
      <span class="dash-stat-pill__label">Staff</span>
    </a>
    <a href="<?= $base ?>/classes" class="dash-stat-pill dash-stat-pill--blue">
      <i class="bi bi-building-fill"></i>
      <span class="dash-stat-pill__value"><?= number_format($classesTotal) ?></span>
      <span class="dash-stat-pill__label">Classes</span>
    </a>
    <a href="<?= $base ?>/subjects" class="dash-stat-pill dash-stat-pill--purple">
      <i class="bi bi-book-half"></i>
      <span class="dash-stat-pill__value"><?= number_format($subjectsOffered) ?></span>
      <span class="dash-stat-pill__label">Subjects offered</span>
    </a>
    <?php if ($showOpsKpis): ?>
      <a href="<?= $base ?>/attendance" class="dash-stat-pill dash-stat-pill--<?= $attRate >= 80 ? 'green' : ($attRate >= 60 ? 'orange' : 'red') ?>">
        <i class="bi bi-calendar-check"></i>
        <span class="dash-stat-pill__value"><?= $attRate !== null ? $attRate . '%' : '—' ?></span>
        <span class="dash-stat-pill__label">Attendance today</span>
      </a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<!-- Per-school cards -->
<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
  <h5 class="mb-0"><i class="bi bi-grid-3x3-gap me-1"></i> All Schools</h5>
  <a href="<?= $base ?>/schools/create" class="btn btn-sm btn-primary">
    <i class="bi bi-plus-lg"></i> Add school
  </a>
</div>
<div class="schools-grid mb-4">
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
    $hods     = (int)($sc['hod_count']     ?? 0);
    $bursars  = (int)($sc['bursar_count']  ?? 0);
    $classes  = (int)($sc['class_count']   ?? 0);
    $male     = (int)($sc['male_count']    ?? 0);
    $female   = (int)($sc['female_count']  ?? 0);
    $isActive = ($sc['status'] ?? '') === 'active';
  ?>
  <a href="<?= $base ?>/schools/<?= (int)$sc['id'] ?>"
     class="school-card<?= !$isActive ? ' school-card--inactive' : '' ?>">
    <div class="school-card__top">
      <?php if ($logoPath !== ''): ?>
        <img src="<?= View::e($logoPath) ?>" alt="" class="school-card__logo">
      <?php else: ?>
        <div class="school-card__logo-icon"><i class="bi bi-building"></i></div>
      <?php endif; ?>
      <div style="min-width:0;">
        <div class="school-card__name"><?= View::e((string)$sc['name']) ?></div>
        <div class="school-card__code"><?= View::e((string)$sc['code']) ?></div>
      </div>
    </div>

    <div class="school-card__stats">
      <span class="school-card__stat">
        <i class="bi bi-people-fill" style="color:#ea580c;"></i>
        <?= number_format($students) ?> student<?= $students !== 1 ? 's' : '' ?>
      </span>
      <span class="school-card__stat">
        <i class="bi bi-person-workspace" style="color:#16a34a;"></i>
        <?= number_format($staff) ?> staff
      </span>
      <span class="school-card__stat">
        <i class="bi bi-building-fill" style="color:#2563eb;"></i>
        <?= number_format($classes) ?> class<?= $classes !== 1 ? 'es' : '' ?>
      </span>
      <?php if ($hods > 0): ?>
        <span class="school-card__stat">
          <i class="bi bi-mortarboard-fill" style="color:#4f46e5;"></i>
          <?= number_format($hods) ?> HOD<?= $hods !== 1 ? 's' : '' ?>
        </span>
      <?php endif; ?>
      <?php if ($bursars > 0): ?>
        <span class="school-card__stat">
          <i class="bi bi-cash-coin" style="color:#0d9488;"></i>
          <?= number_format($bursars) ?> bursar<?= $bursars !== 1 ? 's' : '' ?>
        </span>
      <?php endif; ?>
      <?php if ($male + $female > 0): ?>
        <span class="school-card__stat">
          <i class="bi bi-gender-ambiguous" style="color:#7c3aed;"></i>
          <?= number_format($male) ?>M / <?= number_format($female) ?>F
        </span>
      <?php endif; ?>
    </div>

    <div class="school-card__footer">
      <span class="badge <?= $isActive ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' ?>">
        <?= $isActive ? 'Active' : 'Inactive' ?>
      </span>
      <span class="small text-muted"><i class="bi bi-arrow-right"></i> View</span>
    </div>
  </a>
  <?php endforeach; ?>
</div>

<!-- Separator before the school-level analytics section -->
<div class="d-flex align-items-center gap-2 mb-2 mt-1">
  <span class="fs-6 fw-semibold text-muted">
    <i class="bi bi-activity me-1"></i>Platform-wide analytics (all schools combined)
  </span>
  <hr class="flex-grow-1 my-0">
</div>

<?php endif; /* end $isAdmin platform overview */ ?>

<?php if ($isAdminish): ?>
<?php if ($isSchoolAdmin && !empty($schoolProfile)): ?>
  <div class="card border-0 shadow-sm mb-2 school-dash-profile">
    <div class="card-body py-3">
      <div class="row g-3 align-items-start">
        <div class="col-md-8">
          <h3 class="h6 fw-semibold mb-2">
            <i class="bi bi-building text-primary"></i>
            <?= View::e((string) $schoolProfile['name']) ?>
          </h3>
          <div class="small text-muted d-flex flex-wrap gap-3">
            <span><strong>Code:</strong> <?= View::e((string) ($schoolProfile['code'] ?? '—')) ?></span>
            <span><strong>Status:</strong> <?= View::e(ucfirst((string) ($schoolProfile['status'] ?? '—'))) ?></span>
          </div>
          <?php
            $addr = trim((string) ($schoolProfile['address'] ?? ''));
            $em   = trim((string) ($schoolProfile['email'] ?? ''));
            $ph   = trim((string) ($schoolProfile['phone'] ?? ''));
          ?>
          <?php if ($addr !== ''): ?>
            <p class="small mb-1 mt-2 mb-0 text-body-secondary"><?= nl2br(View::e($addr)) ?></p>
          <?php endif; ?>
          <div class="small mt-2 d-flex flex-wrap gap-3">
            <?php if ($em !== ''): ?>
              <span><i class="bi bi-envelope me-1"></i><a href="mailto:<?= View::e($em) ?>"><?= View::e($em) ?></a></span>
            <?php endif; ?>
            <?php if ($ph !== ''): ?>
              <span><i class="bi bi-telephone me-1"></i><?= View::e($ph) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-md-4 text-md-end">
          <span class="badge rounded-pill <?= ($schoolProfile['status'] ?? '') === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?> align-middle">
            Your organisation
          </span>
          <p class="small text-muted mt-2 mb-0">
            Contact your system administrator to update school branding and global settings.
          </p>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

  <!-- ============================================================
       Charts
       ============================================================ -->
  <!-- Charts -->
  <div class="row g-3 mb-2">
    <!-- Enrollment: wider + taller -->
    <div class="col-lg-7">
      <div class="card chart-card h-100">
        <div class="card-body">
          <div class="chart-card__head">
            <div>
              <h3 class="chart-card__title">Enrollment per class</h3>
              <p class="chart-card__sub">Students currently enrolled in each class</p>
            </div>
            <div class="chart-card__badge">
              <i class="bi bi-bar-chart-fill"></i>
              <?= number_format($classTotal) ?> total
            </div>
          </div>
          <div style="position:relative;min-height:200px;">
            <?php if (empty($classLabels) || $classTotal === 0): ?>
              <div class="chart-empty" style="height:200px;">
                <div class="text-center">
                  <i class="bi bi-bar-chart d-block mb-2 fs-3 text-subtle"></i>
                  <?= empty($classLabels) ? 'No classes set up yet.' : 'No students enrolled yet.' ?>
                </div>
              </div>
            <?php else: ?>
              <canvas id="enrollmentChart" style="width:100%;height:200px;" aria-label="Enrollment per class" role="img"></canvas>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Demographics + Section stacked on the right -->
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
            <div class="col-5" style="position:relative;height:120px;">
              <?php if ($gTotal === 0): ?>
                <div class="chart-empty" style="height:120px;">No data yet.</div>
              <?php else: ?>
                <canvas id="genderChart" style="width:100%;height:120px;" aria-label="Gender distribution" role="img"></canvas>
              <?php endif; ?>
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
              <p class="chart-card__sub">Day vs Boarding &middot; <?= number_format($sectionTotal) ?></p>
            </div>
          </div>
          <div class="row g-0 align-items-center">
            <div class="col-5" style="position:relative;height:120px;">
              <?php if ($sectionTotal === 0): ?>
                <div class="chart-empty" style="height:120px;">No data yet.</div>
              <?php else: ?>
                <canvas id="sectionChart" style="width:100%;height:120px;" aria-label="Section distribution" role="img"></canvas>
              <?php endif; ?>
            </div>
            <div class="col-7 ps-3">
              <ul class="donut-legend">
                <li><span class="donut-legend__swatch" style="background:#14b8a6"></span><span class="donut-legend__label">Day</span><span class="donut-legend__value"><?= number_format($sectionDay) ?></span></li>
                <li><span class="donut-legend__swatch" style="background:#f59e0b"></span><span class="donut-legend__label">Boarding</span><span class="donut-legend__value"><?= number_format($sectionBoarding) ?></span></li>
              </ul>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- ============================================================
       Bottom row: Recent enrollments + Announcements
       ============================================================ -->
  <div class="section-block mb-1">
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
