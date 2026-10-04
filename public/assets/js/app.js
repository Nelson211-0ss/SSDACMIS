(function () {
  'use strict';

  // --- Confirm-on-submit (kept from original) ----------------------------
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!confirm(f.getAttribute('data-confirm'))) e.preventDefault();
    });
  });

  // --- Auto-applying filter forms -----------------------------------------
  // A form marked data-auto-reload applies itself as soon as a filter
  // changes: immediately for a select, checkbox, radio, date or number, and
  // on blur/Enter for a text or search box (so it doesn't reload on every
  // keystroke). The Apply/Reload/Filter button stays in the markup as a
  // manual fallback for no-JS and for re-submitting an unchanged value.
  //
  // Opt a single control out with data-no-auto — used where a field is part
  // of the form but shouldn't trigger a reload on its own.
  document.querySelectorAll('form[data-auto-reload]').forEach(function (f) {
    var submitting = false;

    // requestSubmit() fires the form's submit listeners; submit() does not.
    // That matters here: the mark-entry sheets hang an "unsaved marks"
    // confirmation off submit, and a silent submit() would sail past it.
    function apply() {
      if (submitting) return;
      submitting = true;
      f.classList.add('is-autofiltering');
      // Disable the manual button so a fast second click can't double-post.
      f.querySelectorAll('button[type="submit"], input[type="submit"]')
        .forEach(function (b) { b.disabled = true; });

      if (typeof f.requestSubmit === 'function') {
        f.requestSubmit();
      } else {
        f.submit();
      }

      // A cancelled submit (the user declined a confirm) has to leave the
      // form usable again, so release the lock once the event has settled.
      window.setTimeout(function () {
        submitting = false;
        f.classList.remove('is-autofiltering');
        f.querySelectorAll('button[type="submit"], input[type="submit"]')
          .forEach(function (b) { b.disabled = false; });
      }, 0);
    }

    var SKIP = ['submit', 'reset', 'button', 'file', 'hidden', 'password'];

    f.querySelectorAll('select, input, textarea').forEach(function (el) {
      if (el.hasAttribute('data-no-auto')) return;
      var type = (el.getAttribute('type') || '').toLowerCase();
      if (SKIP.indexOf(type) !== -1) return;

      // change fires on commit for every control: instantly for selects,
      // checkboxes and radios; on blur for typed text.
      el.addEventListener('change', apply);

      // Enter inside a text field would submit anyway, but doing it through
      // apply() keeps the double-submit guard and the disabled state.
      if (el.tagName === 'INPUT' && (type === 'text' || type === 'search' || type === '')) {
        el.addEventListener('keydown', function (e) {
          if (e.key === 'Enter') {
            e.preventDefault();
            apply();
          }
        });
      }
    });
  });

  // --- Mobile sidebar toggle ---------------------------------------------
  var sidebar = document.getElementById('appSidebar');
  if (sidebar) {
    document.querySelectorAll('[data-sidebar-open]').forEach(function (btn) {
      btn.addEventListener('click', function () { sidebar.classList.add('is-open'); });
    });
    document.querySelectorAll('[data-sidebar-close]').forEach(function (btn) {
      btn.addEventListener('click', function () { sidebar.classList.remove('is-open'); });
    });
    // Close on nav link tap (mobile UX)
    sidebar.querySelectorAll('.app-sidebar__link').forEach(function (a) {
      a.addEventListener('click', function () {
        if (window.matchMedia('(max-width: 991.98px)').matches) {
          sidebar.classList.remove('is-open');
        }
      });
    });
  }

  // --- Desktop sidebar collapse (icons only, per portal) -----------------
  var collapseBtn  = document.querySelector('[data-sidebar-collapse]');
  var collapseIcon = document.querySelector('[data-sidebar-collapse-icon]');
  var appShell     = document.querySelector('.app-shell');
  var sidebarScope = (appShell && appShell.getAttribute('data-sidebar-scope')) || 'main';
  var COLLAPSED_KEY = 'sidebarCollapsed:' + sidebarScope;
  var desktopMq = window.matchMedia('(min-width: 992px)');

  function applySidebarCollapsed(collapsed) {
    document.documentElement.classList.toggle('is-sidebar-collapsed', collapsed);
    // Icon-only mode flattens groups, so their items must not stay hidden.
    if (collapsed) {
      document.querySelectorAll('.app-sidebar__group details').forEach(function (d) { d.open = true; });
    }
    if (collapseIcon) {
      collapseIcon.className = collapsed
        ? 'bi bi-layout-sidebar'
        : 'bi bi-layout-sidebar-inset';
    }
    if (collapseBtn) {
      var label = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
      collapseBtn.setAttribute('aria-label', label);
      collapseBtn.setAttribute('title', label);
    }
  }

  if (collapseBtn) {
    collapseBtn.addEventListener('click', function () {
      if (!desktopMq.matches) return;
      var next = !document.documentElement.classList.contains('is-sidebar-collapsed');
      applySidebarCollapsed(next);
      try { localStorage.setItem(COLLAPSED_KEY, next ? '1' : '0'); } catch (e) {}
    });
    applySidebarCollapsed(document.documentElement.classList.contains('is-sidebar-collapsed'));
  }

  // --- User menu: hover on desktop, click on touch ---------------------
  var userMenu = document.querySelector('[data-user-menu]');
  if (userMenu && typeof bootstrap !== 'undefined') {
    var userTrigger = userMenu.querySelector('[data-user-menu-trigger]');
    var userDd      = bootstrap.Dropdown.getOrCreateInstance(userTrigger, { autoClose: true });
    var hideTimer;

    function openUserMenu() {
      if (!desktopMq.matches) return;
      clearTimeout(hideTimer);
      userDd.show();
    }
    function scheduleHideUserMenu() {
      if (!desktopMq.matches) return;
      hideTimer = setTimeout(function () { userDd.hide(); }, 150);
    }

    userMenu.addEventListener('mouseenter', openUserMenu);
    userMenu.addEventListener('mouseleave', scheduleHideUserMenu);

    userTrigger.addEventListener('click', function (e) {
      e.preventDefault();
      userDd.toggle();
    });
  }

  // --- Light / dark theme toggle -----------------------------------------
  var root        = document.documentElement;
  var lightIcon   = document.querySelector('[data-theme-icon-light]');
  var darkIcon    = document.querySelector('[data-theme-icon-dark]');
  var themeToggle = document.querySelector('[data-theme-toggle]');

  function paintIcons(theme) {
    if (!lightIcon || !darkIcon) return;
    if (theme === 'dark') {
      lightIcon.classList.add('d-none');
      darkIcon.classList.remove('d-none');
    } else {
      lightIcon.classList.remove('d-none');
      darkIcon.classList.add('d-none');
    }
  }
  paintIcons(root.getAttribute('data-bs-theme') || 'light');

  if (themeToggle) {
    themeToggle.addEventListener('click', function () {
      var current = root.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
      var next    = current === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-bs-theme', next);
      try { localStorage.setItem('theme', next); } catch (e) {}
      paintIcons(next);
    });
  }

  // --- Flash toasts (success/info) — pop up, then disappear on their own --
  document.querySelectorAll('.toast-container .toast').forEach(function (el) {
    try {
      bootstrap.Toast.getOrCreateInstance(el, { autohide: true }).show();
    } catch (e) { /* Bootstrap not ready — leave it visible rather than hide silently */ }
  });

  // --- Landscape print fix -------------------------------------------------
  // app.css defines a named "landscape" @page alongside the default portrait
  // @page (used by student report cards etc). Chrome has a known quirk where
  // the LAST page of a multi-page print job can fall back to the default
  // (portrait) page box instead of the named one — e.g. an edge-case trailing
  // page generated right at a page-break boundary. Rather than fight that
  // named-page fallback, we temporarily redefine the DEFAULT @page itself to
  // landscape for the duration of printing, but only on pages that actually
  // contain landscape report content — so any stray fallback page during
  // THIS print job is landscape too, and every other page in the app is
  // unaffected.
  var landscapePrintStyle = null;
  window.addEventListener('beforeprint', function () {
    if (!document.querySelector('.report-page--print-landscape')) return;
    landscapePrintStyle = document.createElement('style');
    landscapePrintStyle.textContent = '@page { size: A4 landscape; margin: 8mm 10mm; }';
    document.head.appendChild(landscapePrintStyle);
  });
  window.addEventListener('afterprint', function () {
    if (landscapePrintStyle && landscapePrintStyle.parentNode) {
      landscapePrintStyle.parentNode.removeChild(landscapePrintStyle);
    }
    landscapePrintStyle = null;
  });
})();
