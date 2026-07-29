<?php
use App\Core\View;
use App\Core\Flash;
use App\Core\App;
use App\Core\Auth;
use App\Core\Settings;
use App\Core\SchoolIdentity;

$role        = Auth::role() ?? 'guest';
$useHodNav    = Auth::usesHodPortalNav();
$useBursarNav = ($role === 'bursar') || (Auth::portal() === 'bursar');
$pageUri  = $_SERVER['REQUEST_URI'] ?? '';
$pagePath = parse_url($pageUri, PHP_URL_PATH) ?? '';
$relPath  = $base !== '' && str_starts_with($pagePath, $base)
    ? substr($pagePath, strlen($base))
    : $pagePath;
$relPath  = '/' . ltrim($relPath, '/');

$schoolName  = SchoolIdentity::name();
$schoolMotto = SchoolIdentity::motto();
$schoolLogo  = SchoolIdentity::logoUrl();
$theme       = Settings::activeTheme();

/**
 * Sidebar nav groups. Each entry: [label, icon, href, roles[], match-prefix].
 * HODs see ONLY the HOD group (strict access control). Admins/regular
 * staff/students see the full school nav.
 */
$hodNav = [
    ['Overview',          'bi-bar-chart-steps',  '/hod/overview',      ['staff','hod'], '/hod/overview'],
    ['HOD Dashboard',     'bi-mortarboard',       '/hod',               ['staff','hod'], '/hod'],
    ['Students',          'bi-people',            '/hod/students',      ['staff','hod'], '/hod/students'],
    ['Department Marks',  'bi-pencil-square',     '/hod/marks',         ['staff','hod'], '/hod/marks'],
    ['Department Reports','bi-file-earmark-text', '/hod/reports',       ['staff','hod'], '/hod/reports'],
    ['Results',           'bi-graph-up-arrow',    '/hod/results',       ['staff','hod'], '/hod/results'],
    ['Announcements',     'bi-megaphone',         '/hod/announcements', ['staff','hod'], '/hod/announcements'],
];

$bursarNav = [
    ['Dashboard',    'bi-speedometer2',     '/bursar',                  ['bursar'], '/bursar'],
    ['Fees Setup',   'bi-sliders',          '/bursar/structure',        ['bursar'], '/bursar/structure'],
    ['Students',     'bi-people-fill',      '/bursar/students',         ['bursar'], '/bursar/students'],
    ['Payments',     'bi-receipt',          '/bursar/payments',         ['bursar'], '/bursar/payments'],
    ['Paid Report',  'bi-check2-circle',    '/bursar/reports/paid',     ['bursar'], '/bursar/reports/paid'],
    ['Balances',     'bi-graph-down-arrow', '/bursar/reports/balances', ['bursar'], '/bursar/reports/balances'],
    ['Exam Permits', 'bi-shield-check',     '/bursar/exam-permits',     ['bursar'], '/bursar/exam-permits'],
];

$mainNav = [
    ['Overview',      'bi-speedometer2',      '/dashboard',     ['admin','school_admin','staff','student'], '/dashboard'],
    ['Schools',       'bi-building-gear',     '/schools',       ['admin'],                                  '/schools'],
    ['Students',      'bi-people',            '/students',      ['admin','school_admin','staff'],           '/students'],
    ['Staff',         'bi-person-badge',      '/staff',         ['admin','school_admin'],                   '/staff'],
    ['HODs',          'bi-mortarboard-fill',  '/hods',          ['admin','school_admin'],                   '/hods'],
    ['Bursars',       'bi-cash-coin',         '/bursars',       ['admin','school_admin'],                   '/bursars'],
    ['Classes',       'bi-grid',              '/classes',       ['admin','school_admin','staff'],           '/classes'],
    ['Subjects',      'bi-book',              '/subjects',      ['admin','school_admin','staff'],           '/subjects'],
    ['Teaching',      'bi-diagram-3',         '/teaching',      ['admin','school_admin'],                   '/teaching'],
    ['Marks',         'bi-pencil-square',     '/marks',         ['admin','school_admin','staff'],           '/marks'],
    ['Results',       'bi-graph-up-arrow',    '/results',       ['admin','school_admin','staff'],           '/results'],
    ['Reports',       'bi-file-earmark-text', '/reports',       ['admin','school_admin','staff','student'], '/reports'],
    ['Attendance',    'bi-calendar-check',    '/attendance',    ['admin','school_admin','staff'],           '/attendance'],
    // Fees Management Module is bursar-only and lives under /bursar/*.
    // Students still see /fees as a read-only "My fees" page.
    ['My Fees',       'bi-cash-coin',         '/fees',          ['student'],                                '/fees'],
    ['Announcements', 'bi-megaphone',         '/announcements', ['admin','school_admin','staff','student'], '/announcements'],
    ['Settings',      'bi-gear',              '/settings',      ['admin'],                                  '/settings'],
    ['Activity Log',  'bi-clock-history',     '/activity-log',  ['admin','school_admin'],                   '/activity-log'],
];

