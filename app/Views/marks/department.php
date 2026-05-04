<?php
use App\Core\View;
$layout = 'app';
$title = 'Department Marks Entry';
$catLabel = ['core'=>'Compulsory Core','science'=>'Science','arts'=>'Arts','optional'=>'Optional'];
$catBadge = ['core'=>'bg-primary-subtle text-primary-emphasis','science'=>'bg-success-subtle text-success-emphasis','arts'=>'bg-warning-subtle text-warning-emphasis','optional'=>'bg-secondary-subtle text-secondary-emphasis'];
$catName  = $catLabel[$category] ?? ucfirst($category);
$badge    = $catBadge[$category]  ?? 'bg-secondary';
$existingMid = $existingMid ?? [];
$existingEnd = $existingEnd ?? [];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <h4 class="mb-1">
      <i class="bi bi-mortarboard"></i>
      <?= View::e($catName) ?> Department
      <span class="text-muted fw-normal">·</span>
      <?= View::e($class['name']) ?>
    </h4>
    <div class="small text-muted">
      <span class="badge text-capitalize <?= $badge ?>"><?= View::e($category) ?></span>
      &middot; Mid-term &amp; End-term on one sheet
      &middot; <?= View::e($year) ?>
      &middot; <?= View::e($term) ?>
    </div>
  </div>
  <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?><?= $portalPrefix ?>/marks">
    <i class="bi bi-arrow-left"></i> Back
  </a>
</div>

<?php
  $classLevel = trim((string) ($class['level'] ?? ''));
  $isUpperForm = ($classLevel === 'Form 3' || $classLevel === 'Form 4');
  $streamScoped = $isUpperForm && in_array($category, ['science', 'arts'], true);
?>

<?php if ($streamScoped): ?>
  <div class="alert alert-warning py-2 small d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-funnel-fill"></i>
    <div>
      Form 3/4 <strong class="text-capitalize"><?= View::e($category) ?></strong> department: only <strong class="text-capitalize"><?= View::e($category) ?></strong> stream students appear in this matrix.
    </div>
  </div>
<?php endif; ?>

<form method="get" action="<?= $base ?><?= $portalPrefix ?>/marks/department" class="card border-0 shadow-sm mb-3">
  <input type="hidden" name="class_id" value="<?= (int) $class['id'] ?>">
  <input type="hidden" name="category" value="<?= View::e($category) ?>">
  <div class="card-body row g-3 align-items-end">
    <div class="col-md-4">
      <label class="form-label">Academic year <span class="text-danger">*</span></label>
      <select name="year" class="form-select" required>
        <?php foreach (($years ?? []) as $y): ?>
          <option value="<?= View::e($y) ?>" <?= $y === $year ? 'selected' : '' ?>><?= View::e($y) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Term <span class="text-danger">*</span></label>
      <select name="term" class="form-select" required>
        <?php foreach ($terms as $t): ?>
          <option <?= $t === $term ? 'selected' : '' ?>><?= View::e($t) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <button class="btn btn-outline-primary w-100"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
    </div>
  </div>
</form>

<?php if (empty($subjects)): ?>
  <div class="alert alert-warning">
    There are no subjects in the <strong><?= View::e($catName) ?></strong> department yet.
  </div>
<?php elseif (empty($students)): ?>
  <div class="alert alert-info">No students in <?= View::e($class['name']) ?> yet.</div>
