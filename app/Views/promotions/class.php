<?php
use App\Core\View;
use App\Services\PromotionService;

$layout = 'app';
$title  = 'Promote ' . $class['name'];

$students     = $students ?? [];
$processed    = $processed ?? [];
$averages     = $averages ?? [];
$others       = $others ?? [];
$onlyStudent  = (int) ($onlyStudent ?? 0);
$className    = mb_strtoupper((string) $class['name'], 'UTF-8');
$promoteId    = $promoteGuess ? (int) $promoteGuess['id'] : 0;
$demoteId     = $demoteGuess ? (int) $demoteGuess['id'] : 0;
$defaultAction = $isFinal ? 'graduate' : ($promoteId ? 'promote' : 'skip');
$pending      = array_filter($students, static fn ($s) => !isset($processed[(int) $s['id']]));

$classOptions = function (int $selected) use ($others): string {
    $html = '<option value="">Choose a class</option>';
    foreach ($others as $c) {
        $upper = PromotionService::isUpperLevel($c['level'] ?? null) ? '1' : '0';
        $label = mb_strtoupper((string) $c['name'], 'UTF-8') . (!empty($c['level']) ? ' · ' . $c['level'] : '');
        $html .= '<option value="' . (int) $c['id'] . '" data-upper="' . $upper . '"'
            . ((int) $c['id'] === $selected ? ' selected' : '') . '>' . View::e($label) . '</option>';
    }
    return $html;
};
?>

<div class="page-header mb-4">
  <div>
    <nav aria-label="breadcrumb" class="mb-2">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= $base ?>/promotions?year=<?= rawurlencode($year) ?>">Promotions</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= View::e($className) ?></li>
      </ol>
    </nav>
    <h2 class="h4 mb-1"><i class="bi bi-arrow-up-circle"></i> <?= View::e($className) ?>
      <span class="text-muted fw-normal">&middot; end of <?= View::e($year) ?></span></h2>
    <p class="page-header__sub mb-0">
      <?php if ($onlyStudent): ?>
        Decide for this student. <a href="<?= $base ?>/promotions/class/<?= (int) $class['id'] ?>?year=<?= rawurlencode($year) ?>">Show the whole class</a>
      <?php else: ?>
        Choose what happens to each student. Everyone starts on
        <strong><?= $defaultAction === 'graduate' ? 'Graduate' : ($defaultAction === 'promote' ? 'Promote' : 'No change') ?></strong>:
        change only the ones repeating or moving down.
      <?php endif; ?>
    </p>
  </div>
</div>

<form method="get" action="<?= $base ?>/promotions/class/<?= (int) $class['id'] ?>" data-auto-reload class="mb-3">
  <?php if ($onlyStudent): ?><input type="hidden" name="student" value="<?= $onlyStudent ?>"><?php endif; ?>
  <label class="small fw-semibold me-2" for="pcYear">Academic year being completed</label>
  <select name="year" id="pcYear" class="form-select form-select-sm d-inline-block w-auto">
    <?php foreach ($years as $y): ?>
      <option value="<?= View::e($y) ?>" <?= $y === $year ? 'selected' : '' ?>><?= View::e($y) ?></option>
    <?php endforeach; ?>
  </select>
</form>

<?php if (!$students): ?>
  <div class="alert alert-info">
    <?= $onlyStudent ? 'That student is not in this class.' : 'There are no students in ' . View::e($className) . ' right now.' ?>
  </div>