$initial = strtoupper(mb_substr($auth['name'] ?? '?', 0, 1));
$pageTitle = $title ?? $schoolName;
// Super admin, school admin, Bursar Fees Module, and HOD portal share the enterprise shell.
$useEnterpriseUi = $useBursarNav
    || $useHodNav
    || (in_array($role, ['admin', 'school_admin'], true) && !$useBursarNav && !$useHodNav);
$homeHref = $useBursarNav
    ? $base . '/bursar'
    : ($useHodNav ? $base . '/hod' : $base . '/dashboard');
// Sidebar collapse is remembered per portal (main / hod / bursar) AND per
// school, so one school's toggle never leaks onto another's dashboard in the
// same browser. Super admin (no school_id) gets the "global" scope.
$sidebarSchool = Auth::schoolId() ?? 'global';
$sidebarScope  = Auth::portal() . ':' . $sidebarSchool;
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= View::e($pageTitle) ?> &middot; <?= View::e($schoolName) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Manrope:wght@600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="<?= View::asset($base, 'assets/css/app.css') ?>" rel="stylesheet">
  <link href="<?= View::asset($base, 'assets/css/portal-dash.css') ?>" rel="stylesheet">
  <?php if ($useEnterpriseUi): ?>
  <link href="<?= View::asset($base, 'assets/css/enterprise-admin.css') ?>" rel="stylesheet">
  <?php endif; ?>
  <?php require __DIR__ . '/../partials/favicon.php'; ?>
  <style>
    /* Admin-customized theme - injected per request */
    :root {
      --accent:       <?= View::e($theme['accent']) ?>;
      --accent-hover: <?= View::e($theme['accent_hover']) ?>;
      --accent-soft:  <?= View::e($theme['accent_soft']) ?>;
      --accent-rgb:   <?= View::e($theme['accent_rgb']) ?>;
    }
  </style>
  <script>
    // Apply persisted theme + sidebar state before paint to avoid flash
    (function () {
      try {
        var t = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-bs-theme', t);
        var sidebarScope = <?= json_encode($sidebarScope, JSON_THROW_ON_ERROR) ?>;
        if (localStorage.getItem('sidebarCollapsed:' + sidebarScope) === '1') {
          document.documentElement.classList.add('is-sidebar-collapsed');
        }
      } catch (e) {}
    })();
  </script>
