(function () {
  'use strict';

  // Opens the "Add …" forms (students, staff, HODs, bursars) inside a Bootstrap
  // modal instead of navigating to a full page. Triggers are any element with
  // [data-entity-modal]; the form HTML is fetched from the element's href (or
  // data-url). Submitting the form does a normal full-page POST, so all the
  // existing server-side validation, flash messages, and redirects keep working
  // — on success the page reloads and shows the new record.

  var modalEl = document.getElementById('entityFormModal');
  if (!modalEl) return;

  var dialog = modalEl.querySelector('.modal-dialog');
  var titleEl = modalEl.querySelector('[data-entity-modal-title]');
  var bodyEl = modalEl.querySelector('[data-entity-modal-body]');
  if (!dialog || !bodyEl) return;

  var SPINNER =
    '<div class="text-center text-muted py-5">' +
    '<div class="spinner-border" role="status"><span class="visually-hidden">Loading…</span></div>' +
    '<div class="small mt-2">Loading form…</div></div>';

  function getModal() {
    return window.bootstrap && window.bootstrap.Modal
      ? window.bootstrap.Modal.getOrCreateInstance(modalEl)
      : null;
  }

  // innerHTML does not execute <script> tags, so clone each one into a fresh
  // element the browser will run. This is what wires up the per-form behaviour
  // (admission preview, photo capture, subject toggles, client validation…).
  function runScripts(container) {
    var scripts = container.querySelectorAll('script');
    scripts.forEach(function (old) {
      var s = document.createElement('script');
      for (var i = 0; i < old.attributes.length; i++) {
        var a = old.attributes[i];
        s.setAttribute(a.name, a.value);
      }
      s.textContent = old.textContent;
      old.parentNode.replaceChild(s, old);
    });
  }

  function setSize(size) {
    dialog.classList.remove('modal-sm', 'modal-lg', 'modal-xl');
    Array.from(dialog.classList).forEach(function (cls) {
      if (cls.indexOf('entity-modal--') === 0) dialog.classList.remove(cls);
    });
    if (size) dialog.classList.add(size);
  }

  function loadForm(url, title, size) {
    titleEl.textContent = title || 'Add';
    setSize(size || 'modal-lg');
    bodyEl.innerHTML = SPINNER;

    var modal = getModal();
    if (modal) modal.show();

    fetch(url, {
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
      credentials: 'same-origin'
    })
      .then(function (res) {
        if (!res.ok) throw new Error('Request failed (' + res.status + ')');
        return res.text();
      })
      .then(function (html) {
        bodyEl.innerHTML = html;
        runScripts(bodyEl);
      })
      .catch(function () {
        bodyEl.innerHTML =
          '<div class="alert alert-danger mb-0">Could not load the form. ' +
          'Please <a href="' + url + '">open it on its own page</a> instead.</div>';
      });
  }

  document.addEventListener('click', function (e) {
    var trigger = e.target.closest('[data-entity-modal]');
    if (!trigger) return;
    // Let modified clicks (new tab, etc.) behave normally.
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) return;
    e.preventDefault();

    var url = trigger.getAttribute('data-url') || trigger.getAttribute('href');
    if (!url) return;
    loadForm(url, trigger.getAttribute('data-modal-title'), trigger.getAttribute('data-modal-size'));
  });

  // Free the form markup once the modal is dismissed so a stale form (and any
  // live webcam stream from the student photo capture) never lingers.
  modalEl.addEventListener('hidden.bs.modal', function () {
    bodyEl.innerHTML = '';
  });
})();
