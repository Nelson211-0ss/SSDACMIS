<?php
use App\Core\View;
$layout = 'app';
$title  = 'Choose Period';

$mode  = $mode  ?? 'index';
$extra = $extra ?? [];
$invalid = !empty($invalid);

$action = match ($mode) {
    'entry'      => $base . $portalPrefix . '/marks/entry',
    'department' => $base . $portalPrefix . '/marks/department',
    default      => $base . $portalPrefix . '/marks',
};

$selectedYear = $submittedYear !== '' ? $submittedYear : $defaultYear;
$selectedTerm = $submittedTerm !== '' ? $submittedTerm : '';

$isHodPortal = ($portalPrefix ?? '') === '/hod';

$contextHint = match ($mode) {
    'entry' => 'Your class and subject are carried through — set the academic year, term, and exam so grades file under the correct period.',
    'department' => 'Your class and department category are carried through — choose the academic year and term for this matrix.',
    default => 'Every grade is stored against an explicit year and term. Choose them before opening mark sheets or the department grid.',
};

$heroTitle = match ($mode) {
    'entry' => 'Finish choosing period',
    'department' => 'Finish choosing period',
    default => 'Choose academic period',
};
?>
<div class="marks-period-page">
  <section class="marks-period-page__hero dash-hero dash-hero--slim mb-4">
    <div class="marks-period-page__hero-row">
      <span class="icon-chip icon-chip--purple mx-auto" aria-hidden="true">
        <i class="bi bi-calendar2-week"></i>
      </span>
      <h2 class="dash-hero__title mb-2"><?= View::e($heroTitle) ?></h2>
      <p class="marks-period-page__lead mb-2"><?= View::e($contextHint) ?></p>
      <p class="dash-hero__sub mb-3 small">
        <?= $isHodPortal
          ? 'Department marking portal — periods match official reports and transcripts.'
          : 'This applies to bulk sheets and department matrices across the school.' ?>
      </p>
      <a class="btn btn-outline-secondary btn-sm shadow-sm"
         href="<?= $base ?><?= $portalPrefix ?>/marks">
        <i class="bi bi-arrow-left"></i> Back to marks
      </a>
    </div>
  </section>

  <?php if ($invalid): ?>
    <div class="marks-period-page__warn card border-0 shadow-sm mb-4" role="alert">
      <div class="marks-period-page__warn-inner">
        <span class="marks-period-page__warn-icon" aria-hidden="true"><i class="bi bi-exclamation-triangle-fill"></i></span>
        <div>
          <strong class="d-block mb-1">Invalid or incomplete period</strong>
          <p class="mb-0 small text-body-secondary">
            The year or term was missing or not recognized. Choose a valid academic year from the list
            and one of Term 1–3 — the system does not guess your period for you.
          </p>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <form method="get" action="<?= View::e($action) ?>" class="marks-period-page__panel card border-0 shadow-sm mb-4">
    <?php foreach ($extra as $k => $v): ?>
      <input type="hidden" name="<?= View::e((string) $k) ?>" value="<?= View::e((string) $v) ?>">
    <?php endforeach; ?>

    <div class="marks-period-page__panel-head">
      <span class="marks-period-page__panel-icon" aria-hidden="true"><i class="bi bi-sliders"></i></span>
      <div>
        <h3 class="marks-period-page__panel-title">Academic window</h3>
        <p class="marks-period-page__panel-sub mb-0">
          <?= $mode === 'department'
            ? 'Select year and term. (Department matrices include both mid-term and end-term on the sheet.)'
            : 'Select year, term, and whether you are entering mid-term or end-term marks.' ?>
        </p>
      </div>
    </div>

    <div class="card-body pt-4">
      <div class="row g-4 <?= $mode === 'department' ? 'justify-content-center' : '' ?>">
        <div class="<?= $mode === 'department' ? 'col-md-6 col-lg-5' : 'col-lg-4' ?>">
          <div class="marks-period-field">
            <label class="form-label fw-semibold d-flex align-items-center gap-2" for="marks-period-year">
              <span class="marks-period-field__step" aria-hidden="true">1</span>
              Academic year <span class="text-danger">*</span>
            </label>
            <select id="marks-period-year" name="year" class="form-select form-select-lg shadow-sm" required>
              <option value="">— Select year —</option>
              <?php foreach ($years as $y): ?>
                <option value="<?= View::e($y) ?>" <?= $y === $selectedYear ? 'selected' : '' ?>>
                  <?= View::e($y) ?><?= $y === $defaultYear ? ' (current)' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="marks-period-field__hint">Use the format <span class="font-monospace">YYYY/YYYY</span> (consecutive years).</div>
          </div>
        </div>

        <div class="<?= $mode === 'department' ? 'col-md-6 col-lg-5' : 'col-lg-4' ?>">
          <div class="marks-period-field">
            <label class="form-label fw-semibold d-flex align-items-center gap-2" for="marks-period-term">
              <span class="marks-period-field__step" aria-hidden="true">2</span>
              Term <span class="text-danger">*</span>
            </label>
            <select id="marks-period-term" name="term" class="form-select form-select-lg shadow-sm" required>
              <option value="">— Select term —</option>
              <?php foreach ($terms as $t): ?>
                <option value="<?= View::e($t) ?>" <?= $t === $selectedTerm ? 'selected' : '' ?>><?= View::e($t) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="marks-period-field__hint">Three terms per academic year.</div>
          </div>
        </div>

        <?php if ($mode !== 'department'): ?>
          <div class="col-lg-4">
            <div class="marks-period-field">
              <label class="form-label fw-semibold d-flex align-items-center gap-2" for="marks-period-exam">
                <span class="marks-period-field__step" aria-hidden="true">3</span>
                Exam component
              </label>
              <?php
                $defaultExam = $submittedExam !== '' ? $submittedExam : 'midterm';
              ?>
              <select id="marks-period-exam" name="exam_type" class="form-select form-select-lg shadow-sm">
                <?php foreach ($exams as $k => $label): ?>
                  <option value="<?= View::e($k) ?>" <?= $k === $defaultExam ? 'selected' : '' ?>><?= View::e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="marks-period-field__hint">Mid-term (÷30) or end-term (÷70) component.</div>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <div class="marks-period-page__submit mt-4 pt-2">
        <button type="submit" class="btn btn-primary btn-lg px-4 px-md-5 shadow-sm">
          <i class="bi bi-check2-circle me-2"></i>
          <?= $mode === 'department' ? 'Continue to department sheet' : 'Confirm period and continue' ?>
        </button>
      </div>

      <aside class="marks-period-page__tip mt-4">
        <span class="marks-period-page__tip-icon" aria-hidden="true"><i class="bi bi-info-circle"></i></span>
        <div class="small">
          Marks saved under the wrong period may not line up with report cards and transcripts.
          If unsure, confirm the active term with your administration before entering grades.
        </div>
      </aside>
    </div>
  </form>
</div>