<?php else: ?>
  <form id="marks-dept-form" method="post" action="<?= $base ?><?= $portalPrefix ?>/marks/department">
    <input type="hidden" name="_csrf"     value="<?= $csrf ?>">
    <input type="hidden" name="class_id"  value="<?= (int) $class['id'] ?>">
    <input type="hidden" name="category"  value="<?= View::e($category) ?>">
    <input type="hidden" name="year"      value="<?= View::e($year) ?>">
    <input type="hidden" name="term"      value="<?= View::e($term) ?>">

    <div class="card border-0 shadow-sm dept-marks-card">
      <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle dept-marks-table">
          <thead class="table-light">
            <tr>
              <th class="dept-marks-sticky-num">#</th>
              <th class="dept-marks-sticky-adm">Admission</th>
              <th class="dept-marks-sticky-name">Student</th>
              <?php foreach ($subjects as $sub): ?>
                <th class="text-center dept-marks-subj-head" colspan="2">
                  <div class="fw-semibold small"><?= View::e($sub['name']) ?></div>
                  <?php if (!empty($sub['code'])): ?>
                    <div class="text-muted extra-small"><?= View::e($sub['code']) ?></div>
                  <?php endif; ?>
                  <div class="d-flex border-top mt-1 pt-1">
                    <span class="flex-fill text-primary small" title="Max 30">Mid</span>
                    <span class="flex-fill text-indigo small" title="Max 70">End</span>
                  </div>
                </th>
              <?php endforeach; ?>
              <th class="text-center small" style="width:5rem" title="Mean of subject totals (Mid+End where both entered)">Avg %</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($students as $i => $st):
              $sid = (int) $st['id'];
              $rowMid = $existingMid[$sid] ?? [];
              $rowEnd = $existingEnd[$sid] ?? [];
            ?>
              <tr data-row>
                <td class="text-muted small dept-marks-sticky-num"><?= $i + 1 ?></td>
                <td class="font-monospace small dept-marks-sticky-adm"><?= View::e($st['admission_no']) ?></td>
                <td class="dept-marks-sticky-name"><?= View::e($st['first_name'] . ' ' . $st['last_name']) ?></td>
                <?php foreach ($subjects as $sub):
                  $subId = (int) $sub['id'];
                  $m = $rowMid[$subId] ?? '';
                  $e = $rowEnd[$subId] ?? '';
                  $md = $m !== '' ? rtrim(rtrim(number_format((float) $m, 2, '.', ''), '0'), '.') : '';
                  $ed = $e !== '' ? rtrim(rtrim(number_format((float) $e, 2, '.', ''), '0'), '.') : '';
                ?>
                  <td class="p-1 text-center" style="min-width:4.5rem">
                    <input type="number" min="0" max="30" step="0.01"
                           class="form-control form-control-sm score-mid text-end"
                           name="scores_mid[<?= $sid ?>][<?= $subId ?>]"
                           value="<?= View::e($md) ?>"
                           placeholder="—" aria-label="Mid <?= View::e($sub['name']) ?>">
                  </td>
                  <td class="p-1 text-center" style="min-width:4.5rem">
                    <input type="number" min="0" max="70" step="0.01"
                           class="form-control form-control-sm score-end text-end"
                           name="scores_end[<?= $sid ?>][<?= $subId ?>]"
                           value="<?= View::e($ed) ?>"
                           placeholder="—" aria-label="End <?= View::e($sub['name']) ?>">
                  </td>
                <?php endforeach; ?>
                <td class="text-center"><span class="badge bg-secondary row-avg">—</span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="small text-muted">
          Mid-term ≤ 30 · End-of-term ≤ 70 per subject. Leave blank to skip. Results overview updates after save.
        </div>
        <div class="d-flex gap-2">
          <a class="btn btn-outline-primary btn-sm"
             href="<?= $base ?><?= $portalPrefix ?>/reports/class/<?= (int) $class['id'] ?>/booklet?year=<?= rawurlencode($year) ?>&term=<?= rawurlencode($term) ?>">
            <i class="bi bi-printer"></i> Print class reports
          </a>
          <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save all marks</button>
        </div>
      </div>
    </div>
  </form>
<?php endif; ?>

<style>
  .dept-marks-table thead th { white-space: nowrap; vertical-align: bottom; }
  .dept-marks-sticky-num  { position: sticky; left: 0;  z-index: 2; background: var(--bs-body-bg); box-shadow: 1px 0 0 var(--bs-border-color); }
  .dept-marks-sticky-adm  { position: sticky; left: 2.25rem; z-index: 2; background: var(--bs-body-bg); box-shadow: 1px 0 0 var(--bs-border-color); }
  .dept-marks-sticky-name { position: sticky; left: 9.5rem; z-index: 2; min-width: 7rem; background: var(--bs-body-bg); box-shadow: 1px 0 0 var(--bs-border-color); }
  .dept-marks-subj-head { min-width: 7rem; }
  .text-indigo { color: #4f46e5 !important; }
  .dept-marks-card .form-control-sm { min-height: 2rem; padding: 0.2rem 0.35rem; font-size: 0.8125rem; }
  .extra-small { font-size: 0.65rem; }
</style>

<script src="<?= View::asset($base, 'assets/js/academic-marks.js') ?>"></script>
<script>
(function () {
  function pv(inp) {
    var v = String(inp.value || '').trim();
    if (v === '') return null;
    var n = parseFloat(v.replace(',', '.'));
    return Number.isFinite(n) ? n : NaN;
  }
  document.querySelectorAll('tr[data-row]').forEach(function (tr) {
    var mids = tr.querySelectorAll('.score-mid');
    var ends = tr.querySelectorAll('.score-end');
    var avgEl = tr.querySelector('.row-avg');
    if (!mids.length || !ends.length || !avgEl) return;

    function recalc() {
      var sum = 0, n = 0;
      for (var i = 0; i < mids.length; i++) {
        var m = pv(mids[i]);
        var e = pv(ends[i]);
        if (m === null || e === null) continue;
        if (!SSDACMIS.academicMarks.validateMid(m).ok || !SSDACMIS.academicMarks.validateEnd(e).ok) continue;
        sum += Math.min(100, m + e);
        n++;
      }
      if (!n) {
        avgEl.textContent = '—';
        avgEl.className = 'badge bg-secondary row-avg';
        return;
      }
      var avg = sum / n;
      avgEl.textContent = avg.toFixed(2);
      var lg = SSDACMIS.academicMarks.letterFromTotal(avg);
      avgEl.className = 'badge row-avg bg-' + lg[1];
    }
    [].forEach.call(mids, function (inp) { inp.addEventListener('input', recalc); });
    [].forEach.call(ends, function (inp) { inp.addEventListener('input', recalc); });
    recalc();
  });
  SSDACMIS.academicMarks.attachFormGuard('marks-dept-form', true);
  SSDACMIS.academicMarks.attachFieldBlurValidation(document.getElementById('marks-dept-form'));
})();
</script>
