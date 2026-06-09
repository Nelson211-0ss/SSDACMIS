(function () {
  'use strict';

  // --- Confirm-on-submit (kept from original) ----------------------------
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!confirm(f.getAttribute('data-confirm'))) e.preventDefault();
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

  // --- Auto-dismiss flash alerts -----------------------------------------
  document.querySelectorAll('.alert[data-auto-dismiss]').forEach(function (el) {
    setTimeout(function () {
      try {
        var instance = bootstrap.Alert.getOrCreateInstance(el);
        instance.close();
      } catch (e) { el.remove(); }
    }, 5000);
  });
})();
