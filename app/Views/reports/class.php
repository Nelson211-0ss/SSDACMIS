<?php
use App\Core\View;
use App\Core\Settings;
use App\Core\App;
use App\Services\AcademicMarking;
$layout = 'app';
$title  = (string) ($class['name'] ?? 'Class Report');
$schoolName  = Settings::get('school_name') ?: App::config('app.name');
$schoolMotto = Settings::get('school_motto') ?? '';
$schoolLogo  = Settings::logoUrl();

function letter_for($score) {
    if ($score === null) return '—';
    return AcademicMarking::letterGrade((float) $score);
}

$qs = 'year=' . rawurlencode($year) . '&term=' . rawurlencode($term);
$matrixPeers = [];
foreach (($students ?? []) as $s) {
    $matrixPeers[] = [
        'id'        => (int) $s['id'],
        'admission' => (string) ($s['admission_no'] ?? ''),
        'name'      => trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? '')),
    ];
}
$matrixPeersAttr = htmlspecialchars(json_encode($matrixPeers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$nMatrix = count($matrixPeers);
?>
<div class="report-toolbar report-toolbar--v2 report-toolbar--student mb-3 d-print-none"
     id="classMatrixToolbar"
     data-peers="<?= $matrixPeersAttr ?>"
     data-student-base="<?= View::e(rtrim($base, '/') . $portalPrefix . '/reports/student/') ?>"
     data-qs="<?= View::e($qs) ?>">
  <div class="d-flex flex-wrap align-items-end gap-2 gap-xl-3 justify-content-between">
    <div class="d-flex flex-wrap align-items-end gap-2 gap-md-3 flex-grow-1 min-w-0">
      <a class="btn btn-outline-secondary btn-sm flex-shrink-0"
         href="<?= $base ?><?= $portalPrefix ?>/reports?<?= View::e($qs) ?>">
        <i class="bi bi-arrow-left"></i> Back
      </a>
      <form method="get" class="d-flex flex-wrap gap-2 align-items-end flex-shrink-0"
            action="<?= $base ?><?= $portalPrefix ?>/reports/class/<?= (int) $class['id'] ?>">
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
        <button class="btn btn-outline-primary btn-sm">Reload</button>
      </form>
      <?php if ($nMatrix > 0): ?>
        <div class="report-toolbar__peers d-flex flex-wrap align-items-center gap-2 flex-grow-1 min-w-0">
          <span class="badge rounded-pill bg-body-secondary text-body border small fw-normal flex-shrink-0">
            <?= $nMatrix ?> student<?= $nMatrix === 1 ? '' : 's' ?>
          </span>
          <div class="compact-search compact-search--peer compact-search--toolbar">
            <label class="visually-hidden" for="classMatrixSearch">Open a student's report card</label>
            <div class="input-group input-group-sm">
              <span class="input-group-text py-1"><i class="bi bi-search"></i></span>
              <input type="search" class="form-control py-1" id="classMatrixSearch"
                     placeholder="Open student…" autocomplete="off"
                     title="Type a name or admission no., then Enter to open the report card.">
              <button type="button" class="btn btn-outline-primary px-2" id="classMatrixGo" title="Open">
                <i class="bi bi-arrow-right" aria-hidden="true"></i><span class="visually-hidden">Open</span>
              </button>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
    <div class="d-flex flex-wrap gap-2 flex-shrink-0 ms-auto">
      <a class="btn btn-outline-primary btn-sm"
         href="<?= $base ?><?= $portalPrefix ?>/reports/class/<?= (int) $class['id'] ?>/booklet?<?= View::e($qs) ?>"
         title="Browse one-by-one, with a print-all option">
        <i class="bi bi-file-person"></i> Open booklet
      </a>
      <button class="btn btn-primary btn-sm" onclick="window.print()">
        <i class="bi bi-printer"></i> Print matrix
      </button>
    </div>
  </div>
</div>

<div class="report-page report-page--landscape report-page--print-landscape">
  <header class="report-head">
    <div class="report-head__brand">
      <?php if ($schoolLogo): ?>
        <img src="<?= $base ?>/<?= View::e($schoolLogo) ?>" alt="">
      <?php else: ?>
        <i class="bi bi-mortarboard-fill"></i>
      <?php endif; ?>
      <div>
        <div class="report-head__school"><?= View::e($schoolName) ?></div>
        <?php if ($schoolMotto !== ''): ?>
          <div class="report-head__motto fst-italic"><?= View::e($schoolMotto) ?></div>
        <?php endif; ?>
        <div class="report-head__sub">
          Class Report &middot; <?= View::e($class['name']) ?>
        </div>
      </div>
    </div>
    <div class="report-head__period">
      <div><strong>Year:</strong> <?= View::e($year) ?></div>
      <div><strong>Term:</strong> <?= View::e($term) ?></div>
      <div><strong>Class Teacher:</strong>
        <?= View::e(trim(($class['teacher_first'] ?? '').' '.($class['teacher_last'] ?? ''))) ?: '—' ?>
      </div>
    </div>
  </header>

  <?php if (empty($students)): ?>
    <div class="alert alert-info">No students in this class.</div>
  <?php elseif (empty($subjects)): ?>
    <div class="alert alert-info">
      No results to list for this period: either no subjects are enabled under <em>Subjects</em>,
      or no marks have been recorded for <?= View::e($class['name']) ?> in <?= View::e($year) ?> · <?= View::e($term) ?>.
    </div>
  <?php else: ?>
    <?php foreach ($groups as $groupKey => $group):
      $groupStudents = $group['students'] ?? [];
      if (empty($groupStudents)) continue;

      // Restrict subject columns for streamed groups: Form 3/4 Science groups
      // hide Arts subject columns and vice versa (core+optional always shown).
      if (!empty($isUpperForm) && in_array($groupKey, ['science', 'arts'], true)) {
          $sectionSubjects = array_values(array_filter($subjects, function ($sub) use ($groupKey) {
              $cat = (string) ($sub['category'] ?? '');
              if ($cat === $groupKey) return true;
              return !in_array($cat, ['science', 'arts'], true);
          }));
      } else {
          $sectionSubjects = $subjects;
      }
    ?>
      <?php if (!empty($isUpperForm)): ?>
        <h5 class="mt-3 mb-2">
          <?= View::e($group['label']) ?>
          <span class="text-muted small">(<?= count($groupStudents) ?> student<?= count($groupStudents) === 1 ? '' : 's' ?>, ranked within stream)</span>
        </h5>
      <?php endif; ?>
      <div class="report-matrix-scroll">
      <table class="report-table report-matrix">
        <thead>
          <tr>
            <th rowspan="2" class="t-pos">Pos</th>
            <th rowspan="2" class="t-name">Student</th>
            <?php foreach ($sectionSubjects as $sub): ?>
              <th colspan="3" class="t-subject-group">
                <?= View::e($sub['code'] ?: substr($sub['name'], 0, 4)) ?>
              </th>
            <?php endforeach; ?>
            <th rowspan="2" class="t-num">Σ Tot</th>
            <th rowspan="2" class="t-num">Avg %</th>
            <th rowspan="2" class="t-grade">Gr</th>
          </tr>
          <tr>
            <?php foreach ($sectionSubjects as $sub): ?>
              <th class="t-num small">M</th>
              <th class="t-num small">E</th>
              <th class="t-num small">T</th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php
            // Sort group students by position
            usort($groupStudents, function ($a, $b) use ($matrix) {
                $pa = $matrix[(int) $a['id']]['_position'] ?? 9999;
                $pb = $matrix[(int) $b['id']]['_position'] ?? 9999;
                return $pa <=> $pb;
            });
            foreach ($groupStudents as $st):
              $sid = (int) $st['id'];
              $row = $matrix[$sid] ?? [];
          ?>
            <tr>
              <td class="t-pos"><?= isset($row['_position']) ? (int) $row['_position'] : '—' ?></td>
              <td class="t-name">
                <?= View::e($st['first_name'].' '.$st['last_name']) ?>
                <span class="text-muted small">(<?= View::e($st['admission_no']) ?>)</span>
              </td>
              <?php foreach ($sectionSubjects as $sub):
                $cell = $row[(int) $sub['id']] ?? ['mid' => null, 'end' => null, 'total' => null];
              ?>
                <td class="t-num"><?= $cell['mid'] !== null ? number_format($cell['mid'], 0) : '—' ?></td>
                <td class="t-num"><?= $cell['end'] !== null ? number_format($cell['end'], 0) : '—' ?></td>
                <td class="t-num"><?= isset($cell['total']) && $cell['total'] !== null ? number_format((float) $cell['total'], 0) : '—' ?></td>
              <?php endforeach; ?>
              <td class="t-num"><strong><?= number_format($row['_total'] ?? 0, 0) ?></strong></td>
              <td class="t-num"><?= isset($row['_average']) && $row['_average'] !== null ? number_format($row['_average'], 1) : '—' ?></td>
              <td class="t-grade"><strong><?= letter_for($row['_average'] ?? null) ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endforeach; ?>

    <div class="small text-muted mt-2">
      M = Mid-term (×/30) · E = End-term (×/70) · T = Subject total (Mid+End, max 100) · Σ Tot = sum of subject totals · Avg % = mean across subjects with both components · Position uses competition ranking on average %.
      <?php if (!empty($isUpperForm)): ?>
        Form 3 &amp; Form 4 students are ranked within their stream.
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <footer class="report-footer">
    Issued: <?= date('d M Y') ?>
  </footer>
</div>

<?php if ($nMatrix > 0): ?>
<script>
(function () {
  var bar = document.getElementById('classMatrixToolbar');
  if (!bar) return;
  var input = document.getElementById('classMatrixSearch');
  var go    = document.getElementById('classMatrixGo');
  if (!input) return;

  var peers = [];
  try { peers = JSON.parse(bar.getAttribute('data-peers') || '[]'); } catch (e) { peers = []; }
  var baseUrl = bar.getAttribute('data-student-base') || '';
  var qs      = bar.getAttribute('data-qs') || '';

  function norm(s) { return String(s || '').toLowerCase().replace(/\s+/g, ' ').trim(); }

  function findPeers(q) {
    var nq = norm(q);
    if (!nq) return [];
    return peers.filter(function (p) {
      var adm = norm(p.admission), nm = norm(p.name);
      return adm.indexOf(nq) !== -1 || nm.indexOf(nq) !== -1 || nq.split(' ').every(function (part) {
        return !part || nm.indexOf(part) !== -1;
      });
    });
  }

  function open() {
    var raw = input.value;
    var nq = norm(raw);
    if (!nq) return;
    var target = peers.find(function (p) { return norm(p.admission) === nq; });
    if (!target) {
      var matches = findPeers(raw);
      if (matches.length === 0) {
        window.alert('No student in this class matches that name or admission number.');
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
    window.location.href = baseUrl + target.id + (qs ? ('?' + qs) : '');
  }

  if (go) go.addEventListener('click', open);
  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); open(); }
  });
})();
</script>
<?php endif; ?>