</head>
<body>
<div class="app-shell<?= $useEnterpriseUi ? ' app-shell--enterprise' : '' ?>"
     data-sidebar-scope="<?= View::e($sidebarScope) ?>">

  <aside class="app-sidebar" id="appSidebar">
    <a class="app-sidebar__brand" href="<?= View::e($homeHref) ?>">
      <?php if ($schoolLogo): ?>
        <img src="<?= $base ?>/<?= View::e($schoolLogo) ?>" alt="" class="app-sidebar__brand-logo">
      <?php else: ?>
        <i class="bi bi-mortarboard-fill"></i>
      <?php endif; ?>
      <span class="app-sidebar__brand-text">
        <span class="app-sidebar__brand-name"><?= View::e($schoolName) ?></span>
        <?php if ($schoolMotto !== ''): ?>
          <span class="app-sidebar__brand-motto"><?= View::e($schoolMotto) ?></span>
        <?php endif; ?>
      </span>
      <button type="button"
              class="icon-btn app-sidebar__close ms-auto"
              data-sidebar-close
              aria-label="Close menu">
        <i class="bi bi-x-lg"></i>
      </button>
    </a>

    <ul class="app-sidebar__nav">
      <?php
        // Compute the longest matching prefix among visible items so that a
        // parent route (/hod) does not appear active when a child route
        // (/hod/students) is. Each item only lights up if its prefix is
        // either an exact match or the longest matching prefix.
        $hodVisible = array_values(array_filter($hodNav, fn ($it) => in_array($role, $it[3], true)));
        $bestHodLen = 0;
        foreach ($hodVisible as $it) {
            [$lbl, $ic, $h, $rl, $pf] = $it;
            $p = rtrim($pf, '/');
            if ($relPath === $h || str_starts_with($relPath, $p . '/')) {
                if (strlen($p) > $bestHodLen) $bestHodLen = strlen($p);
            }
        }
      ?>
      <?php if ($useBursarNav): ?>
        <!-- Bursar Fees Management portal: locked down, fees-only items. -->
        <li class="app-sidebar__section">Fees Module</li>
        <?php
          // Compute best-prefix matching for active highlighting (so that
          // /bursar/students doesn't also light up /bursar Dashboard).
          $burVisible = array_values(array_filter($bursarNav, fn ($it) => in_array($role, $it[3], true)));
          $bestBurLen = 0;
          foreach ($burVisible as $it) {
              [$lbl, $ic, $h, $rl, $pf] = $it;
              $p = rtrim($pf, '/');
              if ($relPath === $h || str_starts_with($relPath, $p . '/')) {
                  if (strlen($p) > $bestBurLen) $bestBurLen = strlen($p);
              }
          }
        ?>
        <?php foreach ($bursarNav as [$label, $icon, $href, $roles, $prefix]): ?>
          <?php if (!in_array($role, $roles, true)) continue; ?>
          <?php
            $p = rtrim($prefix, '/');
            $matches = ($relPath === $href || str_starts_with($relPath, $p . '/'));
            $active = $matches && strlen($p) === $bestBurLen;
          ?>
          <li>
            <a class="app-sidebar__link <?= $active ? 'is-active' : '' ?>"
               href="<?= $base . $href ?>"
               title="<?= View::e($label) ?>">
              <i class="bi <?= $icon ?>"></i>
              <span><?= View::e($label) ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      <?php elseif ($useHodNav): ?>
        <!-- HOD portal: locked-down, only HOD-relevant items. -->
        <li class="app-sidebar__section">HOD Portal</li>
        <?php foreach ($hodNav as [$label, $icon, $href, $roles, $prefix]): ?>
          <?php if (!in_array($role, $roles, true)) continue; ?>
          <?php
            $p = rtrim($prefix, '/');
            $matches = ($relPath === $href || str_starts_with($relPath, $p . '/'));
            $active = $matches && strlen($p) === $bestHodLen;
          ?>
          <li>
            <a class="app-sidebar__link <?= $active ? 'is-active' : '' ?>"
               href="<?= $base . $href ?>"
               title="<?= View::e($label) ?>">
              <i class="bi <?= $icon ?>"></i>
              <span><?= View::e($label) ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      <?php else: ?>
        <li class="app-sidebar__section">Main</li>
        <?php foreach ($mainNav as [$label, $icon, $href, $roles, $prefix]): ?>
          <?php if (!in_array($role, $roles, true)) continue; ?>
          <?php
            $active = $relPath === $href
                   || str_starts_with($relPath, rtrim($prefix, '/') . '/');
          ?>
          <li>
            <a class="app-sidebar__link <?= $active ? 'is-active' : '' ?>"
               href="<?= $base . $href ?>"
               title="<?= View::e($label) ?>">
              <i class="bi <?= $icon ?>"></i>
              <span><?= View::e($label) ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      <?php endif; ?>
    </ul>

    <div class="app-sidebar__footer">
      <p class="app-sidebar__footer-school">
        &copy; <?= date('Y') ?> <?= View::e($schoolName) ?>
      </p>
      <div class="app-sidebar__footer-credit">
        <span class="app-sidebar__footer-credit-line">
          <strong>SSD-ACMIS</strong><span class="app-sidebar__footer-credit-by"> by Nelson O. Ochan</span>
        </span>
        <span class="app-sidebar__footer-credit-org">SSD-iT Solutions</span>
      </div>
    </div>
  </aside>
  <div class="app-backdrop" data-sidebar-close></div>

  <header class="app-topbar">
    <button type="button"
            class="icon-btn d-lg-none"
            data-sidebar-open
            aria-label="Open menu">
      <i class="bi bi-list fs-4"></i>
    </button>

    <button type="button"
            class="icon-btn d-none d-lg-inline-grid"
            data-sidebar-collapse
            aria-label="Collapse sidebar"
            title="Collapse sidebar">
      <i class="bi bi-layout-sidebar-inset" data-sidebar-collapse-icon></i>
    </button>

    <div class="app-topbar__title">
      <?= View::e($pageTitle) ?>
    </div>

    <div class="app-topbar__actions">
      <button type="button"
              class="icon-btn"
              data-theme-toggle
              aria-label="Toggle theme"
              title="Toggle light/dark theme">
        <i class="bi bi-sun-fill" data-theme-icon-light></i>
        <i class="bi bi-moon-stars-fill d-none" data-theme-icon-dark></i>
      </button>

      <?php if ($auth): ?>
        <div class="dropdown" data-user-menu>
          <a class="user-chip" href="#" role="button" aria-expanded="false" data-user-menu-trigger>
            <span class="user-chip__avatar"><?= View::e($initial) ?></span>
            <span class="user-chip__meta d-none d-sm-block">
              <strong><?= View::e($auth['name']) ?></strong><br>
              <small class="text-capitalize"><?= View::e($role) ?></small>
            </span>
            <i class="bi bi-chevron-down small ms-1 text-muted"></i>
          </a>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm" data-user-menu-panel>
            <li class="dropdown-header">
              <div class="fw-semibold"><?= View::e($auth['name']) ?></div>
              <div class="small text-muted text-capitalize"><?= View::e($role) ?> account</div>
            </li>
            <li><hr class="dropdown-divider"></li>
            <?php if ($useBursarNav): ?>
              <li>
                <a class="dropdown-item" href="<?= $base ?>/bursar">
                  <i class="bi bi-cash-coin me-2"></i> Fees dashboard
                </a>
              </li>
            <?php elseif ($useHodNav): ?>
              <li>
                <a class="dropdown-item" href="<?= $base ?>/hod">
                  <i class="bi bi-mortarboard me-2"></i> Department home
                </a>
              </li>
            <?php else: ?>
              <li>
                <a class="dropdown-item" href="<?= $base ?>/dashboard">
                  <i class="bi bi-speedometer2 me-2"></i> Overview
                </a>
              </li>
            <?php endif; ?>
            <?php if ($role === 'admin'): ?>
              <li>
                <a class="dropdown-item" href="<?= $base ?>/settings">
                  <i class="bi bi-gear me-2"></i>Settings
                </a>
              </li>
            <?php endif; ?>
            <li>
              <a class="dropdown-item" href="<?= $base ?>/account/password">
                <i class="bi bi-key me-2"></i>Change Password
              </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <?php
                // Use the portal-prefixed logout so we only kill the session
                // slot of the portal we're currently in (the other tab keeps
                // its sign-in).
                $logoutHref = $base . ($useBursarNav
                    ? '/bursar/logout'
                    : ($useHodNav ? '/hod/logout' : '/logout'));
              ?>
              <a class="dropdown-item text-danger" href="<?= $logoutHref ?>">
                <i class="bi bi-box-arrow-right me-2"></i>Sign out
              </a>
            </li>
          </ul>
        </div>
      <?php endif; ?>
    </div>
  </header>

  <main class="app-main">
    <?php foreach (Flash::pull() as $f): ?>
      <?php $sticky = in_array($f['type'], ['danger', 'warning'], true); ?>
      <div class="alert alert-<?= View::e($f['type']) ?> alert-dismissible fade show" role="alert"
           <?= $sticky ? '' : 'data-auto-dismiss' ?>>
        <?= View::e($f['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endforeach; ?>

    <?php if ($useBursarNav):
      // Period selector for the Fees Module — academic year + term scope
      // every bursar page below. Skipped on the printable receipt/report
      // routes (they don't use this layout).
      $period = \App\Services\FeesService::activePeriod();
      include dirname(__DIR__) . '/bursar/_period_bar.php';
    endif; ?>

    <?php if (!$useBursarNav && !$useHodNav): ?>
      <div class="app-page">
        <?= $content ?? '' ?>
      </div>
    <?php else: ?>
      <?= $content ?? '' ?>
    <?php endif; ?>
  </main>
</div>

<!-- Reusable shell for "Add …" forms loaded into a modal (students, staff, HODs, bursars, schools). -->
<div class="modal fade entity-modal" id="entityFormModal" tabindex="-1" aria-labelledby="entityFormModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-primary text-white border-0">
        <h5 class="modal-title text-white d-flex align-items-center gap-2 mb-0" id="entityFormModalTitle">
          <span class="entity-modal__title-icon"><i class="bi bi-plus-lg"></i></span>
          <span data-entity-modal-title>Add</span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" data-entity-modal-body></div>
    </div>
  </div>
</div>
<style>
/* Shared "Add …" entity modal — matches app card radius, compact body, white header text. */
.entity-modal .modal-content {
  border-radius: var(--card-radius, 0.375rem);
  border: 1px solid var(--border, #e7e9ee);
  box-shadow: var(--shadow-md, 0 2px 8px rgba(17, 24, 39, 0.08));
  overflow: hidden;
}
.entity-modal .modal-header {
  background-color: var(--accent) !important;
  border-bottom: 0;
  padding: 0.7rem 1rem;
  border-radius: var(--card-radius, 0.375rem) var(--card-radius, 0.375rem) 0 0;
}
.entity-modal .modal-header .modal-title,
.entity-modal .modal-header .modal-title span,
.entity-modal .modal-header [data-entity-modal-title],
.entity-modal__title-icon,
.entity-modal__title-icon i {
  color: #fff !important;
  font-weight: 700;
  font-size: 1rem;
}
.entity-modal__title-icon {
  display: inline-grid;
  place-items: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: var(--radius-sm, 0.375rem);
  background: rgba(255, 255, 255, 0.18);
  font-size: 0.9rem;
}
.entity-modal .modal-body { padding: 0; }

/* Dashboard blue theme — all popup entity forms */
.entity-modal .entity-form__col-title {
  color: var(--accent);
  border-bottom-color: color-mix(in srgb, var(--accent) 22%, var(--border));
}
.entity-modal .card-header-icon {
  background: var(--accent-soft) !important;
  color: var(--accent) !important;
}
.entity-modal .entity-form__panel {
  background: color-mix(in srgb, var(--accent-soft) 50%, var(--surface));
  border: 1px solid color-mix(in srgb, var(--accent) 14%, var(--border));
  border-radius: var(--radius-sm, 0.375rem);
}
.entity-modal .entity-form__preview {
  background: color-mix(in srgb, var(--accent-soft) 55%, var(--surface));
  border-color: color-mix(in srgb, var(--accent) 28%, var(--border));
}
.entity-modal .entity-form__preview-label {
  color: var(--accent);
}
.entity-modal .entity-form__subject-pick {
  border: 1px solid color-mix(in srgb, var(--accent) 12%, var(--border));
  background: color-mix(in srgb, var(--accent-soft) 35%, var(--surface));
  border-radius: var(--radius-sm, 0.375rem);
}
.entity-modal .form-control:focus,
.entity-modal .form-select:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(var(--accent-rgb), 0.15);
}
.entity-modal .sa-profile h6 {
  color: var(--accent);
}
.entity-modal .sa-profile h6 i {
  color: var(--accent);
}

/* ── Compact form body ───────────────────────────────────────────── */
.entity-modal .entity-form {
  padding: 0.75rem 1rem 0;
  max-width: none;
  margin: 0;
}
.entity-modal .entity-form .row.g-3,
.entity-modal .entity-form .row.g-2 {
  --bs-gutter-y: 0.45rem;
  --bs-gutter-x: 0.65rem;
}
.entity-modal .mb-2 { margin-bottom: 0.3rem !important; }
.entity-modal .mb-3 { margin-bottom: 0.45rem !important; }
.entity-modal .mb-4 { margin-bottom: 0.55rem !important; }
.entity-modal .mt-3 { margin-top: 0.55rem !important; }
.entity-modal .form-label { margin-bottom: 0.2rem !important; font-size: 0.78rem; }
.entity-modal .form-control,
.entity-modal .form-select {
  font-size: 0.8125rem;
  padding: 0.3rem 0.55rem;
  border-radius: var(--radius-sm, 0.375rem);
}
.entity-modal .form-control-sm,
.entity-modal .form-select-sm { padding: 0.25rem 0.45rem; }
.entity-modal .entity-form__col-title {
  margin-bottom: 0.35rem;
  padding-bottom: 0.2rem;
  font-size: 0.625rem;
}
.entity-modal .entity-form__card {
  border: 1px solid var(--border, #e7e9ee) !important;
  border-inline-start: 3px solid var(--accent) !important;
  border-radius: var(--card-radius, 0.375rem) !important;
  box-shadow: var(--shadow-sm) !important;
  background: var(--surface, #fff) !important;
  margin-bottom: 0;
}
.entity-modal .entity-form__card > .card-body { padding: 0.55rem 0.65rem; }
.entity-modal .entity-form__panel { padding: 0.4rem 0.5rem; }
.entity-modal .entity-form__preview { padding: 0.35rem 0.45rem; }
.entity-modal .form-text { display: none; }
.entity-modal p.small.text-muted:not(.entity-form__hint) { display: none; }
.entity-modal textarea.form-control { min-height: 2.25rem; resize: vertical; }
.entity-modal .entity-form__hint { font-size: 0.7rem; display: block !important; }

/* Staff subject picker — scroll instead of stretching the modal */
.entity-modal .entity-form__subject-pick {
  max-height: 7.5rem;
  overflow-y: auto;
  padding: 0.35rem 0.45rem;
}
.entity-modal .entity-form__subject-pick .form-check { margin-bottom: 0.15rem; }

/* HOD / Bursar — hide the portal info column in the popup */
.entity-modal .entity-form__split > .col-xl-5:last-child { display: none; }
.entity-modal .entity-form__split > .col-xl-7 {
  flex: 0 0 100%;
  max-width: 100%;
  border-inline-end: 0 !important;
  margin-bottom: 0 !important;
}

/* Student passport photo — single compact row */
.entity-modal #studentPhotoCard {
  margin-top: 0.5rem !important;
  padding-top: 0 !important;
  border-top: 0 !important;
}
.entity-modal #studentPhotoCard > .card-body { padding: 0.45rem 0.65rem !important; }
.entity-modal #studentPhotoCard .entity-form__col-title { margin-bottom: 0.25rem; }
.entity-modal #studentPhotoCard .row { --bs-gutter-y: 0.35rem; --bs-gutter-x: 0.5rem; align-items: center; }
.entity-modal #studentPhotoCard .col-md-4 { flex: 0 0 auto; width: auto; max-width: 5.5rem; }
.entity-modal #studentPhotoCard .col-md-8 { flex: 1 1 auto; width: auto; }
.entity-modal #studentPhotoFrame { max-width: 5rem !important; }
.entity-modal #studentPhotoPlaceholder i { font-size: 1.35rem !important; }
.entity-modal #studentPhotoCard .small.text-muted.mt-2 { font-size: 0.6rem; margin-top: 0.15rem !important; }
.entity-modal #photoUploadPane .form-label { margin-bottom: 0.15rem !important; }

/* Schools form inside modal */
.entity-modal .sa-profile {
  border: 1px solid color-mix(in srgb, var(--accent) 14%, var(--border)) !important;
  border-inline-start: 3px solid var(--accent) !important;
  border-radius: var(--card-radius, 0.375rem) !important;
  box-shadow: var(--shadow-sm) !important;
  background: var(--surface, #fff) !important;
  margin: 0.75rem 1rem 0;
}
.entity-modal .sa-profile > .card-body {
  padding: 0.65rem 0.75rem 0 !important;
}
.entity-modal .sa-profile h6 {
  font-size: 0.625rem;
  margin-bottom: 0.4rem !important;
  margin-top: 0.35rem !important;
  padding-top: 0.35rem !important;
}
.entity-modal .sa-profile h6.border-top { margin-top: 0.5rem !important; }

/* Bottom action bar — flush with card corners */
.entity-modal .entity-form__actions {
  margin: 0.65rem 0 0;
  padding: 0.6rem 1rem;
  background: color-mix(in srgb, var(--accent-soft) 45%, var(--surface));
  border-top: 1px solid color-mix(in srgb, var(--accent) 16%, var(--border));
  border-radius: 0 0 var(--card-radius, 0.375rem) var(--card-radius, 0.375rem);
}
.entity-modal .entity-form__actions .btn {
  min-width: 6.5rem;
  font-size: 0.8125rem;
  padding: 0.35rem 0.85rem;
}
.entity-modal .entity-form__actions .btn-primary {
  background-color: var(--accent);
  border-color: var(--accent);
}
.entity-modal .entity-form__actions .btn-primary:hover,
.entity-modal .entity-form__actions .btn-primary:focus {
  background-color: var(--accent-hover);
  border-color: var(--accent-hover);
}
.entity-modal .sa-profile .entity-form__actions {
  margin: 0.65rem -0.75rem 0;
  padding: 0.6rem 0.75rem;
}

/* Student popup — narrower, fits viewport without inner scroll */
.entity-modal .modal-dialog.entity-modal--student {
  max-width: 48rem;
  width: calc(100% - 1.5rem);
  margin: 0.75rem auto;
}
.entity-modal .modal-dialog.entity-modal--student .entity-form {
  padding: 0.55rem 0.75rem 0;
}
.entity-modal .modal-dialog.entity-modal--student .entity-form__card > .card-body {
  padding: 0.4rem 0.5rem;
}
.entity-modal .modal-dialog.entity-modal--student .entity-form__split {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.45rem;
  --bs-gutter-x: 0;
  --bs-gutter-y: 0;
}
.entity-modal .modal-dialog.entity-modal--student .entity-form__split > [class*="col-xl-4"] {
  flex: none;
  width: auto;
  max-width: none;
  border-inline-end: 0 !important;
  padding-inline-end: 0 !important;
  margin-bottom: 0 !important;
}
.entity-modal .modal-dialog.entity-modal--student .entity-form__col-title {
  margin-bottom: 0.25rem;
  padding-bottom: 0.15rem;
  font-size: 0.58rem;
}
.entity-modal .modal-dialog.entity-modal--student .mb-2 {
  margin-bottom: 0.2rem !important;
}
.entity-modal .modal-dialog.entity-modal--student .entity-form__preview {
  padding: 0.25rem 0.35rem;
  margin-bottom: 0.2rem !important;
}
.entity-modal .modal-dialog.entity-modal--student .btn-group .btn {
  padding-top: 0.2rem;
  padding-bottom: 0.2rem;
  font-size: 0.68rem;
}
.entity-modal .modal-dialog.entity-modal--student #studentPhotoCard {
  margin-top: 0.4rem !important;
}
.entity-modal .modal-dialog.entity-modal--student #studentPhotoCard > .card-body {
  padding: 0.35rem 0.5rem !important;
}
.entity-modal .modal-dialog.entity-modal--student #studentPhotoCard .entity-form__col-title {
  margin-bottom: 0.15rem;
}
.entity-modal .modal-dialog.entity-modal--student #studentPhotoCard .row {
  flex-direction: row;
  align-items: center;
  --bs-gutter-y: 0;
  --bs-gutter-x: 0.45rem;
}
.entity-modal .modal-dialog.entity-modal--student #studentPhotoCard .col-md-4 {
  flex: 0 0 auto;
  width: auto;
  max-width: 4.25rem;
}
.entity-modal .modal-dialog.entity-modal--student #studentPhotoCard .col-md-8 {
  flex: 1 1 auto;
  width: auto;
  max-width: none;
}
.entity-modal .modal-dialog.entity-modal--student #studentPhotoFrame {
  max-width: 4rem !important;
}
.entity-modal .modal-dialog.entity-modal--student #studentPhotoCard .small.text-muted.mt-2 {
  display: none;
}
.entity-modal .modal-dialog.entity-modal--student #photoUploadPane .form-label {
  font-size: 0.68rem;
  margin-bottom: 0.1rem !important;
}
.entity-modal .modal-dialog.entity-modal--student textarea[name="address"] {
  min-height: 1.75rem;
  height: 1.75rem;
  resize: none;
}
.entity-modal .modal-dialog.entity-modal--student .entity-form__actions {
  margin-top: 0.45rem;
  padding: 0.45rem 0.75rem;
}
@media (max-width: 767.98px) {
  .entity-modal .modal-dialog.entity-modal--student {
    max-width: calc(100% - 1rem);
  }
  .entity-modal .modal-dialog.entity-modal--student .entity-form__split {
    grid-template-columns: 1fr;
  }
}