<?php else: ?>
<form method="post" action="<?= $base ?>/promotions/class/<?= (int) $class['id'] ?>" id="promotionForm"
      data-confirm="Apply these decisions for <?= View::e($className) ?>? You can undo the whole run afterwards from the Promotions page.">
  <input type="hidden" name="_csrf" value="<?= $csrf ?>">
  <input type="hidden" name="year" value="<?= View::e($year) ?>">

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body row g-3">
      <div class="col-md-6">
        <label class="form-label fw-semibold" for="promoteTo"><i class="bi bi-arrow-up text-success"></i> Promote into</label>
        <select name="promote_to" id="promoteTo" class="form-select"><?= $classOptions($promoteId) ?></select>
        <?php if ($isFinal): ?>
          <p class="form-text mb-0"><?= View::e((string) $class['level']) ?> is the final year: students normally <strong>graduate</strong>.</p>
        <?php elseif (!$promoteId): ?>
          <p class="form-text mb-0 text-warning-emphasis">No class one level up was found. Pick one, or create it on the Classes page first.</p>
        <?php endif; ?>
      </div>
      <div class="col-md-6">
        <label class="form-label fw-semibold" for="demoteTo"><i class="bi bi-arrow-down text-danger"></i> Move down into</label>
        <select name="demote_to" id="demoteTo" class="form-select"><?= $classOptions($demoteId) ?></select>
        <p class="form-text mb-0">Only used for students set to <em>Move down</em>.</p>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-transparent d-flex flex-wrap align-items-center gap-2">
      <span class="fw-semibold me-auto"><?= count($students) ?> student<?= count($students) === 1 ? '' : 's' ?></span>
      <?php if (count($pending) > 1): ?>
        <span class="small text-muted">Set everyone to:</span>
        <div class="btn-group btn-group-sm" role="group">
          <?php if (!$isFinal): ?><button type="button" class="btn btn-outline-success" data-set-all="promote">Promote</button><?php endif; ?>
          <button type="button" class="btn btn-outline-secondary" data-set-all="repeat">Repeat</button>
          <?php if ($isFinal): ?><button type="button" class="btn btn-outline-primary" data-set-all="graduate">Graduate</button><?php endif; ?>
          <button type="button" class="btn btn-outline-secondary" data-set-all="skip">No change</button>
        </div>
      <?php endif; ?>
    </div>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Admission</th>
            <th>Student</th>
            <th class="text-center">Latest average</th>
            <th style="min-width:11rem">Decision</th>
            <th class="promo-stream-col" style="min-width:9rem">Stream (Form 3/4)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($students as $s):
            $sid = (int) $s['id'];
            $done = $processed[$sid] ?? null;
            $avg = $averages[$sid] ?? null;
            $stream = (string) ($s['stream'] ?? 'none');
          ?>
            <tr class="<?= $done ? 'table-light text-muted' : '' ?>">
              <td class="font-monospace small"><?= View::e((string) $s['admission_no']) ?></td>
              <td><?= View::e($s['first_name'] . ' ' . $s['last_name']) ?></td>
              <td class="text-center">
                <?php if ($avg): ?>
                  <span class="fw-semibold"><?= number_format($avg['avg'], 1) ?>%</span>
                  <span class="small text-muted d-block"><?= View::e($avg['term']) ?></span>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($done): ?>
                  <span class="small"><i class="bi bi-check2-circle text-success"></i>
                    Already moved for <?= View::e($year) ?><?= !empty($done['from_class_name']) ? ' (came from ' . View::e(mb_strtoupper((string) $done['from_class_name'], 'UTF-8')) . ')' : '' ?></span>
                <?php else: ?>
                  <select name="action[<?= $sid ?>]" class="form-select form-select-sm" data-action-select>
                    <?php if (!$isFinal): ?><option value="promote" <?= $defaultAction === 'promote' ? 'selected' : '' ?>>Promote</option><?php endif; ?>
                    <option value="repeat">Repeat <?= View::e((string) ($class['level'] ?: 'class')) ?></option>
                    <option value="demote">Move down</option>
                    <?php if ($isFinal): ?><option value="graduate" <?= $defaultAction === 'graduate' ? 'selected' : '' ?>>Graduate</option><?php endif; ?>
                    <option value="skip" <?= $defaultAction === 'skip' ? 'selected' : '' ?>>No change</option>
                  </select>
                <?php endif; ?>
              </td>
              <td class="promo-stream-col">
                <?php if (!$done && $stream === 'none'): ?>
                  <select name="stream[<?= $sid ?>]" class="form-select form-select-sm" data-stream-select>
                    <option value="">Decide later</option>
                    <option value="science">Science</option>
                    <option value="arts">Arts</option>
                  </select>
                <?php elseif ($stream !== 'none'): ?>
                  <span class="small text-capitalize"><?= View::e($stream) ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($pending): ?>
      <div class="card-footer bg-transparent d-flex flex-wrap align-items-center gap-2">
        <span class="small text-muted me-auto" data-promo-summary></span>
        <a href="<?= $base ?>/promotions?year=<?= rawurlencode($year) ?>" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i> Apply decisions</button>
      </div>
    <?php endif; ?>
  </div>
</form>

<script>
(function () {
  var form = document.getElementById('promotionForm');
  if (!form) return;
  var promoteTo = document.getElementById('promoteTo');
  var demoteTo = document.getElementById('demoteTo');
  var selects = Array.prototype.slice.call(form.querySelectorAll('[data-action-select]'));
  var summary = form.querySelector('[data-promo-summary]');
  var labels = { promote: 'promote', repeat: 'repeat', demote: 'move down', graduate: 'graduate', skip: 'no change' };

  function targetIsUpper(sel) {
    var opt = sel && sel.options[sel.selectedIndex];
    return !!opt && opt.getAttribute('data-upper') === '1';
  }

  function refresh() {
    var counts = {};
    selects.forEach(function (s) {
      counts[s.value] = (counts[s.value] || 0) + 1;
      // Stream only matters when this student lands in Form 3/4.
      var tr = s.closest('tr');
      var st = tr && tr.querySelector('[data-stream-select]');
      if (st) {
        var needs = (s.value === 'promote' && targetIsUpper(promoteTo))
                 || (s.value === 'demote' && targetIsUpper(demoteTo));
        st.disabled = !needs;
        st.style.visibility = needs ? '' : 'hidden';
      }
    });
    var anyStream = form.querySelector('[data-stream-select]:not([disabled])');
    form.querySelectorAll('.promo-stream-col').forEach(function (c) { c.style.display = anyStream ? '' : 'none'; });
    if (summary) {
      var parts = [];
      Object.keys(labels).forEach(function (k) { if (counts[k]) parts.push(counts[k] + ' ' + labels[k]); });
      summary.textContent = parts.join(' · ');
    }
  }

  selects.forEach(function (s) { s.addEventListener('change', refresh); });
  promoteTo.addEventListener('change', refresh);
  demoteTo.addEventListener('change', refresh);
  form.querySelectorAll('[data-set-all]').forEach(function (b) {
    b.addEventListener('click', function () {
      var v = b.getAttribute('data-set-all');
      selects.forEach(function (s) { s.value = v; });
      refresh();
    });
  });
  form.addEventListener('submit', function (e) {
    var needPromote = selects.some(function (s) { return s.value === 'promote'; }) && !promoteTo.value;
    var needDemote = selects.some(function (s) { return s.value === 'demote'; }) && !demoteTo.value;
    if (needPromote || needDemote) {
      e.preventDefault();
      e.stopImmediatePropagation();
      alert(needPromote ? 'Choose the class to promote into.' : 'Choose the class to move students down into.');
    }
  }, true);
  refresh();
})();
</script>
<?php endif; ?>
