<?php
use App\Core\View;
use App\Core\Settings;
use App\Core\App;
$layout = 'app';
$title  = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')) ?: 'Report Card';
$schoolName  = Settings::get('school_name') ?: App::config('app.name');
$schoolMotto = Settings::get('school_motto') ?? '';
$schoolLogo  = Settings::logoUrl();

$qs = 'year=' . rawurlencode($year) . '&term=' . rawurlencode($term);
$studentReportHref = function (int $sid) use ($base, $portalPrefix, $qs): string {
    return $base . $portalPrefix . '/reports/student/' . $sid . '?' . $qs;
};
$peersAttr = htmlspecialchars(json_encode($reportPeersJson ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div
  class="report-student-layout"
  id="reportStudentRoot"
  data-peers="<?= $peersAttr ?>"
  data-prev-url="<?= $prevStudentId ? View::e($studentReportHref($prevStudentId)) : '' ?>"
  data-next-url="<?= $nextStudentId ? View::e($studentReportHref($nextStudentId)) : '' ?>"
>
  <div class="report-toolbar report-toolbar--v2 report-toolbar--student mb-3 d-print-none">
    <div class="d-flex flex-wrap align-items-end gap-2 gap-xl-3 justify-content-between">
      <div class="d-flex flex-wrap align-items-end gap-2 gap-md-3 flex-grow-1 min-w-0">
        <a class="btn btn-outline-secondary btn-sm flex-shrink-0" href="<?= $base ?><?= $portalPrefix ?>/reports?<?= View::e($qs) ?>">
          <i class="bi bi-arrow-left"></i> Back
        </a>
        <form method="get" class="d-flex flex-wrap gap-2 align-items-end flex-shrink-0" action="<?= $base ?><?= $portalPrefix ?>/reports/student/<?= (int) $student['id'] ?>">
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
        <?php if (!empty($reportPeersJson)): ?>
          <div class="report-toolbar__peers d-flex flex-wrap align-items-center gap-2 flex-grow-1 min-w-0">
            <div class="btn-group btn-group-sm flex-shrink-0" role="group" aria-label="Student report cards">
              <?php if ($prevStudentId): ?>
                <a class="btn btn-outline-secondary" href="<?= View::e($studentReportHref($prevStudentId)) ?>" title="Previous (←)" id="reportPeerPrev">
                  <i class="bi bi-chevron-left"></i><span class="d-none d-xxl-inline"> Prev</span>
                </a>
              <?php else: ?>
                <button type="button" class="btn btn-outline-secondary" disabled title="No previous" aria-label="No previous student"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
              <?php endif; ?>
              <?php if ($nextStudentId): ?>
                <a class="btn btn-outline-secondary" href="<?= View::e($studentReportHref($nextStudentId)) ?>" title="Next (→)" id="reportPeerNext">
                  <span class="d-none d-xxl-inline">Next </span><i class="bi bi-chevron-right"></i>
                </a>
              <?php else: ?>
                <button type="button" class="btn btn-outline-secondary" disabled title="No next" aria-label="No next student"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
              <?php endif; ?>
            </div>
            <?php if (($peerTotal ?? 0) > 0 && ($peerPosition ?? null) !== null): ?>
              <span class="badge rounded-pill bg-body-secondary text-body border small fw-normal flex-shrink-0"><span id="reportPeerIndex"><?= (int) $peerPosition ?></span>&nbsp;/&nbsp;<?= (int) $peerTotal ?></span>
            <?php endif; ?>
            <div class="compact-search compact-search--peer compact-search--toolbar">
              <label class="visually-hidden" for="reportPeerSearch">Find student by name or admission number</label>
              <div class="input-group input-group-sm">
                <span class="input-group-text py-1"><i class="bi bi-search"></i></span>
                <input type="search"
                       class="form-control py-1"
                       id="reportPeerSearch"
                       placeholder="Name or No."
                       title="Class roster for this period. Arrow keys: ← → when not typing."
                       autocomplete="off">
                <button type="button" class="btn btn-outline-primary px-2" id="reportPeerGo" title="Open">
                  <i class="bi bi-arrow-right" aria-hidden="true"></i><span class="visually-hidden">Open</span>
                </button>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
      <div class="d-flex flex-wrap gap-2 flex-shrink-0 ms-auto">
        <?php if (!empty($student['class_id'])): ?>
          <a class="btn btn-outline-primary btn-sm"
             href="<?= $base ?><?= $portalPrefix ?>/reports/class/<?= (int) $student['class_id'] ?>/booklet?<?= View::e($qs) ?>">
            <i class="bi bi-printer"></i> Print whole class
          </a>
        <?php endif; ?>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
          <i class="bi bi-printer"></i> Print this student
        </button>
      </div>
    </div>
  </div>

  <div class="report-student-viewport" id="reportStudentViewport">
    <div class="report-student-viewport__slot" id="reportStudentSlot">
      <div class="report-student-viewport__sheet" id="reportStudentSheet">
        <?php include __DIR__ . '/_student_card.php'; ?>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var root = document.getElementById('reportStudentRoot');
  var viewport = document.getElementById('reportStudentViewport');
  var slot = document.getElementById('reportStudentSlot');
  var sheet = document.getElementById('reportStudentSheet');
  if (!root || !viewport || !slot || !sheet) return;

  function fitCard() {
    if (window.matchMedia('print').matches) return;
    /* Reset the slot before measuring so previous inline sizes don't constrain
       the natural sheet dimensions. */
    sheet.style.transform = 'none';
    slot.style.width = 'auto';
    slot.style.height = 'auto';
    var w = sheet.offsetWidth;
    var h = sheet.offsetHeight;
    if (!w || !h) return;
    var vw = viewport.clientWidth;
    var vh = viewport.clientHeight;
    if (!vw || !vh) return;
    /* 2px safety: rounding + sub-pixel borders on the card. */
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
  if (ro) {
    ro.observe(viewport);
    ro.observe(sheet);
  }
  window.addEventListener('resize', fitCardRaf);
  if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', fitCardRaf);
  }
  window.addEventListener('afterprint', fitCard);
  window.addEventListener('load', fitCardRaf);
  document.fonts && document.fonts.ready.then(fitCardRaf);
  sheet.querySelectorAll('img').forEach(function (img) {
    if (!img.complete) img.addEventListener('load', fitCardRaf);
  });
  fitCardRaf();

  var peers = [];
  try {
    peers = JSON.parse(root.getAttribute('data-peers') || '[]');
  } catch (e) { peers = []; }

  function norm(s) {
    return String(s || '').toLowerCase().replace(/\s+/g, ' ').trim();
  }

  function findPeers(query) {
    var q = norm(query);
    if (!q) return [];
    return peers.filter(function (p) {
      var adm = norm(p.admission);
      var nm = norm(p.name);
      return adm.indexOf(q) !== -1 || nm.indexOf(q) !== -1 || q.split(' ').every(function (part) {
        return !part || nm.indexOf(part) !== -1;
      });
    });
  }

  var searchInput = document.getElementById('reportPeerSearch');
  var goBtn = document.getElementById('reportPeerGo');
  var baseUrl = <?= json_encode(rtrim($base, '/') . $portalPrefix . '/reports/student/', JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_SLASHES) ?>;

  function goSearch() {
    if (!searchInput) return;
    var raw = searchInput.value;
    var nq = norm(raw);
    if (!nq) return;

    var exactAdm = peers.find(function (p) { return norm(p.admission) === nq; });
    if (exactAdm) {
      window.location.href = baseUrl + exactAdm.id + '?' + <?= json_encode($qs, JSON_HEX_TAG | JSON_HEX_APOS) ?>;
      return;
    }

    var matches = findPeers(raw);
    if (matches.length === 0) {
      window.alert('No student matches that name or admission number in this class list.');
      return;
    }
    if (matches.length === 1) {
      window.location.href = baseUrl + matches[0].id + '?' + <?= json_encode($qs, JSON_HEX_TAG | JSON_HEX_APOS) ?>;
      return;
    }
    var lines = matches.slice(0, 8).map(function (m) {
      return m.name + ' — ' + (m.admission || '—');
    }).join('\n');
    var more = matches.length > 8 ? '\n… and ' + (matches.length - 8) + ' more — narrow your search.' : '';
    window.alert('Several matches:\n\n' + lines + more);
  }

  if (goBtn) goBtn.addEventListener('click', goSearch);
  if (searchInput) {
    searchInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        goSearch();
      }
    });
  }

  var prevUrl = root.getAttribute('data-prev-url') || '';
  var nextUrl = root.getAttribute('data-next-url') || '';
  document.addEventListener('keydown', function (e) {
    if (!e.key || e.ctrlKey || e.metaKey || e.altKey) return;
    var tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
    if (tag === 'input' || tag === 'textarea' || tag === 'select' || (e.target && e.target.isContentEditable)) return;
    if (e.key === 'ArrowLeft' && prevUrl) {
      e.preventDefault();
      window.location.href = prevUrl;
    } else if (e.key === 'ArrowRight' && nextUrl) {
      e.preventDefault();
      window.location.href = nextUrl;
    }
  });
})();
</script>
