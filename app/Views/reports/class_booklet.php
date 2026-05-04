<?php
use App\Core\View;
use App\Core\Settings;
use App\Core\App;
$layout = 'app';
$title  = (string) ($class['name'] ?? 'Report Cards');
$schoolName  = Settings::get('school_name') ?: App::config('app.name');
$schoolMotto = Settings::get('school_motto') ?? '';
$schoolLogo  = Settings::logoUrl();
$n = count($booklet);
$qs = 'year=' . rawurlencode($year) . '&term=' . rawurlencode($term);

$peersJson = [];
foreach ($booklet as $entry) {
    $st = $entry['student'];
    $peersJson[] = [
        'id'        => (int) $st['id'],
        'admission' => (string) ($st['admission_no'] ?? ''),
        'name'      => trim(($st['first_name'] ?? '') . ' ' . ($st['last_name'] ?? '')),
    ];
}
$peersAttr = htmlspecialchars(json_encode($peersJson, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div
  class="report-booklet-layout"
  id="bookletRoot"
  data-peers="<?= $peersAttr ?>"
>
  <div class="report-toolbar report-toolbar--v2 report-toolbar--student mb-3 d-print-none">
    <div class="d-flex flex-wrap align-items-end gap-2 gap-xl-3 justify-content-between">
      <div class="d-flex flex-wrap align-items-end gap-2 gap-md-3 flex-grow-1 min-w-0">
        <a class="btn btn-outline-secondary btn-sm flex-shrink-0" href="<?= $base ?><?= $portalPrefix ?>/reports/class/<?= (int) $class['id'] ?>?<?= View::e($qs) ?>">
          <i class="bi bi-arrow-left"></i> Class matrix
        </a>
        <form method="get" class="d-flex flex-wrap gap-2 align-items-end flex-shrink-0" action="<?= $base ?><?= $portalPrefix ?>/reports/class/<?= (int) $class['id'] ?>/booklet">
          <div>
            <label class="form-label small mb-1">Year</label>
            <input name="year" class="form-control form-control-sm" value="<?= View::e($year) ?>">
          </div>
          <div>
            <label class="form-label small mb-1">Term</label>
            <select name="term" class="form-select form-select-sm">
              <?php foreach ($terms as $t): ?>
                <option <?= $t === $term ? 'selected' : '' ?>><?= View::e($t) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-outline-primary btn-sm">Reload</button>
        </form>
        <?php if ($n > 0): ?>
          <div class="report-toolbar__peers d-flex flex-wrap align-items-center gap-2 flex-grow-1 min-w-0">
            <div class="btn-group btn-group-sm flex-shrink-0" role="group" aria-label="Student report cards">
              <button type="button" class="btn btn-outline-secondary" id="bookletPrev" title="Previous (←)" disabled aria-label="Previous">
                <i class="bi bi-chevron-left" aria-hidden="true"></i><span class="d-none d-xxl-inline"> Prev</span>
              </button>
              <button type="button" class="btn btn-outline-secondary" id="bookletNext" title="Next (→)" <?= $n <= 1 ? 'disabled' : '' ?> aria-label="Next">
                <span class="d-none d-xxl-inline">Next </span><i class="bi bi-chevron-right" aria-hidden="true"></i>
              </button>
            </div>
            <span class="badge rounded-pill bg-body-secondary text-body border small fw-normal flex-shrink-0">
              <span id="bookletIndex">1</span>&nbsp;/&nbsp;<?= $n ?>
            </span>
            <div class="compact-search compact-search--peer compact-search--toolbar">
              <label class="visually-hidden" for="bookletSearch">Find student in booklet</label>
              <div class="input-group input-group-sm">
                <span class="input-group-text py-1"><i class="bi bi-search"></i></span>
                <input type="search" class="form-control py-1" id="bookletSearch"
                       placeholder="Name or No." autocomplete="off"
                       title="Class roster for this period. Arrow keys: ← → when not typing.">
                <button type="button" class="btn btn-outline-primary px-2" id="bookletGo" title="Open">
                  <i class="bi bi-arrow-right" aria-hidden="true"></i><span class="visually-hidden">Open</span>
                </button>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
      <div class="d-flex flex-wrap gap-2 flex-shrink-0 ms-auto">
        <span class="text-muted small d-none d-md-inline align-self-end pb-1 me-1"><?= View::e($class['name']) ?> · <?= $n ?> student<?= $n === 1 ? '' : 's' ?></span>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()" <?= $n === 0 ? 'disabled' : '' ?>>
          <i class="bi bi-printer"></i> Print all
        </button>
      </div>
    </div>
  </div>

  <?php if ($n === 0): ?>
    <div class="alert alert-info">No students in this class yet.</div>
  <?php else: ?>
    <!-- Screen view: one student at a time, scaled to fit (populated by JS from #bookletStore) -->
    <div class="report-student-viewport report-booklet-viewport" id="reportStudentViewport">
      <div class="report-student-viewport__slot" id="reportStudentSlot">
        <div class="report-student-viewport__sheet" id="reportStudentSheet"></div>
      </div>
    </div>

    <!-- Source of all cards: visible when no JS, used as the printable booklet always -->
    <div id="bookletStore" class="report-booklet">
      <?php
      $i = 0;
      foreach ($booklet as $entry):
        $i++;
        $student  = $entry['student'];
        $sheet    = $entry['sheet'];
        $position = $entry['position'];
      ?>
        <div class="report-booklet__page<?= $i < $n ? ' report-booklet__page--break' : '' ?>"
             data-student-id="<?= (int) $student['id'] ?>"
             data-index="<?= $i - 1 ?>">
          <?php include __DIR__ . '/_student_card.php'; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($n > 0): ?>
<script>
(function () {
  var root = document.getElementById('bookletRoot');
  var viewport = document.getElementById('reportStudentViewport');
  var slot = document.getElementById('reportStudentSlot');
  var sheet = document.getElementById('reportStudentSheet');
  var store = document.getElementById('bookletStore');
  if (!root || !viewport || !slot || !sheet || !store) return;

  var pages = Array.prototype.slice.call(store.querySelectorAll('.report-booklet__page'));
  if (pages.length === 0) return;

  /* Switch the layout into single-card scaled view (CSS hides the stacked store on screen). */
  root.classList.add('js-booklet-active');
  /* Borrow the student-report fullscreen wrapper rules so .app-shell:has(...) kicks in. */
  root.classList.add('report-student-layout');

  var prevBtn = document.getElementById('bookletPrev');
  var nextBtn = document.getElementById('bookletNext');
  var indexEl = document.getElementById('bookletIndex');
  var searchInput = document.getElementById('bookletSearch');
  var goBtn = document.getElementById('bookletGo');

  var peers = [];
  try { peers = JSON.parse(root.getAttribute('data-peers') || '[]'); } catch (e) { peers = []; }

  var current = 0;

  function pickCard(pageEl) {
    return pageEl.querySelector('.report-page') || pageEl.firstElementChild;
  }

  function render() {
    if (current < 0) current = 0;
    if (current >= pages.length) current = pages.length - 1;
    var card = pickCard(pages[current]);
    sheet.innerHTML = '';
    if (card) sheet.appendChild(card.cloneNode(true));
    if (indexEl) indexEl.textContent = String(current + 1);
    if (prevBtn) prevBtn.disabled = (current <= 0);
    if (nextBtn) nextBtn.disabled = (current >= pages.length - 1);
    fitCardRaf();
  }

  function fitCard() {
    if (window.matchMedia('print').matches) return;
    sheet.style.transform = 'none';
    slot.style.width = 'auto';
    slot.style.height = 'auto';
    var w = sheet.offsetWidth;
    var h = sheet.offsetHeight;
    if (!w || !h) return;
    var vw = viewport.clientWidth;
    var vh = viewport.clientHeight;
    if (!vw || !vh) return;
    var s = Math.min((vw - 2) / w, (vh - 2) / h);
    if (s > 1) s = 1;
    if (s <= 0) s = 0.01;
    sheet.style.transformOrigin = 'top left';
    sheet.style.transform = 'scale(' + s + ')';
    slot.style.width = Math.floor(w * s) + 'px';
    slot.style.height = Math.floor(h * s) + 'px';
  }

  function fitCardRaf() {
    window.requestAnimationFrame(function () {
      window.requestAnimationFrame(fitCard);
    });
  }

  var ro = typeof ResizeObserver !== 'undefined'
    ? new ResizeObserver(function () { fitCardRaf(); })
    : null;
  if (ro) ro.observe(viewport);
  window.addEventListener('resize', fitCardRaf);
  if (window.visualViewport) window.visualViewport.addEventListener('resize', fitCardRaf);
  window.addEventListener('afterprint', fitCardRaf);
  document.fonts && document.fonts.ready.then(fitCardRaf);

  function norm(s) { return String(s || '').toLowerCase().replace(/\s+/g, ' ').trim(); }

  function findPeers(query) {
    var q = norm(query);
    if (!q) return [];
    return peers.filter(function (p) {
      var adm = norm(p.admission), nm = norm(p.name);
      return adm.indexOf(q) !== -1 || nm.indexOf(q) !== -1 || q.split(' ').every(function (part) {
        return !part || nm.indexOf(part) !== -1;
      });
    });
  }

  function indexFromId(id) {
    for (var i = 0; i < pages.length; i++) {
      if (pages[i].getAttribute('data-student-id') === String(id)) return i;
    }
    return -1;
  }

  function goSearch() {
    if (!searchInput) return;
    var raw = searchInput.value;
    var nq = norm(raw);
    if (!nq) return;
    var target = peers.find(function (p) { return norm(p.admission) === nq; });
    if (!target) {
      var matches = findPeers(raw);
      if (matches.length === 0) {
        window.alert('No student matches that name or admission number in this class.');
        return;
      }
      if (matches.length === 1) {
        target = matches[0];
      } else {
        var lines = matches.slice(0, 8).map(function (m) {
          return m.name + ' — ' + (m.admission || '—');
        }).join('\n');
        var more = matches.length > 8 ? '\n… and ' + (matches.length - 8) + ' more — narrow your search.' : '';
        window.alert('Several matches:\n\n' + lines + more);
        return;
      }
    }
    var idx = indexFromId(target.id);
    if (idx >= 0) {
      current = idx;
      render();
      if (searchInput) searchInput.select && searchInput.select();
    }
  }

  if (prevBtn) prevBtn.addEventListener('click', function () { if (current > 0) { current--; render(); } });
  if (nextBtn) nextBtn.addEventListener('click', function () { if (current < pages.length - 1) { current++; render(); } });
  if (goBtn) goBtn.addEventListener('click', goSearch);
  if (searchInput) {
    searchInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); goSearch(); }
    });
  }
  document.addEventListener('keydown', function (e) {
    if (!e.key || e.ctrlKey || e.metaKey || e.altKey) return;
    var tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
    if (tag === 'input' || tag === 'textarea' || tag === 'select' || (e.target && e.target.isContentEditable)) return;
    if (e.key === 'ArrowLeft') { e.preventDefault(); if (current > 0) { current--; render(); } }
    else if (e.key === 'ArrowRight') { e.preventDefault(); if (current < pages.length - 1) { current++; render(); } }
  });

  render();
})();
</script>
<?php endif; ?>