/* Staff popup — narrower width, taller stacked layout */
.entity-modal .modal-dialog.entity-modal--staff {
  max-width: 36rem;
  width: calc(100% - 1.5rem);
  margin: 0.75rem auto;
}
.entity-modal .modal-dialog.entity-modal--staff .modal-content {
  min-height: 30rem;
}
.entity-modal .modal-dialog.entity-modal--staff .entity-form {
  padding: 0.65rem 0.85rem 0;
}
.entity-modal .modal-dialog.entity-modal--staff .entity-form__card > .card-body {
  padding: 0.5rem 0.55rem;
}
.entity-modal .modal-dialog.entity-modal--staff .entity-form__split {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  --bs-gutter-x: 0;
  --bs-gutter-y: 0;
}
.entity-modal .modal-dialog.entity-modal--staff .entity-form__split > .col-xl-6 {
  flex: 0 0 100%;
  max-width: 100%;
  width: 100%;
  border-inline-end: 0 !important;
  padding-inline-end: 0 !important;
  margin-bottom: 0 !important;
}
.entity-modal .modal-dialog.entity-modal--staff .entity-form__col-title {
  margin-bottom: 0.35rem;
  padding-bottom: 0.2rem;
}
.entity-modal .modal-dialog.entity-modal--staff .entity-form__subject-pick {
  max-height: 13rem;
}
.entity-modal .modal-dialog.entity-modal--staff .entity-form__split > .col-xl-6:last-child .row.g-2 {
  --bs-gutter-y: 0.35rem;
  --bs-gutter-x: 0.5rem;
}
.entity-modal .modal-dialog.entity-modal--staff .entity-form__actions {
  margin-top: 0.55rem;
  padding: 0.55rem 0.85rem;
}
@media (max-width: 575.98px) {
  .entity-modal .modal-dialog.entity-modal--staff {
    max-width: calc(100% - 1rem);
  }
  .entity-modal .modal-dialog.entity-modal--staff .modal-content {
    min-height: auto;
  }
}

