<?php
/**
 * Period selector — sits at the top of every bursar page so the bursar
 * picks the academic year + term they want to work on. The selection is
 * stored in the session by BursarController::setPeriod() and read back
 * via FeesService::activePeriod().
 *
 * Required vars from the layout: $base, $csrf, $period (current selection).
 */
use App\Core\View;
use App\Services\FeesService;

$activeYear = (string) ($period['year'] ?? FeesService::currentYear());
$activeTerm = (string) ($period['term'] ?? FeesService::currentTerm());
$years      = FeesService::knownYears();

// Send the user back to the page they were on after they change period.
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/bursar', PHP_URL_PATH) ?? '/bursar';
$reqQs   = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY) ?? '');
$relReq  = ($base !== '' && str_starts_with($reqPath, $base)) ? substr($reqPath, strlen($base)) : $reqPath;
if ($relReq === '' || !str_starts_with($relReq, '/bursar') || $relReq === '/bursar/period') {
    $relReq = '/bursar';
}
if ($reqQs !== '') $relReq .= '?' . $reqQs;
?>
<div class="bursar-period-bar mb-3">
  <form method="post" action="<?= $base ?>/bursar/period" class="card border-0 shadow-sm">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <input type="hidden" name="return" value="<?= View::e($relReq) ?>">

    <div class="card-body py-2 px-3 d-flex flex-wrap align-items-center gap-2">
      <span class="badge bg-primary-subtle text-primary-emphasis d-inline-flex align-items-center gap-1 me-1">
        <i class="bi bi-calendar-event"></i>
        Active period
      </span>

      <label class="form-label small fw-semibold mb-0 ms-1" for="periodYear">Year</label>
      <select id="periodYear" name="year" class="form-select form-select-sm" style="width:auto;">
        <?php foreach ($years as $y): ?>
          <option value="<?= View::e($y) ?>" <?= $y === $activeYear ? 'selected' : '' ?>>
            <?= View::e($y) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label class="form-label small fw-semibold mb-0 ms-2" for="periodTerm">Term</label>
      <select id="periodTerm" name="term" class="form-select form-select-sm" style="width:auto;">
        <?php foreach (FeesService::TERMS as $t): ?>
          <option value="<?= View::e($t) ?>" <?= $t === $activeTerm ? 'selected' : '' ?>>
            <?= View::e($t) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <button type="submit" class="btn btn-primary btn-sm">
        <i class="bi bi-check2"></i> Apply
      </button>

      <span class="ms-auto small text-muted d-none d-md-inline">
        <i class="bi bi-info-circle"></i>
        All bills, payments, and reports below are scoped to this year &amp; term.
      </span>
    </div>
  </form>
</div>
