<?php
use App\Core\View;
use App\Services\AcademicMarking;
$layout = 'app';
$title = 'Results — ' . ($class['name'] ?? '');
$schoolName = $schoolName ?? '';

/** Short label for dense columns (code preferred). */
$subLabel = static function (array $sub): string {
    $code = trim((string) ($sub['code'] ?? ''));
    if ($code !== '') {
        return mb_strlen($code) > 8 ? mb_substr($code, 0, 7) . '…' : $code;
    }
    $name = (string) ($sub['name'] ?? '');
    return mb_strlen($name) > 10 ? mb_substr($name, 0, 9) . '…' : $name;
};
?>
<div class="results-landscape-root results-print-area report-page--print-landscape">
  <div class="results-toolbar d-print-none mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
      <a class="btn btn-outline-secondary btn-sm"
         href="<?= $base ?><?= $portalPrefix ?>/results?year=<?= rawurlencode($year) ?>&term=<?= rawurlencode($term) ?>">
        <i class="bi bi-arrow-left"></i> Classes
      </a>
      <div class="d-flex flex-wrap gap-2 align-items-end justify-content-end flex-grow-1">
        <form method="get" class="d-flex flex-wrap gap-2 align-items-end"
              action="<?= $base ?><?= $portalPrefix ?>/results/class/<?= (int) $class['id'] ?>">
          <div>
            <label class="form-label small mb-1">Year</label>
            <select name="year" class="form-select form-select-sm">
              <?php foreach (($years ?? []) as $y): ?>
                <option value="<?= View::e($y) ?>" <?= $y === $year ? 'selected' : '' ?>><?= View::e($y) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label small mb-1">Term</label>
            <select name="term" class="form-select form-select-sm">
              <?php foreach (($terms ?? []) as $t): ?>
                <option <?= $t === $term ? 'selected' : '' ?>><?= View::e($t) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-outline-primary btn-sm">Reload</button>
        </form>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()" title="Print this results sheet">
          <i class="bi bi-printer"></i> Print
        </button>
      </div>
    </div>
  </div>

  <div class="results-print-brand border-bottom pb-2 mb-2 d-none d-print-block">
    <div class="fw-bold"><?= View::e($schoolName) ?></div>
    <div><?= View::e($class['name'] ?? '') ?><?php if (!empty($class['level'])): ?> · <?= View::e($class['level']) ?><?php endif; ?></div>
    <div class="small"><?= View::e($year) ?> · <?= View::e($term) ?> · Competition ranking on average %</div>
  </div>

  <div class="mb-2 d-print-none">
    <h4 class="mb-1"><?= View::e($class['name']) ?></h4>
    <div class="small text-muted">
      <?= View::e($year) ?> · <?= View::e($term) ?> · Wide landscape layout fits columns without horizontal scrollbar where possible (subject codes shorten headers).
    </div>
  </div>

  <?php if (empty($rows)): ?>
    <div class="alert alert-warning border-0 shadow-sm d-print-none">
      No computed results for this class and period yet. Enter <strong>Mid-term</strong> (max <?= (int) $midMax ?>)
      and <strong>End-of-term</strong> (max <?= (int) $endMax ?>) marks — totals and positions update automatically when marks are saved.
    </div>
  <?php else: ?>
    <?php if (!empty($subjectCols)): ?>
      <div class="results-table-panel mb-4">
        <table class="table table-sm align-middle bg-white results-density-table mb-0">
          <thead class="table-light">
            <tr>
              <th class="text-center rd-pos text-muted">Pos</th>
              <th class="rd-adm">Adm.</th>
              <th class="rd-name-col">Student</th>
              <?php foreach ($subjectCols as $sub): ?>
                <th class="text-center rd-subj small px-1" title="<?= View::e((string) ($sub['name'] ?? '')) ?>"><?= View::e($subLabel($sub)) ?></th>
              <?php endforeach; ?>
              <th class="text-center rd-avg">Avg%</th>
              <th class="text-center rd-gr">Gr</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r):
              $sid = (int) $r['student_id'];
            ?>
              <tr>
                <td class="text-center rd-pos"><?= $r['class_position'] !== null ? (int) $r['class_position'] : '—' ?></td>
                <td class="font-monospace small rd-adm"><?= View::e($r['admission_no']) ?></td>
                <td class="rd-name-col"><?= View::e(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))) ?></td>
                <?php foreach ($subjectCols as $sub):
                  $bid = (int) $sub['id'];
                  $cell = $cells[$sid][$bid] ?? null;
                ?>
                  <td class="text-center small rd-subj-cell px-1">
                    <?php if ($cell !== null && $cell['total'] !== null): ?>
                      <span class="font-monospace"><?= View::e(rtrim(rtrim(number_format((float) $cell['total'], 1, '.', ''), '0'), '.')) ?></span><?php if (!empty($cell['grade'])): ?><span class="text-muted ms-1"><?= View::e((string) $cell['grade']) ?></span><?php endif; ?>
                    <?php else: ?>
                      —
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
                <td class="text-center rd-avg"><strong><?= $r['average_percentage'] !== null ? View::e(number_format((float) $r['average_percentage'], 2)) : '—' ?></strong></td>
                <td class="text-center rd-gr">
                  <?php if ($r['average_percentage'] !== null): ?>
                    <span class="badge bg-secondary"><?= View::e(AcademicMarking::letterGrade((float) $r['average_percentage'])) ?></span>
                  <?php else: ?>
                    —
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="results-table-panel mb-4">
        <table class="table table-sm align-middle bg-white results-density-table mb-0">
          <thead class="table-light">
            <tr>
              <th class="text-center rd-pos">Pos</th>
              <th class="rd-adm">Admission</th>
              <th class="rd-name-col">Student</th>
              <th class="text-center">Subjects</th>
              <th class="text-center rd-avg">Avg %</th>
              <th class="text-center rd-gr">Gr</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td class="text-center rd-pos"><?= $r['class_position'] !== null ? (int) $r['class_position'] : '—' ?></td>
                <td class="font-monospace small rd-adm"><?= View::e($r['admission_no']) ?></td>
                <td class="rd-name-col"><?= View::e(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))) ?></td>
                <td class="text-center"><?= (int) ($r['subjects_with_totals'] ?? 0) ?></td>
                <td class="text-center rd-avg"><strong><?= $r['average_percentage'] !== null ? View::e(number_format((float) $r['average_percentage'], 2)) : '—' ?></strong></td>
                <td class="text-center rd-gr">
                  <?php if ($r['average_percentage'] !== null): ?>
                    <span class="badge bg-secondary"><?= View::e(AcademicMarking::letterGrade((float) $r['average_percentage'])) ?></span>
                  <?php else: ?>
                    —
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