/* HOD popup — narrower, slightly taller (same family as staff) */
.entity-modal .modal-dialog.entity-modal--hod {
  max-width: 34rem;
  width: calc(100% - 1.5rem);
  margin: 0.75rem auto;
}
.entity-modal .modal-dialog.entity-modal--hod .modal-content {
  min-height: 22rem;
}
.entity-modal .modal-dialog.entity-modal--hod .entity-form {
  padding: 0.65rem 0.85rem 0;
}
.entity-modal .modal-dialog.entity-modal--hod .entity-form__card > .card-body {
  padding: 0.55rem 0.6rem;
}
.entity-modal .modal-dialog.entity-modal--hod .entity-form__actions {
  margin-top: 0.55rem;
  padding: 0.55rem 0.85rem;
}

/* Bursar popup — same proportions as HOD */
.entity-modal .modal-dialog.entity-modal--bursar {
  max-width: 34rem;
  width: calc(100% - 1.5rem);
  margin: 0.75rem auto;
}
.entity-modal .modal-dialog.entity-modal--bursar .modal-content {
  min-height: 22rem;
}
.entity-modal .modal-dialog.entity-modal--bursar .entity-form {
  padding: 0.65rem 0.85rem 0;
}
.entity-modal .modal-dialog.entity-modal--bursar .entity-form__card > .card-body {
  padding: 0.55rem 0.6rem;
}
.entity-modal .modal-dialog.entity-modal--bursar .entity-form__actions {
  margin-top: 0.55rem;
  padding: 0.55rem 0.85rem;
}

