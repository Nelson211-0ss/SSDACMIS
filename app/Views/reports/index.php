<?php
use App\Core\View;
$layout = 'app';
$title = 'Reports';
$isHodPortal = ($portalPrefix ?? '') === '/hod';
?>
<div class="reports-page">
  <!-- Hero -->
  <section class="reports-page__hero dash-hero dash-hero--slim mb-3">
    <div class="reports-page__hero-row">
      <div class="dash-hero__content d-flex align-items-center gap-3">
        <span class="icon-chip icon-chip--blue d-none d-sm-inline-grid" aria-hidden="true">
          <i class="bi bi-file-earmark-text"></i>
        </span>
        <div class="flex-grow-1" style="min-width:0;">
          <h2 class="dash-hero__title mb-1">
            <?= $isHodPortal ? 'Department reports' : 'Report cards' ?>
          </h2>
          <p class="dash-hero__sub mb-0">
            <?= $isHodPortal
              ? 'Open class-wide matrices or printable student cards for the selected academic period.'
              : 'Browse class summaries and individual student report cards.' ?>
          </p>
        </div>
      </div>
      <div class="reports-page__period-chip" title="Reports use this period">
        <i class="bi bi-calendar3"></i>
        <span><strong><?= View::e($year) ?></strong> · <?= View::e($term) ?></span>
      </div>
    </div>
  </section>

  <!-- Period filter -->
  <form method="get" action="<?= $base ?><?= $portalPrefix ?>/reports" class="reports-page__filters card border-0 shadow-sm mb-4">
    <div class="card-body">
      <div class="reports-page__filters-head mb-3">
        <span class="reports-page__filters-icon" aria-hidden="true"><i class="bi bi-sliders"></i></span>
        <div>
          <div class="reports-page__filters-title">Reporting period</div>
          <p class="reports-page__filters-hint mb-0 text-muted small">
            Year and term apply to every class and student link below.
          </p>
        </div>
      </div>
      <div class="row g-3 align-items-end">
        <div class="col-md-4">
          <label class="form-label">Academic year</label>
          <input name="year" class="form-control shadow-sm" value="<?= View::e($year) ?>"
                 placeholder="e.g. <?= View::e(date('n') >= 9 ? date('Y') . '/' . (date('Y') + 1) : (date('Y') - 1) . '/' . date('Y')) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Term</label>
          <select name="term" class="form-select shadow-sm">
            <?php foreach ($terms as $t): ?>
              <option <?= $t === $term ? 'selected' : '' ?>><?= View::e($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <button type="submit" class="btn btn-primary w-100 shadow-sm">
            <i class="bi bi-check2-circle me-1"></i> Apply period
          </button>
        </div>
      </div>
    </div>
  </form>

<?php if ($role === 'student'): ?>
  <div class="card border-0 shadow-sm reports-page__solo">
    <div class="card-body text-center py-5 px-3">
      <?php if (!empty($studentId)): ?>
        <div class="reports-page__solo-icon mb-3"><i class="bi bi-person-badge"></i></div>
        <p class="text-muted mb-3">Your report card for <strong><?= View::e($year) ?></strong> · <?= View::e($term) ?></p>
        <a class="btn btn-primary btn-lg px-4 shadow-sm"
           href="<?= $base ?><?= $portalPrefix ?>/reports/student/<?= (int) $studentId ?>?year=<?= rawurlencode($year) ?>&term=<?= rawurlencode($term) ?>">
          <i class="bi bi-file-earmark-arrow-down"></i> View my report card
        </a>
      <?php else: ?>
        <div class="empty-state py-2">
          <i class="bi bi-person-x d-block mb-2"></i>
          <p class="text-muted mb-0">Your account isn’t linked to a student profile yet.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

<?php elseif (empty($classes)): ?>
  <div class="card border-0 shadow-sm reports-page__empty">
    <div class="card-body d-flex gap-3 align-items-start py-4">
      <span class="reports-page__empty-icon" aria-hidden="true"><i class="bi bi-shield-lock"></i></span>
      <div>
        <h3 class="h6 mb-2">No reports available</h3>
        <p class="text-muted small mb-0">
          You don’t have access to any classes’ reports yet.
          <?php if ($role === 'staff'): ?>
            Ask an admin to assign you a teaching class or make you the class teacher.
          <?php endif; ?>
        </p>
      </div>
    </div>
  </div>

<?php else:
  $classNameById = [];
  foreach ($classes as $c) {
      $classNameById[(int) $c['id']] = (string) $c['name'];
  }
  $studentsByClass = [];
  foreach ($students as $s) {
      $cid = (int) ($s['class_id'] ?? 0);
      $studentsByClass[$cid][] = $s;
  }
  ksort($studentsByClass);
  $reportQs = 'year=' . rawurlencode($year) . '&term=' . rawurlencode($term);
?>

  <div class="row g-4 reports-page__grid">
    <!-- Whole-class -->
    <div class="col-lg-5">
      <div class="reports-panel card border-0 shadow-sm h-100">
        <div class="reports-panel__head reports-panel__head--theme">
          <span class="reports-panel__glyph" aria-hidden="true"><i class="bi bi-people-fill"></i></span>
          <div class="reports-panel__head-text">
            <h3 class="reports-panel__title">Whole-class reports</h3>
            <p class="reports-panel__sub mb-0">Subject matrix · one page per class</p>
          </div>
        </div>
        <div class="reports-class-list">
          <?php foreach ($classes as $c):
            $cid = (int) $c['id'];
            $classReportUrl = $base . $portalPrefix . '/reports/class/' . $cid . '?' . $reportQs;
            $bookletUrl = $base . $portalPrefix . '/reports/class/' . $cid . '/booklet?' . $reportQs;
            $stuN = (int) ($c['student_count'] ?? 0);
          ?>
            <article class="reports-class-item">
              <div class="reports-class-item__main">
                <span class="reports-class-item__avatar" aria-hidden="true"><i class="bi bi-building"></i></span>
                <div class="reports-class-item__body">
                  <a class="reports-class-item__title" href="<?= View::e($classReportUrl) ?>">
                    <?= View::e($c['name']) ?>
                  </a>
                  <div class="reports-class-item__meta">
                    <span class="badge rounded-pill text-bg-light border">
                      <?= $stuN ?> student<?= $stuN === 1 ? '' : 's' ?>
                    </span>
                    <?php if (!empty($c['level'])): ?>
                      <span class="text-muted small"><?= View::e((string) $c['level']) ?></span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <div class="reports-class-item__actions">
                <a class="btn btn-sm btn-primary" href="<?= View::e($classReportUrl) ?>">
                  <i class="bi bi-grid-3x3-gap"></i> Open
                </a>
                <a class="btn btn-sm btn-outline-secondary"
                   href="<?= View::e($bookletUrl) ?>"
                   title="One vertical page per student">
                  <i class="bi bi-file-person"></i> Booklet
                </a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Individual -->
    <div class="col-lg-7">
      <div class="reports-panel card border-0 shadow-sm h-100">
        <div class="reports-panel__head reports-panel__head--accent">
          <span class="reports-panel__glyph" aria-hidden="true"><i class="bi bi-person-vcard"></i></span>
          <div class="reports-panel__head-text">
            <h3 class="reports-panel__title">Individual student</h3>
            <p class="reports-panel__sub mb-0">
              <span id="studentCount"><?= count($students) ?></span> student<?= count($students) === 1 ? '' : 's' ?> in scope
            </p>
          </div>
        </div>
        <div class="card-body pt-3">
          <form method="get" id="studentReportForm" onsubmit="return openStudentReport(event)">
            <div class="reports-student-search mb-3">
              <div class="reports-student-search__row d-flex flex-column flex-sm-row flex-wrap align-items-sm-center justify-content-sm-between gap-2 mb-2">
                <label class="form-label fw-semibold mb-0 flex-shrink-0" for="studentSearch">Find a student</label>
                <div class="compact-search compact-search--reports ms-sm-auto">
                  <div class="input-group input-group-sm rounded-2 overflow-hidden">
                    <span class="input-group-text py-1"><i class="bi bi-search"></i></span>
                    <input type="search" id="studentSearch" class="form-control py-1"
                           placeholder="Name or No."
                           autocomplete="off"
                           aria-controls="studentSelect"
                           title="Filter the list below">
                    <button type="button" class="btn btn-outline-secondary px-2" id="studentSearchClear" title="Clear">
                      <i class="bi bi-x-lg" aria-hidden="true"></i><span class="visually-hidden">Clear</span>
                    </button>
                  </div>
                </div>
              </div>
              <div class="form-text small mb-0" id="studentSearchHint">
                Showing all <?= count($students) ?> students.
              </div>
            </div>
            <div class="row g-3 align-items-end">
              <div class="col-md-9">
                <label class="form-label fw-semibold" for="studentSelect">Select student</label>
                <select id="studentSelect" class="form-select reports-student-select shadow-sm" required size="10">
                  <option value="">— Choose a student —</option>
                  <?php foreach ($studentsByClass as $cid => $list):
                    $label = $classNameById[$cid] ?? 'Unassigned';
                  ?>
                    <optgroup label="<?= View::e($label) ?>" data-class-name="<?= View::e(strtolower($label)) ?>">
                      <?php foreach ($list as $s):
                        $adm   = (string) ($s['admission_no'] ?? '');
                        $first = (string) ($s['first_name']   ?? '');
                        $last  = (string) ($s['last_name']    ?? '');
                        $hay   = strtolower(trim($adm . ' ' . $first . ' ' . $last . ' ' . $label));
                      ?>
                        <option value="<?= (int) $s['id'] ?>" data-search="<?= View::e($hay) ?>">
                          <?= View::e($adm . ' — ' . $first . ' ' . $last) ?>
                        </option>
                      <?php endforeach; ?>
                    </optgroup>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100 shadow-sm py-2">
                  <i class="bi bi-file-earmark-text"></i> View report
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <script>
    function openStudentReport(e) {
      e.preventDefault();
      var id = document.getElementById('studentSelect').value;
      if (!id) return false;
      var u = <?= json_encode(rtrim($base, '/') . $portalPrefix . '/reports/student/', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?> + id
            + '?year=' + encodeURIComponent(<?= json_encode((string) $year, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)
            + '&term=' + encodeURIComponent(<?= json_encode((string) $term, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
      window.location.href = u;
      return false;
    }

    (function () {
      var input  = document.getElementById('studentSearch');
      var clear  = document.getElementById('studentSearchClear');
      var select = document.getElementById('studentSelect');
      var hint   = document.getElementById('studentSearchHint');
      if (!input || !select) return;

      var groups  = Array.prototype.slice.call(select.querySelectorAll('optgroup'));
      var options = Array.prototype.slice.call(select.querySelectorAll('optgroup option'));
      var total   = options.length;

      function tokenize(q) {
        return q.toLowerCase().split(/\s+/).filter(Boolean);
      }
      function matches(opt, tokens) {
        if (!tokens.length) return true;
        var hay = opt.dataset.search || opt.textContent.toLowerCase();
        for (var i = 0; i < tokens.length; i++) {
          if (hay.indexOf(tokens[i]) === -1) return false;
        }
        return true;
      }

      function apply() {
        var tokens = tokenize(input.value);
        var visible = 0;

        options.forEach(function (opt) {
          var ok = matches(opt, tokens);
          opt.hidden   = !ok;
          opt.disabled = !ok;
          if (ok) visible++;
        });
        groups.forEach(function (g) {
          var anyVisible = Array.prototype.some.call(
            g.querySelectorAll('option'),
            function (o) { return !o.hidden; }
          );
          g.hidden = !anyVisible;
        });

        if (hint) {
          hint.textContent = tokens.length === 0
            ? 'Showing all ' + total + ' students.'
            : 'Showing ' + visible + ' of ' + total + ' students';
        }

        var sel = select.options[select.selectedIndex];
        if (sel && sel.hidden) select.value = '';
      }

      input.addEventListener('input', apply);
      input.addEventListener('search', apply);
      if (clear) clear.addEventListener('click', function () {
        input.value = '';
        apply();
        input.focus();
      });

      input.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        var first = options.find(function (o) { return !o.hidden; });
        if (!first) return;
        select.value = first.value;
        openStudentReport({ preventDefault: function () {} });
      });
    })();
  </script>
<?php endif; ?>
</div>
