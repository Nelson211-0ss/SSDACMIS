(function () {
  'use strict';

  var input = document.getElementById('student-search-q');
  var tbody = document.getElementById('students-table-body');
  var form = document.getElementById('students-search-form');
  var busy = document.getElementById('student-search-busy');
  if (!input || !tbody || !form) return;

  var rowsUrl = form.getAttribute('data-rows-url');
  if (!rowsUrl) return;

  var debounceMs = 240;
  var timer = null;
  var abortCtl = null;

  function bindConfirmForms(scope) {
    scope.querySelectorAll('form[data-confirm]').forEach(function (f) {
      f.addEventListener('submit', function (e) {
        if (!confirm(f.getAttribute('data-confirm'))) e.preventDefault();
      });
    });
  }

  function setBusy(on) {
    if (!busy) return;
    busy.classList.toggle('d-none', !on);
    input.setAttribute('aria-busy', on ? 'true' : 'false');
  }

  function syncUrl(q) {
    try {
      var u = new URL(window.location.href);
      if (q) u.searchParams.set('q', q);
      else u.searchParams.delete('q');
      window.history.replaceState({}, '', u.pathname + u.search + u.hash);
    } catch (_) {}
  }

  function fetchRows() {
    var q = input.value.trim();
    var params = new URLSearchParams();
    if (q) params.set('q', q);

    if (abortCtl) abortCtl.abort();
    abortCtl = new AbortController();

    setBusy(true);
    var url = rowsUrl + (params.toString() ? '?' + params.toString() : '');

    fetch(url, {
      signal: abortCtl.signal,
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
      credentials: 'same-origin'
    })
      .then(function (res) {
        if (!res.ok) throw new Error('Request failed');
        return res.text();
      })
      .then(function (html) {
        tbody.innerHTML = html;
        bindConfirmForms(tbody);
        syncUrl(q);
      })
      .catch(function (err) {
        if (err && err.name === 'AbortError') return;
      })
      .finally(function () {
        setBusy(false);
      });
  }

  function scheduleFetch() {
    clearTimeout(timer);
    timer = setTimeout(fetchRows, debounceMs);
  }

  input.addEventListener('input', scheduleFetch);

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    clearTimeout(timer);
    fetchRows();
  });
})();