/* School popup — narrower, taller scroll-friendly body */
.entity-modal .modal-dialog.entity-modal--school {
  max-width: 36rem;
  width: calc(100% - 1.5rem);
  margin: 0.75rem auto;
}
.entity-modal .modal-dialog.entity-modal--school .modal-content {
  min-height: 28rem;
}
.entity-modal .modal-dialog.entity-modal--school .sa-profile {
  margin: 0.65rem 0.85rem 0;
}
.entity-modal .modal-dialog.entity-modal--school .sa-profile > .card-body {
  padding: 0.55rem 0.65rem 0 !important;
}
.entity-modal .modal-dialog.entity-modal--school .sa-profile .entity-form__actions {
  margin: 0.55rem -0.65rem 0;
  padding: 0.55rem 0.65rem;
}

/* Full-page entity forms on the dashboard — same blue theme as popups */
.app-page .entity-form__col-title {
  color: var(--accent);
  border-bottom-color: color-mix(in srgb, var(--accent) 22%, var(--border));
}
.app-page .entity-form .card-header-icon {
  background: var(--accent-soft) !important;
  color: var(--accent) !important;
}
.app-page .entity-form__panel {
  background: color-mix(in srgb, var(--accent-soft) 50%, var(--surface));
  border: 1px solid color-mix(in srgb, var(--accent) 14%, var(--border));
}
.app-page .entity-form__preview {
  background: color-mix(in srgb, var(--accent-soft) 55%, var(--surface));
  border-color: color-mix(in srgb, var(--accent) 28%, var(--border));
}
.app-page .entity-form__preview-label {
  color: var(--accent);
}
.app-page .entity-form__subject-pick {
  border: 1px solid color-mix(in srgb, var(--accent) 12%, var(--border));
  background: color-mix(in srgb, var(--accent-soft) 35%, var(--surface));
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= View::asset($base, 'assets/js/app.js') ?>"></script>
<script src="<?= View::asset($base, 'assets/js/entity-modal.js') ?>"></script>
<script>
  // Inline print: load any URL inside a hidden iframe and trigger the print
  // dialog from there, so receipts/reports never spawn a new tab or window
  // and the user stays on the dashboard the whole time.
  (function () {
    function printInline(url) {
      var prev = document.getElementById('inlinePrintFrame');
      if (prev) prev.remove();

      var iframe = document.createElement('iframe');
      iframe.id = 'inlinePrintFrame';
      iframe.setAttribute('aria-hidden', 'true');
      iframe.style.cssText =
        'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;';
      iframe.src = url;

      var done = false;
      function cleanup() {
        if (done) return;
        done = true;
        setTimeout(function () { iframe.remove(); }, 500);
      }

      iframe.onload = function () {
        try {
          iframe.contentWindow.focus();
          var w = iframe.contentWindow;
          if (w.addEventListener) {
            w.addEventListener('afterprint', cleanup, { once: true });
          }
          // Small delay lets fonts/CSS settle before the dialog opens.
          setTimeout(function () {
            try { w.print(); } catch (err) { window.open(url, '_blank'); cleanup(); }
          }, 250);
        } catch (err) {
          window.open(url, '_blank');
          cleanup();
        }
        // Safety net in case afterprint never fires (some browsers).
        setTimeout(cleanup, 60000);
      };

      document.body.appendChild(iframe);
    }
    window.printInline = printInline;

    document.addEventListener('click', function (e) {
      var trigger = e.target.closest('[data-inline-print]');
      if (!trigger) return;
      e.preventDefault();
      var url = trigger.getAttribute('href')
             || trigger.getAttribute('data-inline-print');
      if (url) printInline(url);
    });
  })();
</script>
<script>
  // Suppress the browser's auto-injected page header on print
  // (e.g. "Class Report Cards · Fr.leopoldo college"). Browsers print
  // whatever is in <title>; we blank it during the print dialog and
  // restore it afterwards so tab titles stay intact during normal use.
  (function () {
    var original = document.title;
    var blank    = '\u00A0'; // non-breaking space — most browsers render the header empty
    window.addEventListener('beforeprint', function () { document.title = blank; });
    window.addEventListener('afterprint',  function () { document.title = original; });
    // Safari fallback (no afterprint event in some versions).
    if (window.matchMedia) {
      var mql = window.matchMedia('print');
      var handler = function (m) { document.title = m.matches ? blank : original; };
      if (mql.addEventListener) mql.addEventListener('change', handler);
      else if (mql.addListener) mql.addListener(handler);
    }
  })();
</script>
</body>
</html>
