<?php
use App\Core\View;
use App\Core\Flash;
use App\Core\App;
use App\Core\Auth;
use App\Core\Settings;

$role        = Auth::role() ?? 'guest';
$useHodNav    = Auth::usesHodPortalNav();
$useBursarNav = ($role === 'bursar') || (Auth::portal() === 'bursar');
$pageUri  = $_SERVER['REQUEST_URI'] ?? '';
$pagePath = parse_url($pageUri, PHP_URL_PATH) ?? '';
$relPath  = $base !== '' && str_starts_with($pagePath, $base)
    ? substr($pagePath, strlen($base))
    : $pagePath;
$relPath  = '/' . ltrim($relPath, '/');

$schoolName  = Settings::get('school_name') ?: App::config('app.name');
$schoolMotto = Settings::get('school_motto') ?? '';
$schoolLogo  = Settings::logoUrl();
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
    ['Dashboard',     'bi-speedometer2',    '/dashboard',     ['admin','staff','student'], '/dashboard'],
    ['Students',      'bi-people',          '/students',      ['admin','staff'],           '/students'],
    ['Staff',         'bi-person-badge',    '/staff',         ['admin'],                   '/staff'],
    ['HODs',          'bi-mortarboard-fill','/hods',          ['admin'],                   '/hods'],
    ['Bursars',       'bi-cash-coin',       '/bursars',       ['admin'],                   '/bursars'],
    ['Classes',       'bi-building',        '/classes',       ['admin','staff'],           '/classes'],
    ['Subjects',      'bi-book',            '/subjects',      ['admin','staff'],           '/subjects'],
    ['Teaching',      'bi-diagram-3',       '/teaching',      ['admin'],                   '/teaching'],
    ['Marks',         'bi-pencil-square',   '/marks',         ['admin','staff'],           '/marks'],
    ['Results',       'bi-graph-up-arrow',  '/results',       ['admin','staff'],           '/results'],
    ['Reports',       'bi-file-earmark-text','/reports',      ['admin','staff','student'], '/reports'],
    ['Attendance',    'bi-calendar-check',  '/attendance',    ['admin','staff'],           '/attendance'],
    // Fees Management Module is bursar-only and lives under /bursar/*.
    // Students still see /fees as a read-only "My fees" page.
    ['My Fees',       'bi-cash-coin',       '/fees',          ['student'],                 '/fees'],
    ['Announcements', 'bi-megaphone',       '/announcements', ['admin','staff','student'], '/announcements'],
    ['Settings',      'bi-gear',            '/settings',      ['admin'],                   '/settings'],
];

$initial = strtoupper(mb_substr($auth['name'] ?? '?', 0, 1));
$pageTitle = $title ?? $schoolName;
$homeHref = $useBursarNav
    ? $base . '/bursar'
    : ($useHodNav ? $base . '/hod' : $base . '/dashboard');
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= View::e($pageTitle) ?> &middot; <?= View::e($schoolName) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="<?= View::asset($base, 'assets/css/app.css') ?>" rel="stylesheet">
  <?php if ($schoolLogo): ?>
    <link rel="icon" type="image/png" href="<?= $base ?>/<?= View::e($schoolLogo) ?>">
  <?php endif; ?>
  <style>
    /* Admin-customized theme - injected per request */
    :root {
      --accent:       <?= View::e($theme['accent']) ?>;
      --accent-hover: <?= View::e($theme['accent_hover']) ?>;
      --accent-soft:  <?= View::e($theme['accent_soft']) ?>;
      --accent-rgb:   <?= View::e($theme['accent_rgb']) ?>;
      --sidebar-bg:   <?= View::e($theme['sidebar_bg']) ?>;
    }
  </style>
  <script>
    // Apply persisted theme before paint to avoid flash
    (function () {
      try {
        var t = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-bs-theme', t);
      } catch (e) {}
    })();
  </script>
</head>
<body>
<div class="app-shell">

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
               href="<?= $base . $href ?>">
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
               href="<?= $base . $href ?>">
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
               href="<?= $base . $href ?>">
              <i class="bi <?= $icon ?>"></i>
              <span><?= View::e($label) ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      <?php endif; ?>
    </ul>

    <div class="app-sidebar__footer">
      <div class="app-sidebar__footer-school">
        &copy; <?= date('Y') ?> <?= View::e($schoolName) ?>
      </div>
      <div class="app-sidebar__footer-credit">
        <strong>SSD-ACMIS</strong>
        <span class="text-muted"> by Nelson O. Ochan</span><br>
        <span class="text-muted">SSD-iT Solutions</span>
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
        <div class="dropdown">
          <a class="user-chip" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="user-chip__avatar"><?= View::e($initial) ?></span>
            <span class="user-chip__meta d-none d-sm-block">
              <strong><?= View::e($auth['name']) ?></strong><br>
              <small class="text-capitalize"><?= View::e($role) ?></small>
            </span>
            <i class="bi bi-chevron-down small ms-1 text-muted"></i>
          </a>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm">
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
                  <i class="bi bi-speedometer2 me-2"></i> Dashboard
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

    <?= $content ?? '' ?>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= View::asset($base, 'assets/js/app.js') ?>"></script>
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
