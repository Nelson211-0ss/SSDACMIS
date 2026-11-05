/*
 * Session-timeout dialog.
 *
 * The server signs a user out after `session.lifetime` seconds without a
 * request (App::enforceIdleTimeout). This script mirrors that timer in the
 * browser so the user is told, instead of finding out on their next click:
 *
 *   - shortly before the timeout, a warning dialog counts down and offers
 *     "Stay signed in" (pings the server) or "Sign out";
 *   - once it has expired, a dialog that cannot be dismissed says so and
 *     sends them to the sign-in page.
 *
 * Before showing either dialog it asks /session/status, which reads the
 * server timer without extending it, so activity in another tab (which
 * shares the same session) is respected. Any fetch/XHR this page makes
 * (e.g. marks autosave) also counts as activity.
 *
 * Config comes from data-* attributes on this script's own <script> tag.
 */
(function () {
  'use strict';

  var tag = document.currentScript;
  if (!tag) return;
  var lifetime  = parseInt(tag.getAttribute('data-lifetime') || '0', 10);
  var statusUrl = tag.getAttribute('data-status-url');
  var pingUrl   = tag.getAttribute('data-ping-url');
  var loginUrl  = tag.getAttribute('data-login-url');
  var logoutUrl = tag.getAttribute('data-logout-url');
  if (!lifetime || !statusUrl) return;

  // Warn 2 minutes ahead (or a quarter of the lifetime if that's shorter).
  var WARN_SECONDS = Math.min(120, Math.floor(lifetime / 4));

  var deadline = Date.now() + lifetime * 1000;
  var state = 'idle';     // idle | checking | warning | expired
  var modal = null, modalEl = null, countdownEl = null;

  /* ---- Count this page's own requests as activity --------------------- */

  function isOwnRequest(url) {
    try {
      var u = new URL(String(url), window.location.href);
      return u.origin === window.location.origin && u.pathname.indexOf('/session/status') === -1;
    } catch (e) { return false; }
  }
  function bump() {
    if (state === 'expired') return;
    deadline = Date.now() + lifetime * 1000;
  }
  if (window.fetch) {
    var origFetch = window.fetch;
    window.fetch = function (input) {
      var url = (input && typeof input === 'object' && 'url' in input) ? input.url : input;
      var p = origFetch.apply(this, arguments);
      if (isOwnRequest(url)) {
        p.then(function (r) { if (r && r.ok && !r.redirected) bump(); }, function () {});
      }
      return p;
    };
  }
  if (window.XMLHttpRequest) {
    var origOpen = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function (method, url) {
      if (isOwnRequest(url)) {
        this.addEventListener('load', function () { if (this.status >= 200 && this.status < 400) bump(); });
      }
      return origOpen.apply(this, arguments);
    };
  }

  /* ---- Dialog ---------------------------------------------------------- */

  function fmt(sec) {
    sec = Math.max(0, Math.ceil(sec));
    var m = Math.floor(sec / 60), s = sec % 60;
    return m + ':' + (s < 10 ? '0' : '') + s;
  }

  function buildModal() {
    if (modalEl) return;
    modalEl = document.createElement('div');
    modalEl.className = 'modal fade';
    modalEl.id = 'sessionTimeoutModal';
    modalEl.tabIndex = -1;
    modalEl.setAttribute('aria-hidden', 'true');
    modalEl.setAttribute('data-bs-backdrop', 'static');
    modalEl.setAttribute('data-bs-keyboard', 'false');
    modalEl.innerHTML =
      '<div class="modal-dialog modal-dialog-centered" style="max-width:26rem">' +
        '<div class="modal-content border-0 shadow-lg rounded-4">' +
          '<div class="modal-body p-4 text-center">' +
            '<div class="session-timeout__icon mb-3" data-st-icon></div>' +
            '<h5 class="fw-bold mb-2" data-st-title></h5>' +
            '<p class="text-body-secondary mb-0" data-st-text></p>' +
          '</div>' +
          '<div class="modal-footer border-0 pt-0 px-4 pb-4 justify-content-center gap-2" data-st-actions></div>' +
        '</div>' +
      '</div>';
    document.body.appendChild(modalEl);
    if (window.bootstrap && bootstrap.Modal) {
      modal = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: false });
    }
  }

  function showModal() {
    if (modal) {
      modal.show();
    } else {
      // Bootstrap not loaded: fall back to a plain visible overlay.
      modalEl.style.display = 'block';
      modalEl.classList.add('show');
      modalEl.style.background = 'rgba(0,0,0,.5)';
    }
  }

  function hideModal() {
    if (!modalEl) return;
    if (modal) {
      modal.hide();
    } else {
      modalEl.style.display = 'none';
      modalEl.classList.remove('show');
    }
  }

  function render(kind) {
    buildModal();
    var icon = modalEl.querySelector('[data-st-icon]');
    var title = modalEl.querySelector('[data-st-title]');
    var text = modalEl.querySelector('[data-st-text]');
    var actions = modalEl.querySelector('[data-st-actions]');
    actions.innerHTML = '';

    if (kind === 'warning') {
      icon.innerHTML = '<i class="bi bi-hourglass-split fs-1 text-warning"></i>';
      title.textContent = 'Your session is about to expire';
      text.innerHTML = 'You have been inactive for a while. For your security you will be signed out in ' +
        '<strong class="font-monospace" data-st-countdown></strong>.';
      countdownEl = text.querySelector('[data-st-countdown]');

      var out = document.createElement('a');
      out.className = 'btn btn-outline-secondary';
      out.href = logoutUrl;
      out.textContent = 'Sign out';
      var stay = document.createElement('button');
      stay.type = 'button';
      stay.className = 'btn btn-primary';
      stay.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Stay signed in';
      stay.addEventListener('click', staySignedIn);
      actions.appendChild(out);
      actions.appendChild(stay);
      setTimeout(function () { stay.focus(); }, 300);
    } else {
      icon.innerHTML = '<i class="bi bi-lock fs-1 text-danger"></i>';
      title.textContent = 'Session expired';
      text.textContent = 'You were signed out because you were inactive for too long. ' +
        'Please sign in again to continue. Anything you had not saved on this page was not kept.';
      countdownEl = null;

      var again = document.createElement('a');
      again.className = 'btn btn-primary';
      again.href = loginUrl;
      again.innerHTML = '<i class="bi bi-box-arrow-in-right me-1"></i>Sign in again';
      actions.appendChild(again);
      setTimeout(function () { again.focus(); }, 300);
    }
  }

  /* ---- Server checks ---------------------------------------------------- */

  function request(url, method) {
    return fetch(url, {
      method: method,
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    }).then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    });
  }

  function applyStatus(data) {
    if (!data || !data.authenticated || data.remaining <= 0) {
      goExpired();
      return;
    }
    deadline = Date.now() + data.remaining * 1000;
    if (data.remaining > WARN_SECONDS) {
      if (state === 'warning') hideModal();
      state = 'idle';
    } else if (state !== 'warning') {
      state = 'warning';
      render('warning');
      showModal();
    }
  }

  function check() {
    if (state === 'checking' || state === 'expired') return;
    var prev = state;
    state = 'checking';
    request(statusUrl, 'GET').then(function (data) {
      state = prev;
      applyStatus(data);
    }).catch(function () {
      // Network hiccup: fall back to the local timer.
      state = prev;
      if (Date.now() >= deadline) goExpired();
    });
  }

  function staySignedIn() {
    request(pingUrl, 'POST').then(function (data) {
      if (!data || !data.authenticated) { goExpired(); return; }
      deadline = Date.now() + (data.remaining || lifetime) * 1000;
      state = 'idle';
      hideModal();
    }).catch(function () {
      goExpired();
    });
  }

  function goExpired() {
    if (state === 'expired') return;
    var wasOpen = state === 'warning';
    state = 'expired';
    render('expired');
    if (!wasOpen) showModal();
  }

  /* ---- Clock ------------------------------------------------------------ */

  function tick() {
    if (state === 'expired' || state === 'checking') return;
    var left = (deadline - Date.now()) / 1000;
    if (state === 'warning') {
      if (countdownEl) countdownEl.textContent = fmt(left);
      if (left <= 0) check();
      return;
    }
    if (left <= WARN_SECONDS) check();
  }

  setInterval(tick, 1000);
  // Timers are throttled in background tabs and paused while a laptop
  // sleeps, so re-check the moment the page is looked at again.
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible' && state !== 'expired') check();
  });
})();
