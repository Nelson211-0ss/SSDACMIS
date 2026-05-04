/**
 * SSD-ACMIS South Sudan marking: Mid-term max 30, End-of-term max 70.
 */
(function (global) {
  'use strict';

  var MID_MAX = 30;
  var END_MAX = 70;

  function parseCell(v) {
    if (v === '' || v === null || typeof v === 'undefined') return null;
    var n = parseFloat(String(v).replace(',', '.'));
    return Number.isFinite(n) ? n : NaN;
  }

  /**
   * @returns {{ ok: boolean, msg?: string }}
   */
  function validateMid(value) {
    if (value === null) return { ok: true };
    if (!Number.isFinite(value)) return { ok: false, msg: 'Invalid number' };
    if (value < 0) return { ok: false, msg: 'Mid-term marks cannot be below 0' };
    if (value > MID_MAX + 1e-9) return { ok: false, msg: 'Mid-term marks cannot exceed 30' };
    return { ok: true };
  }

  /**
   * @returns {{ ok: boolean, msg?: string }}
   */
  function validateEnd(value) {
    if (value === null) return { ok: true };
    if (!Number.isFinite(value)) return { ok: false, msg: 'Invalid number' };
    if (value < 0) return { ok: false, msg: 'End-of-term marks cannot be below 0' };
    if (value > END_MAX + 1e-9) return { ok: false, msg: 'End-of-term marks cannot exceed 70' };
    return { ok: true };
  }

  /** Grade letter from subject total (0–100). Mirrors server default tiers if tiers omitted. */
  function letterFromTotal(score, tiers) {
    if (!Number.isFinite(score)) return ['—', 'secondary'];
    var t = tiers && tiers.length ? tiers : [
      { label: 'A', min: 80, max: 100 },
      { label: 'B', min: 70, max: 79.99 },
      { label: 'C', min: 60, max: 69.99 },
      { label: 'D', min: 50, max: 59.99 },
      { label: 'F', min: 0, max: 49.99 }
    ];
    t = t.slice().sort(function (a, b) { return (b.min || 0) - (a.min || 0); });
    for (var i = 0; i < t.length; i++) {
      var row = t[i];
      if (score >= row.min && score <= row.max) {
        var lbl = row.label || '?';
        var tone = lbl.charAt(0).toUpperCase();
        var badge = tone === 'A' || tone === 'B' ? 'success'
          : tone === 'F' ? 'danger'
          : 'warning';
        return [lbl, badge];
      }
    }
    return ['—', 'secondary'];
  }

  function wireDualExamRows(root) {
    root = root || document;
    root.querySelectorAll('tr').forEach(function (tr) {
      var mIn = tr.querySelector('.score-mid');
      var eIn = tr.querySelector('.score-end');
      var mBd = tr.querySelector('.grade-mid');
      var eBd = tr.querySelector('.grade-end');
      var totEl = tr.querySelector('.score-total-val');
      var totBd = tr.querySelector('.grade-total');
      if (!mIn || !eIn || !mBd || !eBd) return;

      function bindPart(input, badge, validateFn, scaledLetter) {
        function run() {
          var pv = parseCell(input.value);
          var v = pv === null ? null : pv;
          var chk = validateFn(v);
          input.classList.toggle('is-invalid', !chk.ok && input.value !== '');
          if (!chk.ok && input.value !== '') {
            badge.textContent = '!';
            badge.className = 'badge bg-danger';
            return;
          }
          var t = scaledLetter(v);
          badge.textContent = v !== null && chk.ok ? t[0] : '—';
          badge.className = 'badge bg-' + (v !== null && chk.ok ? t[1] : 'secondary');
        }
        input.addEventListener('input', run);
        run();
      }

      bindPart(mIn, mBd, validateMid, function (v) {
        return letterFromTotal(v !== null ? (v / MID_MAX) * 100 : NaN);
      });
      bindPart(eIn, eBd, validateEnd, function (v) {
        return letterFromTotal(v !== null ? (v / END_MAX) * 100 : NaN);
      });

      function refreshTotal() {
        var pm = parseCell(mIn.value);
        var pe = parseCell(eIn.value);
        var vm = pm === null ? null : pm;
        var ve = pe === null ? null : pe;
        var cm = validateMid(vm);
        var ce = validateEnd(ve);
        if (totEl && totBd) {
          if (vm !== null && ve !== null && cm.ok && ce.ok) {
            var sum = Math.min(100, vm + ve);
            totEl.textContent = String(Math.round(sum * 100) / 100);
            var lg = letterFromTotal(sum);
            totBd.textContent = lg[0];
            totBd.className = 'badge bg-' + lg[1];
          } else {
            totEl.textContent = '—';
            totBd.textContent = '—';
            totBd.className = 'badge bg-secondary';
          }
        }
      }
      mIn.addEventListener('input', refreshTotal);
      eIn.addEventListener('input', refreshTotal);
      refreshTotal();
    });
    attachFieldBlurValidation(root);
  }

  function wireSingleExam(root, examType) {
    root = root || document;
    root.querySelectorAll('tr').forEach(function (tr) {
      var input = tr.querySelector('.score-input');
      var badge = tr.querySelector('.grade-badge');
      if (!input || !badge) return;
      function refresh() {
        var pv = parseCell(input.value);
        var chk = examType === 'midterm' ? validateMid(pv === null ? null : pv) : validateEnd(pv === null ? null : pv);
        input.classList.toggle('is-invalid', !chk.ok && input.value !== '');
        if (!chk.ok && input.value !== '') {
          badge.textContent = '!';
          badge.className = 'badge grade-badge bg-danger';
          return;
        }
        var v = pv === null ? NaN : pv;
        var scaled = examType === 'midterm' ? (v / MID_MAX) * 100 : (v / END_MAX) * 100;
        var t = letterFromTotal(scaled);
        badge.textContent = input.value !== '' && chk.ok ? t[0] : '—';
        badge.className = 'badge grade-badge bg-' + (input.value !== '' && chk.ok ? t[1] : 'secondary');
      }
      input.addEventListener('input', refresh);
      refresh();
    });
    attachFieldBlurValidation(root);
  }

  /**
   * Same Bootstrap modal pattern as student enrollment validation (rounded card, icon, bullet list).
   * Modal node is created once and appended to document.body.
   */
  function ensureMarksValidationModal() {
    var existing = document.getElementById('marksValidationModal');
    if (existing) return existing;

    var wrap = document.createElement('div');
    wrap.id = 'marksValidationModal';
    wrap.className = 'modal fade';
    wrap.tabIndex = -1;
    wrap.setAttribute('aria-hidden', 'true');
    wrap.setAttribute('aria-labelledby', 'marksValidationModalTitle');

    wrap.innerHTML =
      '<div class="modal-dialog modal-dialog-centered">' +
      '<div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">' +
      '<div class="modal-body p-4">' +
      '<div class="d-flex gap-3 align-items-start">' +
      '<div class="flex-shrink-0 rounded-circle bg-danger-subtle text-danger d-flex align-items-center justify-content-center shadow-sm" style="width:3rem;height:3rem">' +
      '<i class="bi bi-exclamation-circle fs-4" aria-hidden="true"></i>' +
      '</div>' +
      '<div class="flex-grow-1 min-w-0 pt-0">' +
      '<h3 class="h6 fw-bold mb-2" id="marksValidationModalTitle" data-marks-modal-title></h3>' +
      '<p class="small text-muted mb-2 mb-lg-3">Correct the scores below, then continue.</p>' +
      '<ul class="small mb-0 ps-3 text-body" data-marks-modal-list></ul>' +
      '</div>' +
      '</div>' +
      '</div>' +
      '<div class="modal-footer border-0 bg-body-secondary bg-opacity-25 px-4 py-3">' +
      '<button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">OK</button>' +
      '</div>' +
      '</div>' +
      '</div>';

    document.body.appendChild(wrap);
    return wrap;
  }

  function showMarksValidationModal(title, messages, focusEl) {
    var msgs = messages && messages.length ? messages : ['Something went wrong.'];
    var modalEl = ensureMarksValidationModal();
    var titleEl = modalEl.querySelector('[data-marks-modal-title]');
    var listEl = modalEl.querySelector('[data-marks-modal-list]');
    if (titleEl) titleEl.textContent = title || 'Marks validation';
    if (listEl) {
      listEl.innerHTML = '';
      msgs.forEach(function (msg) {
        var li = document.createElement('li');
        li.className = 'mb-1';
        li.textContent = msg;
        listEl.appendChild(li);
      });
    }

    function focusAfterClose() {
      if (!focusEl || typeof focusEl.focus !== 'function') return;
      try {
        focusEl.focus();
        if (typeof focusEl.select === 'function') focusEl.select();
      } catch (err) { /* ignore */ }
    }

    try {
      var Modal = bootstrap.Modal.getOrCreateInstance(modalEl);
      modalEl.addEventListener(
        'hidden.bs.modal',
        function () {
          focusAfterClose();
        },
        { once: true }
      );
      Modal.show();
    } catch (err) {
      window.alert((title || 'Marks') + '\n\n' + msgs.join('\n'));
      setTimeout(focusAfterClose, 0);
    }
  }

  /** Blocking popup + refocus when user leaves a field with an invalid value (Tab/click away). */
  function attachFieldBlurValidation(root) {
    root = root || document;

    function alertInvalid(title, detail, input) {
      showMarksValidationModal(title || 'Invalid mark', [detail], input);
    }

    root.querySelectorAll('.score-mid').forEach(function (inp) {
      inp.addEventListener('blur', function () {
        var raw = String(inp.value || '').trim();
        if (raw === '') return;
        var pv = parseCell(raw);
        if (pv === null || !Number.isFinite(pv)) {
          alertInvalid('Invalid mark', 'Please enter a valid number.', inp);
          return;
        }
        var chk = validateMid(pv);
        if (!chk.ok) {
          alertInvalid('Invalid mark', chk.msg || 'Mid-term mark is not valid.', inp);
        }
      });
    });

    root.querySelectorAll('.score-end').forEach(function (inp) {
      inp.addEventListener('blur', function () {
        var raw = String(inp.value || '').trim();
        if (raw === '') return;
        var pv = parseCell(raw);
        if (pv === null || !Number.isFinite(pv)) {
          alertInvalid('Invalid mark', 'Please enter a valid number.', inp);
          return;
        }
        var chk = validateEnd(pv);
        if (!chk.ok) {
          alertInvalid('Invalid mark', chk.msg || 'End-of-term mark is not valid.', inp);
        }
      });
    });

    root.querySelectorAll('.score-input').forEach(function (inp) {
      inp.addEventListener('blur', function () {
        var raw = String(inp.value || '').trim();
        if (raw === '') return;
        var pv = parseCell(raw);
        if (pv === null || !Number.isFinite(pv)) {
          alertInvalid('Invalid mark', 'Please enter a valid number.', inp);
          return;
        }
        var form = inp.closest('form');
        var ex = form ? form.querySelector('[name="exam_type"]') : null;
        var et = ex ? String(ex.value || 'midterm') : 'midterm';
        var chk = et === 'midterm' ? validateMid(pv) : validateEnd(pv);
        if (!chk.ok) {
          alertInvalid('Invalid mark', chk.msg || 'Score is not valid.', inp);
        }
      });
    });
  }

  function attachFormGuard(formId, dualExam) {
    var form = document.getElementById(formId);
    if (!form) return;
    form.addEventListener('submit', function (e) {
      var errs = [];
      form.querySelectorAll('.score-mid').forEach(function (inp) {
        var pv = parseCell(inp.value);
        if (pv === null && inp.value !== '') errs.push('Mid-term: invalid number');
        if (pv !== null) {
          var c = validateMid(pv);
          if (!c.ok) errs.push(c.msg || 'Mid-term invalid');
        }
      });
      form.querySelectorAll('.score-end').forEach(function (inp) {
        var pv = parseCell(inp.value);
        if (pv === null && inp.value !== '') errs.push('End-of-term: invalid number');
        if (pv !== null) {
          var c = validateEnd(pv);
          if (!c.ok) errs.push(c.msg || 'End-of-term invalid');
        }
      });
      if (!dualExam) {
        form.querySelectorAll('.score-input').forEach(function (inp) {
          var pv = parseCell(inp.value);
          if (pv === null && inp.value !== '') errs.push('Score: invalid number');
          if (pv !== null) {
            var ex = form.querySelector('[name="exam_type"]');
            var et = ex ? ex.value : 'midterm';
            var c = et === 'midterm' ? validateMid(pv) : validateEnd(pv);
            if (!c.ok) errs.push(c.msg || 'Score invalid');
          }
        });
      }
      if (errs.length) {
        e.preventDefault();
        showMarksValidationModal('Cannot save marks', errs.slice(0, 16), null);
      }
    });
  }

  global.SSDACMIS = global.SSDACMIS || {};
  global.SSDACMIS.academicMarks = {
    MID_MAX: MID_MAX,
    END_MAX: END_MAX,
    validateMid: validateMid,
    validateEnd: validateEnd,
    letterFromTotal: letterFromTotal,
    wireDualExamRows: wireDualExamRows,
    wireSingleExam: wireSingleExam,
    attachFormGuard: attachFormGuard,
    attachFieldBlurValidation: attachFieldBlurValidation,
    showMarksValidationModal: showMarksValidationModal
  };
})(typeof window !== 'undefined' ? window : this);
