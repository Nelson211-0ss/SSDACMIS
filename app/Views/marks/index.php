<?php
use App\Core\View;
$layout = 'app';
$title = 'Marks';
$catLabel = ['core'=>'Compulsory Core','science'=>'Science','arts'=>'Arts','optional'=>'Optional'];
$catShort = ['core'=>'Core','science'=>'Science','arts'=>'Arts','optional'=>'Optional'];
$catBadge = ['core'=>'bg-primary-subtle text-primary-emphasis','science'=>'bg-success-subtle text-success-emphasis','arts'=>'bg-warning-subtle text-warning-emphasis','optional'=>'bg-secondary-subtle text-secondary-emphasis'];
$periodQs = '&year=' . rawurlencode($year) . '&term=' . rawurlencode($term) . '&exam_type=' . rawurlencode($examType);

$classesByForm     = $classesByForm     ?? ['Form 1' => [], 'Form 2' => [], 'Form 3' => [], 'Form 4' => []];
$classDeptCats     = $classDeptCats     ?? [];
$hodMarkCategories = $hodMarkCategories ?? [];
$hodMarkSubjects   = $hodMarkSubjects   ?? [];
$subjectsByCategory = [];
foreach ($hodMarkSubjects as $_sub) {
    $ck = (string) ($_sub['category'] ?? 'optional');
    $subjectsByCategory[$ck][] = $_sub;
}
$formLabels = ['Form 1' => 'Form 1', 'Form 2' => 'Form 2', 'Form 3' => 'Form 3', 'Form 4' => 'Form 4'];
$formBadgeTones = ['Form 1' => 'orange', 'Form 2' => 'green', 'Form 3' => 'blue', 'Form 4' => 'purple'];
$isHodPortal = ($portalPrefix ?? '') === '/hod';
$examLabel = View::e($exams[$examType] ?? ucfirst((string) $examType));
?>
<div class="marks-page">
  <!-- Hero -->
  <section class="marks-page__hero dash-hero dash-hero--slim mb-3">
    <div class="marks-page__hero-row">
      <div class="dash-hero__content d-flex align-items-center gap-3 flex-grow-1" style="min-width:0;">
        <span class="icon-chip icon-chip--teal d-none d-sm-inline-grid" aria-hidden="true">
          <i class="bi bi-pencil-square"></i>
        </span>
        <div>
          <h2 class="dash-hero__title mb-1">
            <?= $isHodPortal ? 'Department marks' : 'Marks entry' ?>
          </h2>
          <p class="dash-hero__sub mb-0">
            <?= $isHodPortal
              ? 'Enter marks by class matrix or single subject for every group you oversee.'
              : 'Choose class × subject tiles or use the department grid when available.' ?>
          </p>
        </div>
      </div>
      <div class="marks-page__hero-tail d-flex flex-wrap align-items-center gap-2">
        <div class="marks-page__period-chip" title="Active marking period">
          <i class="bi bi-calendar2-check"></i>
          <span>
            <strong><?= View::e($year) ?></strong>
            · <?= View::e($term) ?>
            · <?= $examLabel ?>
          </span>
        </div>
        <?php if ($isAdmin): ?>
          <a class="btn btn-outline-secondary btn-sm shadow-sm" href="<?= $base ?>/teaching">
            <i class="bi bi-diagram-3"></i> Assignments
          </a>
        <?php endif; ?>
        <a class="btn btn-outline-secondary btn-sm shadow-sm"
           href="<?= $base ?><?= $portalPrefix ?>/marks"
           title="Pick a different year, term, or exam">
          <i class="bi bi-arrow-repeat"></i> Change period
        </a>
      </div>
    </div>
  </section>

  <!-- Period / exam filter -->
  <form method="get" action="<?= $base ?><?= $portalPrefix ?>/marks" class="marks-page__filters card border-0 shadow-sm mb-4">
    <div class="card-body">
      <div class="marks-page__filters-head mb-3">
        <span class="marks-page__filters-icon" aria-hidden="true"><i class="bi bi-sliders"></i></span>
        <div>
          <div class="marks-page__filters-title">Marking period</div>
          <p class="marks-page__filters-hint mb-0 text-muted small">
            All links below record grades against this academic year, term, and exam component.
          </p>
        </div>
      </div>
      <div class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label">Academic year <span class="text-danger">*</span></label>
          <select name="year" class="form-select shadow-sm" required>
            <?php foreach ($years as $y): ?>
              <option value="<?= View::e($y) ?>" <?= $y === $year ? 'selected' : '' ?>><?= View::e($y) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Term <span class="text-danger">*</span></label>
          <select name="term" class="form-select shadow-sm" required>
            <?php foreach ($terms as $t): ?>
              <option <?= $t === $term ? 'selected' : '' ?>><?= View::e($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Exam component</label>
          <select name="exam_type" class="form-select shadow-sm">
            <?php foreach ($exams as $k => $label): ?>
              <option value="<?= View::e($k) ?>" <?= $k === $examType ? 'selected' : '' ?>><?= View::e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <button type="submit" class="btn btn-primary w-100 shadow-sm">
            <i class="bi bi-check2-circle me-1"></i> Apply
          </button>
        </div>
      </div>
    </div>
  </form>

<?php if (!empty($departments)): ?>
  <section class="marks-page__section mb-4" id="marks-department-grid">
    <div class="marks-page__section-intro card border-0 shadow-sm mb-3 overflow-hidden">
      <div class="marks-page__section-intro-inner">
        <span class="marks-page__section-intro-icon" aria-hidden="true"><i class="bi bi-ui-checks-grid"></i></span>
        <div>
          <h3 class="marks-page__section-title mb-1">Department mark entry</h3>
          <p class="marks-page__section-sub mb-0 text-muted">
            One column per form. Under each class: matrix shortcuts by category, then subjects grouped by category.
          </p>
        </div>
      </div>
    </div>

    <div class="row row-cols-1 row-cols-lg-2 row-cols-xl-4 g-3 hod-mark-entry-grid">
      <?php foreach ($formLabels as $formKey => $formTitle):
        $rows = $classesByForm[$formKey] ?? [];
        $totalFormStudents = 0;
        foreach ($rows as $rc) {
            $totalFormStudents += (int) $rc['student_count'];
        }
        $badgeTone = $formBadgeTones[$formKey] ?? 'purple';
        $formSlug = 'marks-form-' . preg_replace('/\s+/', '-', strtolower($formKey));
      ?>
        <div class="col d-flex">
          <div class="form-card hod-mark-column w-100" id="<?= View::e($formSlug) ?>">
            <div class="form-card__head">
              <span class="form-card__badge form-card__badge--<?= View::e($badgeTone) ?>"><?= View::e($formTitle) ?></span>
              <?php if (empty($rows)): ?>
                <span class="form-card__meta form-card__meta--warn">
                  <i class="bi bi-exclamation-circle"></i> No class
                </span>
              <?php else: ?>
                <span class="form-card__meta">
                  <i class="bi bi-people"></i>
                  <?= number_format($totalFormStudents) ?>
                </span>
              <?php endif; ?>
            </div>

            <div class="form-card__body hod-mark-column__body">
              <?php if (empty($rows)): ?>
                <div class="form-card__empty">
                  No class at <?= View::e($formKey) ?> with department mark entry for your access.
                  <?php if ($isAdmin): ?>
                    Add or adjust classes under <a href="<?= $base ?>/classes">Classes</a>.
                  <?php endif; ?>
                </div>
              <?php else: foreach ($rows as $c):
                $reportHref = $base . $portalPrefix . '/reports/class/' . (int) $c['id']
                    . '?year=' . rawurlencode($year) . '&term=' . rawurlencode($term);
                $cid = (int) $c['id'];
                $classHasSubjectsBelow = false;
                foreach ($hodMarkCategories as $_cat) {
                    $catSubs = $subjectsByCategory[$_cat] ?? [];
                    if ($catSubs !== [] && !empty($classDeptCats[$cid][$_cat])) {
                        $classHasSubjectsBelow = true;
                        break;
                    }
                }
              ?>
                <div class="form-card__class hod-mark-class">
                  <div class="form-card__class-head<?= $classHasSubjectsBelow ? ' form-card__class-head--theme' : '' ?>">
                    <div class="form-card__class-title">
                      <span class="form-card__class-name"><?= View::e($c['name']) ?></span>
                      <span class="badge-soft"><?= (int) $c['student_count'] ?></span>
                    </div>
                    <a class="form-card__icon-link"
                       href="<?= View::e($reportHref) ?>"
                       title="Class report">
                      <i class="bi bi-printer"></i>
                    </a>
                  </div>

                  <div class="hod-mark-class__section">
                    <div class="hod-mark-class__label">Matrix (whole category)</div>
                    <div class="subject-chips subject-chips--cats hod-mark-class__chips">
                      <?php foreach ($hodMarkCategories as $cat):
                          if (empty($classDeptCats[(int) $c['id']][$cat])) {
                              continue;
                          }
                          $href = $base . $portalPrefix . '/marks/department?class_id=' . (int) $c['id']
                              . '&category=' . rawurlencode($cat) . $periodQs;
                      ?>
                        <a class="subject-chip subject-chip--<?= View::e($cat) ?> subject-chip--solid"
                           href="<?= View::e($href) ?>">
                          <?= View::e($catShort[$cat] ?? $cat) ?>
                        </a>
                      <?php endforeach; ?>
                    </div>
                  </div>

                  <?php foreach ($hodMarkCategories as $cat):
                      $catSubs = $subjectsByCategory[$cat] ?? [];
                      if (empty($catSubs) || empty($classDeptCats[(int) $c['id']][$cat])) {
                          continue;
                      }
                  ?>
                    <div class="hod-mark-class__section">
                      <div class="hod-mark-class__label"><?= View::e($catLabel[$cat] ?? ucfirst($cat)) ?></div>
                      <div class="subject-chips hod-mark-class__chips">
                        <?php foreach ($catSubs as $sub):
                            $href = $base . $portalPrefix . '/marks/entry?class_id=' . (int) $c['id']
                                . '&subject_id=' . (int) $sub['id'] . $periodQs;
                            $subCat = (string) ($sub['category'] ?? 'optional');
                        ?>
                          <a class="subject-chip subject-chip--<?= View::e($subCat) ?>"
                             href="<?= View::e($href) ?>"
                             title="Enter <?= View::e($sub['name']) ?> marks">
                            <?= View::e($sub['name']) ?>
                          </a>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endforeach; endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php if (!empty($assignments)): ?>
  <section class="marks-page__assignments mb-2">
    <div class="marks-page__assignments-head card border-0 shadow-sm mb-3 overflow-hidden">
      <div class="marks-page__assignments-head-inner">
        <span class="marks-page__assignments-head-icon" aria-hidden="true"><i class="bi bi-person-vcard"></i></span>
        <div>
          <h3 class="marks-page__assignments-title mb-1">Per-subject assignments</h3>
          <p class="marks-page__assignments-sub mb-0 text-muted small">
            <?php if (!empty($departments)): ?>
              Individual class × subject shortcuts — alongside the department grid above.
            <?php else: ?>
              Open a class and subject pair to enter marks on the bulk sheet.
            <?php endif; ?>
          </p>
        </div>
      </div>
    </div>
    <div class="row g-3">
      <?php foreach ($assignments as $a):
        $url = $base . $portalPrefix . '/marks/entry?class_id=' . (int) $a['class_id']
             . '&subject_id=' . (int) $a['subject_id'] . $periodQs;
      ?>
        <div class="col-sm-6 col-lg-4">
          <a class="marks-assignment-tile card border-0 shadow-sm h-100 text-decoration-none text-reset"
             href="<?= View::e($url) ?>">
            <div class="card-body">
              <div class="marks-assignment-tile__class text-muted small text-uppercase fw-semibold mb-1">
                <?= View::e($a['class_name']) ?>
              </div>
              <div class="marks-assignment-tile__subject h5 mb-2"><?= View::e($a['subject_name']) ?></div>
              <div class="small text-muted">
                <span class="badge text-capitalize <?= $catBadge[$a['category']] ?? 'bg-light text-dark border' ?>"><?= View::e($a['category']) ?></span>
                <span class="ms-2"><i class="bi bi-people"></i> <?= (int) $a['student_count'] ?> students</span>
                <?php if ($isAdmin && !empty($a['first_name'])): ?>
                  <div class="mt-2 pt-2 border-top border-light-subtle">
                    <i class="bi bi-person"></i>
                    <?= View::e(trim($a['first_name'] . ' ' . $a['last_name'])) ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
            <div class="marks-assignment-tile__footer card-footer border-0 pt-0">
              <span class="marks-assignment-tile__cta">
                Enter marks <i class="bi bi-arrow-right ms-1"></i>
              </span>
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php if (empty($assignments) && empty($departments)): ?>
  <div class="card border-0 shadow-sm marks-page__empty">
    <div class="card-body text-center py-5 px-3">
      <div class="marks-page__empty-icon mx-auto mb-3" aria-hidden="true"><i class="bi bi-inbox"></i></div>
      <p class="text-muted mb-0">
        <?php if ($isAdmin): ?>
          No teaching assignments or department heads yet.
          <a href="<?= $base ?>/teaching">Set up assignments</a>.
        <?php else: ?>
          You haven’t been assigned any classes, subjects, or departments yet.<br>
          Ask an administrator to set this up.
        <?php endif; ?>
      </p>
    </div>
  </div>
<?php endif; ?>
</div>
