/**
 * SSD-ACMIS South Sudan marking: Mid-term max 30, End-of-term max 70.
 * Shared behaviour for the mark-entry sheets: inline validation, keyboard
 * navigation between cells, a live "entered" progress pill, an unsaved-
 * changes guard, and a "fill blank cells" bulk-entry helper.
 */
(function (global) {
  'use strict';

  var MID_MAX = 30;
  var END_MAX = 70;
  var TOTAL_MAX = 100;

  function parseCell(v) {
    if (v === '' || v === null || typeof v === 'undefined') return null;
    var n = parseFloat(String(v).trim().replace(',', '.'));
    return Number.isFinite(n) ? n : NaN;
  }

  /** @returns {{ ok: boolean, msg?: string }} */
  function validateMid(value) {
    if (value === null) return { ok: true };
    if (!Number.isFinite(value)) return { ok: false, msg: 'Invalid number' };
    if (value < 0) return { ok: false, msg: 'Cannot be below 0' };
    if (value > MID_MAX + 1e-9) return { ok: false, msg: 'Max is 30' };
    return { ok: true };
  }

  /** @returns {{ ok: boolean, msg?: string }} */
  function validateEnd(value) {
    if (value === null) return { ok: true };
    if (!Number.isFinite(value)) return { ok: false, msg: 'Invalid number' };
    if (value < 0) return { ok: false, msg: 'Cannot be below 0' };
    if (value > END_MAX + 1e-9) return { ok: false, msg: 'Max is 70' };
    return { ok: true };
  }

  var DEFAULT_TIERS = [
    { label: 'A', min: 80, max: 100 },
    { label: 'B', min: 70, max: 79.99 },
    { label: 'C', min: 60, max: 69.99 },
    { label: 'D', min: 50, max: 59.99 },
    { label: 'F', min: 0, max: 49.99 }
  ];

  /** Grade letter from a /100 figure, using the school's actual configured tiers. */
  function letterFromTotal(score, tiers) {
    if (!Number.isFinite(score)) return ['—', 'secondary'];
    var t = (tiers && tiers.length ? tiers : DEFAULT_TIERS).slice()
      .sort(function (a, b) { return (b.min || 0) - (a.min || 0); });
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

  /**
   * Raw subject total, mirroring AcademicMarking::subjectTotal() server-side:
   * Mid + End when both are entered (max 100); otherwise the single entered
   * component AT FACE VALUE against its own max — out of 30 when only mid is
   * in, out of 70 when only end is in. Nothing is scaled up. Use
   * subjectMax() for the denominator and subjectPercentage() for anything
   * that needs a comparable 0–100 figure (grade lookup, row/class averages).
   */
  function subjectTotal(mid, end) {
    if (mid !== null && Number.isFinite(mid) && end !== null && Number.isFinite(end)) {
      return Math.min(TOTAL_MAX, mid + end);
    }
    if (mid !== null && Number.isFinite(mid)) return mid;
    if (end !== null && Number.isFinite(end)) return end;
    return null;
  }

  /** The denominator subjectTotal() is out of, given which components exist. */
  function subjectMax(mid, end) {
    var hasMid = mid !== null && Number.isFinite(mid);
    var hasEnd = end !== null && Number.isFinite(end);
    if (hasMid && hasEnd) return TOTAL_MAX;
    if (hasMid) return MID_MAX;
    if (hasEnd) return END_MAX;
    return null;
  }

  /** subjectTotal() as a 0–100 percentage of subjectMax() — for grading/averaging. */
  function subjectPercentage(mid, end) {
    var total = subjectTotal(mid, end);
    var max = subjectMax(mid, end);
    if (total === null || !max) return null;
    return Math.min(TOTAL_MAX, (total / max) * TOTAL_MAX);
  }

  /* ------------------------------------------------------------------ */
  /* Inline (non-blocking) validation                                    */
  /* ------------------------------------------------------------------ */

  /** Paint/clear the quiet inline error under one cell — no popups. */
  function paintCell(input, check, hasValue) {
    var cell = input.closest('td') || input.parentElement;
    var errEl = cell ? cell.querySelector('.msheet-cell-err') : null;
    var invalid = hasValue && check && !check.ok;
    input.classList.toggle('is-invalid', !!invalid);
    input.classList.toggle('is-filled', hasValue && !invalid);
    if (errEl) errEl.textContent = invalid ? check.msg : '';
  }

  /* ------------------------------------------------------------------ */
  /* Keyboard navigation: Up/Down/Enter move within a column, Left/Right  */
  /* move to the adjacent cell — spreadsheet-style entry.                */
  /* ------------------------------------------------------------------ */

  function wireKeyboardNav(inputs) {
    function rowHidden(inp) {
      var tr = inp.closest('tr');
      return !!tr && tr.style.display === 'none';
    }

    // Steps row-by-row in `dir` past any rows a search filter has hidden,
    // so Up/Down/Enter never lands on an invisible cell.
    function findByRowCol(row, col, dir) {
      var maxRow = -1;
      for (var i = 0; i < inputs.length; i++) {
        var r = parseInt(inputs[i].dataset.row || '-1', 10);
        if (r > maxRow) maxRow = r;
      }
      while (row >= 0 && row <= maxRow) {
        for (var j = 0; j < inputs.length; j++) {
          if (inputs[j].dataset.row === String(row) && inputs[j].dataset.col === col && !rowHidden(inputs[j])) {
            return inputs[j];
          }
        }
        row += dir;
      }
      return null;
    }

    // Steps left/right through the flat input list, skipping filtered-out rows.
    function nextVisible(idx, dir) {
      var i = idx + dir;
      while (i >= 0 && i < inputs.length) {
        if (!rowHidden(inputs[i])) return inputs[i];
        i += dir;
      }
      return null;
    }

    inputs.forEach(function (inp, idx) {
      inp.addEventListener('keydown', function (e) {
        var row = parseInt(inp.dataset.row || '-1', 10);
        var col = inp.dataset.col || '';
        var target = null;

        if (e.key === 'ArrowDown' || e.key === 'Enter') {
          e.preventDefault();
          target = findByRowCol(row + 1, col, 1);
          if (!target) {
            var form = inp.closest('form');
            var btn = form ? form.querySelector('[data-sheet-submit]') : null;
            if (btn) btn.focus();
          }
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          target = findByRowCol(row - 1, col, -1);
        } else if (e.key === 'ArrowRight') {
          if (inp.selectionStart === inp.value.length) { target = nextVisible(idx, 1); }
        } else if (e.key === 'ArrowLeft') {
          if (inp.selectionStart === 0) { target = nextVisible(idx, -1); }
        }
        if (target) {
          if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') e.preventDefault();
          target.focus();
          if (typeof target.select === 'function') target.select();
        }
      });
    });
  }

  /* ------------------------------------------------------------------ */
  /* Live progress pill: "X / Y entered"                                  */
  /* ------------------------------------------------------------------ */

  function wireProgress(form, inputs) {
    var pill = form.querySelector('[data-sheet-progress]');
    if (!pill) return function () {};
    var countEl = pill.querySelector('[data-progress-count]');
    var fillEl = pill.querySelector('.marks-sheet-progress__fill');
    var total = inputs.length;

    function recompute() {
      var filled = 0;
      inputs.forEach(function (inp) { if (String(inp.value || '').trim() !== '') filled++; });
      if (countEl) countEl.textContent = filled + ' / ' + total + ' entered';
      if (fillEl) fillEl.style.width = (total ? (filled / total) * 100 : 0) + '%';
      pill.classList.toggle('marks-sheet-progress--done', total > 0 && filled === total);
    }
    inputs.forEach(function (inp) { inp.addEventListener('input', recompute); });
    recompute();
    return recompute;
  }

  /* ------------------------------------------------------------------ */
  /* Autosave: persists one cell at a time, debounced, independent of the  */
  /* explicit "Save marks" submit. Drives the same status pill that used   */
  /* to be a plain "unsaved changes" flag — it now reflects Saving / Saved  */
  /* / Save failed, since edits are committed automatically as you type.   */
  /* ------------------------------------------------------------------ */

  function wireAutosave(form, inputs) {
    var flag = form.querySelector('[data-sheet-unsaved]');
    var textEl = flag ? flag.querySelector('[data-sheet-unsaved-text]') : null;
    var url = form.getAttribute('data-autosave-url');
    var pending = 0;
    var errored = false;
    var timers = [];

    function setState(state, message) {
      if (!flag) return;
      flag.classList.add('is-active');
      flag.classList.remove('marks-sheet-unsaved--saving', 'marks-sheet-unsaved--saved', 'marks-sheet-unsaved--error');
      flag.classList.add('marks-sheet-unsaved--' + state);
      if (textEl) textEl.textContent = message;
    }

    function refreshFlag() {
      if (errored) {
        setState('error', 'Save failed — fix highlighted cells or click Save');
      } else if (pending > 0) {
        setState('saving', 'Saving…');
      } else {
        setState('saved', 'All changes saved');
      }
    }

    function cellError(input, message) {
      var cell = input.closest('td') || input.parentElement;
      var errEl = cell ? cell.querySelector('.msheet-cell-err') : null;
      input.classList.add('is-invalid', 'is-autosave-error');
      if (errEl) errEl.textContent = message || 'Could not save.';
    }

    function clearCellError(input) {
      input.classList.remove('is-autosave-error');
      if (!input.classList.contains('is-invalid')) {
        var cell = input.closest('td') || input.parentElement;
        var errEl = cell ? cell.querySelector('.msheet-cell-err') : null;
        if (errEl) errEl.textContent = '';
      }
    }

    function anyStillErrored() {
      return inputs.some(function (inp) { return inp.classList.contains('is-autosave-error'); });
    }

    function save(input) {
      if (!url) return;
      var studentId = input.dataset.studentId;
      var subjectId = input.dataset.subjectId;
      var examType  = input.dataset.examType;
      if (!studentId || !subjectId || !examType) return;

      pending++;
      refreshFlag();

      var body = new URLSearchParams({
        _csrf: form._csrf ? form._csrf.value : '',
        class_id: form.class_id ? form.class_id.value : '',
        subject_id: subjectId,
        student_id: studentId,
        year: form.year ? form.year.value : '',
        term: form.term ? form.term.value : '',
        exam_type: examType,
        value: String(input.value || '').trim()
      });

      fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
      })
        .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
        .then(function (data) {
          if (data && data.ok) {
            clearCellError(input);
          } else {
            cellError(input, (data && data.error) || 'Could not save.');
          }
        })
        .catch(function () {
          cellError(input, 'Offline — will need Save marks.');
        })
        .then(function () {
          pending = Math.max(0, pending - 1);
          errored = anyStillErrored();
          refreshFlag();
        });
    }

    inputs.forEach(function (input, idx) {
      input.addEventListener('input', function () {
        clearTimeout(timers[idx]);
        timers[idx] = setTimeout(function () { save(input); }, 800);
      });
    });

    form.addEventListener('submit', function () {
      // The explicit "Save marks" click always does a full, authoritative
      // save — autosave's per-cell status no longer matters after that.
      pending = 0;
      errored = false;
    });

    window.addEventListener('beforeunload', function (e) {
      if (pending === 0 && !errored) return;
      e.preventDefault();
      e.returnValue = '';
    });

    // The "Reload/Refresh" period mini-form is a separate GET form elsewhere
    // on the page — only warn if something hasn't actually saved yet.
    document.querySelectorAll('[data-sheet-reload]').forEach(function (reloadForm) {
      reloadForm.addEventListener('submit', function (e) {
        if ((pending > 0 || errored)
            && !window.confirm('Some marks have not saved yet. Discard them and reload?')) {
          e.preventDefault();
        }
      });
    });

    if (url) refreshFlag();
  }

  /* ------------------------------------------------------------------ */
  /* Fill-blanks: apply one value to every currently-empty matching cell  */
  /* ------------------------------------------------------------------ */

  function wireFillBlanks(form, getTargetInputs, onFilled) {
    var btn = form.querySelector('[data-sheet-fill-btn]');
    var valueEl = form.querySelector('[data-sheet-fill-value]');
    if (!btn || !valueEl) return;
    btn.addEventListener('click', function () {
      var raw = String(valueEl.value || '').trim();
      if (raw === '') return;
      var targets = getTargetInputs();
      var changed = false;
      targets.forEach(function (inp) {
        // Respect an active search filter — only fill what's actually shown.
        var tr = inp.closest('tr');
        if (tr && tr.style.display === 'none') return;
        if (String(inp.value || '').trim() === '') {
          inp.value = raw;
          inp.dispatchEvent(new Event('input', { bubbles: true }));
          changed = true;
        }
      });
      if (changed && typeof onFilled === 'function') onFilled();
    });
  }

  /* ------------------------------------------------------------------ */
  /* Search: type a name or admission number to filter the roster down —  */
  /* every row already has a data-search haystack rendered server-side.   */
  /* ------------------------------------------------------------------ */

  function wireSearchFilter(form) {
    var input = form.querySelector('[data-sheet-search]');
    if (!input) return;

    var wrap = input.closest('.marks-sheet-search');
    var clearBtn = form.querySelector('[data-sheet-search-clear]');
    var rows = Array.prototype.slice.call(form.querySelectorAll('tbody tr[data-row]'));
    var countEl = form.querySelector('[data-sheet-search-count]');
    var emptyRow = null;

    function ensureEmptyRow() {
      if (emptyRow) return emptyRow;
      var table = form.querySelector('.marks-sheet');
      var tbody = table ? table.querySelector('tbody') : null;
      var headRow = table ? table.querySelector('thead tr') : null;
      if (!tbody || !headRow) return null;
      var cols = 0;
      Array.prototype.forEach.call(headRow.children, function (th) { cols += th.colSpan || 1; });
      emptyRow = document.createElement('tr');
      emptyRow.className = 'msheet-search-empty';
      emptyRow.style.display = 'none';
      var td = document.createElement('td');
      td.colSpan = cols;
      td.className = 'text-center text-muted small py-3';
      td.textContent = 'No students match that search.';
      emptyRow.appendChild(td);
      tbody.appendChild(emptyRow);
      return emptyRow;
    }

    function apply() {
      var q = input.value.trim().toLowerCase();
      var visible = 0;
      rows.forEach(function (tr) {
        var haystack = tr.getAttribute('data-search') || '';
        var match = q === '' || haystack.indexOf(q) !== -1;
        tr.style.display = match ? '' : 'none';
        if (match) visible++;
      });
      var empty = ensureEmptyRow();
      if (empty) empty.style.display = (q !== '' && visible === 0) ? '' : 'none';
      if (countEl) countEl.textContent = q === '' ? '' : (visible + ' of ' + rows.length + ' shown');
      if (wrap) wrap.classList.toggle('has-value', input.value !== '');
    }

    input.addEventListener('input', apply);
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && input.value !== '') {
        input.value = '';
        apply();
      }
    });
    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        input.value = '';
        apply();
        input.focus();
      });
    }
  }

  /* ------------------------------------------------------------------ */
  /* Sheet initializers                                                   */
  /* ------------------------------------------------------------------ */

  /** Single exam-type sheet (ordinary teacher: one column of scores). */
  function initSingleSheet(form, opts) {
    if (!form) return;
    opts = opts || {};
    var tiers = opts.tiers;
    var examType = opts.examType || 'midterm';
    var validateFn = examType === 'midterm' ? validateMid : validateEnd;
    var max = examType === 'midterm' ? MID_MAX : END_MAX;

    var inputs = Array.prototype.slice.call(form.querySelectorAll('.score-input'));
    inputs.forEach(function (inp) {
      var badge = (inp.closest('tr') || document).querySelector('.grade-badge');
      function refresh() {
        var raw = String(inp.value || '').trim();
        var v = parseCell(raw);
        var check = validateFn(v);
        paintCell(inp, check, raw !== '');
        if (badge) {
          if (raw === '' || !check.ok) {
            badge.textContent = '—';
            badge.className = 'badge grade-badge bg-secondary';
          } else {
            var t = letterFromTotal((v / max) * 100, tiers);
            badge.textContent = t[0];
            badge.className = 'badge grade-badge bg-' + t[1];
          }
        }
      }
      inp.addEventListener('input', refresh);
      refresh();
    });

    wireKeyboardNav(inputs);
    wireProgress(form, inputs);
    wireAutosave(form, inputs);
    wireFillBlanks(form, function () { return inputs; });
    wireSearchFilter(form);
    attachFormGuard(form);
  }

  /** Dual exam-type sheet (HOD single subject: Mid + End together). */
  function initDualSheet(form, opts) {
    if (!form) return;
    opts = opts || {};
    var tiers = opts.tiers;

    var mids = Array.prototype.slice.call(form.querySelectorAll('.score-mid'));
    var ends = Array.prototype.slice.call(form.querySelectorAll('.score-end'));
    var all = mids.concat(ends);

    form.querySelectorAll('tr[data-row]').forEach(function (tr) {
      var mIn = tr.querySelector('.score-mid');
      var eIn = tr.querySelector('.score-end');
      var mBd = tr.querySelector('.grade-mid');
      var eBd = tr.querySelector('.grade-end');
      var totEl = tr.querySelector('.score-total-val');
      var totBd = tr.querySelector('.grade-total');
      if (!mIn || !eIn) return;

      function refreshPart(input, badge, validateFn, max) {
        var raw = String(input.value || '').trim();
        var v = parseCell(raw);
        var check = validateFn(v);
        paintCell(input, check, raw !== '');
        if (badge) {
          if (raw === '' || !check.ok) {
            badge.textContent = '—';
            badge.className = 'badge bg-secondary';
          } else {
            var t = letterFromTotal((v / max) * 100, tiers);
            badge.textContent = t[0];
            badge.className = 'badge bg-' + t[1];
          }
        }
      }

      function refreshTotal() {
        var vm = parseCell(mIn.value);
        var ve = parseCell(eIn.value);
        var cm = validateMid(vm);
        var ce = validateEnd(ve);
        var ok = cm.ok && ce.ok;
        var total = ok ? subjectTotal(vm, ve) : null;
        var max = ok ? subjectMax(vm, ve) : null;
        var pct = ok ? subjectPercentage(vm, ve) : null;
        if (totEl && totBd) {
          if (total !== null) {
            totEl.textContent = String(Math.round(total * 100) / 100) + (max ? ' /' + max : '');
            var lg = letterFromTotal(pct, tiers);
            totBd.textContent = lg[0];
            totBd.className = 'badge bg-' + lg[1];
          } else {
            totEl.textContent = '—';
            totBd.textContent = '—';
            totBd.className = 'badge bg-secondary';
          }
        }
      }

      mIn.addEventListener('input', function () { refreshPart(mIn, mBd, validateMid, MID_MAX); refreshTotal(); });
      eIn.addEventListener('input', function () { refreshPart(eIn, eBd, validateEnd, END_MAX); refreshTotal(); });
      refreshPart(mIn, mBd, validateMid, MID_MAX);
      refreshPart(eIn, eBd, validateEnd, END_MAX);
      refreshTotal();
    });

    wireKeyboardNav(all);
    wireProgress(form, all);
    wireAutosave(form, all);
    wireFillBlanks(form, function () {
      var col = form.querySelector('[data-sheet-fill-col]');
      var which = col ? col.value : 'mid';
      return which === 'end' ? ends : mids;
    });
    wireSearchFilter(form);
    attachFormGuard(form);
  }

  /** Department matrix sheet (HOD: every subject in a category, Mid + End). */
  function initDepartmentSheet(form, opts) {
    if (!form) return;
    opts = opts || {};
    var tiers = opts.tiers;

    var mids = Array.prototype.slice.call(form.querySelectorAll('.score-mid'));
    var ends = Array.prototype.slice.call(form.querySelectorAll('.score-end'));
    var all = mids.concat(ends);

    function paintOne(input, validateFn) {
      var raw = String(input.value || '').trim();
      var v = parseCell(raw);
      paintCell(input, validateFn(v), raw !== '');
    }
    all.forEach(function (inp) {
      var validateFn = inp.classList.contains('score-mid') ? validateMid : validateEnd;
      inp.addEventListener('input', function () { paintOne(inp, validateFn); });
      paintOne(inp, validateFn);
    });

    form.querySelectorAll('tr[data-row]').forEach(function (tr) {
      var rowMids = tr.querySelectorAll('.score-mid');
      var rowEnds = tr.querySelectorAll('.score-end');
      var avgEl = tr.querySelector('.row-avg');
      if (!rowMids.length || !avgEl) return;

      function recalc() {
        // Average each subject's own PERCENTAGE, not its raw total — a
        // mid-only subject is out of 30, a complete one out of 100, so
        // summing raw totals directly would corrupt the row average the
        // moment a student has a mix of the two.
        var sum = 0, n = 0;
        for (var i = 0; i < rowMids.length; i++) {
          var m = parseCell(rowMids[i].value);
          var e = parseCell(rowEnds[i] ? rowEnds[i].value : '');
          var cm = validateMid(m);
          var ce = validateEnd(e);
          if (!cm.ok || !ce.ok) continue;
          var pct = subjectPercentage(m, e);
          if (pct === null) continue;
          sum += pct;
          n++;
        }
        if (!n) {
          avgEl.textContent = '—';
          avgEl.className = 'badge bg-secondary row-avg';
          return;
        }
        var avg = sum / n;
        avgEl.textContent = avg.toFixed(1);
        var lg = letterFromTotal(avg, tiers);
        avgEl.className = 'badge row-avg bg-' + lg[1];
      }
      [].forEach.call(rowMids, function (inp) { inp.addEventListener('input', recalc); });
      [].forEach.call(rowEnds, function (inp) { inp.addEventListener('input', recalc); });
      recalc();
    });

    wireKeyboardNav(all);
    wireProgress(form, all);
    wireAutosave(form, all);
    wireSearchFilter(form);

    // "Jump to subject" chips scroll the sheet horizontally to that subject's columns.
    form.querySelectorAll('[data-jump-subject]').forEach(function (chip) {
      chip.addEventListener('click', function () {
        var head = document.getElementById('subj-head-' + chip.dataset.jumpSubject);
        if (head && typeof head.scrollIntoView === 'function') {
          head.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
        }
      });
    });

    attachFormGuard(form);
  }

  /* ------------------------------------------------------------------ */
  /* Final pre-submit check — a summary modal is appropriate here (once   */
  /* per save attempt), unlike per-keystroke popups.                     */
  /* ------------------------------------------------------------------ */

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
      '<p class="small text-muted mb-2 mb-lg-3">Correct the highlighted cells, then save again.</p>' +
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
      modalEl.addEventListener('hidden.bs.modal', focusAfterClose, { once: true });
      Modal.show();
    } catch (err) {
      window.alert((title || 'Marks') + '\n\n' + msgs.join('\n'));
      setTimeout(focusAfterClose, 0);
    }
  }

  function attachFormGuard(form) {
    if (!form || form.dataset.guardAttached) return;
    form.dataset.guardAttached = '1';
    form.addEventListener('submit', function (e) {
      var errs = [];
      var firstBad = null;
      form.querySelectorAll('.score-mid, .score-end, .score-input').forEach(function (inp) {
        var raw = String(inp.value || '').trim();
        if (raw === '') return;
        var v = parseCell(raw);
        var validateFn = inp.classList.contains('score-end') ? validateEnd : validateMid;
        if (inp.classList.contains('score-input')) {
          var form2 = inp.closest('form');
          var ex = form2 ? form2.querySelector('[name="exam_type"]') : null;
          validateFn = (ex && ex.value === 'endterm') ? validateEnd : validateMid;
        }
        var check = validateFn(v);
        if (!check.ok) {
          errs.push((check.msg || 'Invalid value') + ' — check the highlighted cell.');
          if (!firstBad) firstBad = inp;
        }
      });
      if (errs.length) {
        e.preventDefault();
        showMarksValidationModal('Cannot save marks', errs.slice(0, 16), firstBad);
        if (firstBad && typeof firstBad.scrollIntoView === 'function') {
          firstBad.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
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
    subjectTotal: subjectTotal,
    subjectMax: subjectMax,
    subjectPercentage: subjectPercentage,
    initSingleSheet: initSingleSheet,
    initDualSheet: initDualSheet,
    initDepartmentSheet: initDepartmentSheet,
    showMarksValidationModal: showMarksValidationModal
  };
})(typeof window !== 'undefined' ? window : this);
