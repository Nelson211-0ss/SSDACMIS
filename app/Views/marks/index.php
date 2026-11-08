<?php
use App\Core\View;
$layout = 'app';
$title = 'Marks';

/*
 * Marks entry picker. Built to fit one screen on a laptop/desktop: a single
 * header row (title + period), then the four forms side by side, each class
 * a flat card (hairline border, no shadow) holding a few dense lines of
 * subject links. No hero, no gradients.
 * If a school has more classes than fit, only the form column scrolls —
 * never the page (see .marks-board in app.css).
 */

$catShort = ['core' => 'Core', 'science' => 'Science', 'arts' => 'Arts', 'optional' => 'Optional'];
$periodQs = '&year=' . rawurlencode($year) . '&term=' . rawurlencode($term) . '&exam_type=' . rawurlencode($examType);

$classesByForm     = $classesByForm     ?? ['Form 1' => [], 'Form 2' => [], 'Form 3' => [], 'Form 4' => []];
$classDeptCats     = $classDeptCats     ?? [];
$hodMarkCategories = $hodMarkCategories ?? [];
$hodMarkSubjects   = $hodMarkSubjects   ?? [];
$assignments       = $assignments       ?? [];

$subjectsByCategory = [];
foreach ($hodMarkSubjects as $sub) {
    $sid = (int) ($sub['id'] ?? 0);
    if ($sid > 0) {
        $subjectsByCategory[(string) ($sub['category'] ?? 'optional')][$sid] = $sub;
    }
}

/** Short label for a subject link: its code when it has one, else its name. */
$subLabel = static function (?string $code, string $name): string {
    $code = trim((string) $code);
    return $code !== '' ? $code : $name;
};

// Per-subject assignments, grouped by class: one line per class.
$assignByClass = [];
foreach ($assignments as $a) {
    $cid = (int) $a['class_id'];
    if (!isset($assignByClass[$cid])) {
        $assignByClass[$cid] = ['name' => $a['class_name'], 'count' => (int) $a['student_count'], 'subjects' => []];
    }
    $assignByClass[$cid]['subjects'][] = $a;
}

$isHodPortal = ($portalPrefix ?? '') === '/hod';
$hasDept = !empty($departments);
?>
<div class="marks-board<?= $hasDept && $assignByClass ? ' marks-board--both' : '' ?>">

  <div class="marks-board__bar">
    <h2 class="marks-board__title">
      <i class="bi bi-pencil-square" aria-hidden="true"></i>
      <?= $isHodPortal ? 'Department marks' : 'Marks entry' ?>
    </h2>

    <form method="get" data-auto-reload action="<?= $base ?><?= $portalPrefix ?>/marks" class="marks-board__period"
          aria-label="Marking period">
      <label class="visually-hidden" for="mbYear">Academic year</label>
      <select name="year" id="mbYear" class="form-select form-select-sm" required>
        <?php foreach ($years as $y): ?>
          <option value="<?= View::e($y) ?>" <?= $y === $year ? 'selected' : '' ?>><?= View::e($y) ?></option>
        <?php endforeach; ?>
      </select>
      <label class="visually-hidden" for="mbTerm">Term</label>
      <select name="term" id="mbTerm" class="form-select form-select-sm" required>
        <?php foreach ($terms as $t): ?>
          <option <?= $t === $term ? 'selected' : '' ?>><?= View::e($t) ?></option>
        <?php endforeach; ?>
      </select>
      <label class="visually-hidden" for="mbExam">Exam</label>
      <select name="exam_type" id="mbExam" class="form-select form-select-sm">
        <?php foreach ($exams as $k => $label): ?>
          <option value="<?= View::e($k) ?>" <?= $k === $examType ? 'selected' : '' ?>><?= View::e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <noscript><button type="submit" class="btn btn-sm btn-primary">Apply</button></noscript>
    </form>

    <?php if ($isAdmin): ?>
      <a class="btn btn-sm btn-outline-secondary ms-auto" href="<?= $base ?>/teaching">
        <i class="bi bi-diagram-3"></i> Assignments
      </a>
    <?php endif; ?>
  </div>

  <?php if ($hasDept || $assignByClass): ?>
    <p class="marks-board__hint">
      Marks are saved for <strong><?= View::e($year) ?> · <?= View::e($term) ?> · <?= View::e($exams[$examType] ?? $examType) ?></strong>.
      Click a subject to enter its marks<?= $hasDept ? ', or a category button (<i class="bi bi-grid-3x3-gap"></i>) to enter all its subjects on one sheet' : '' ?>.
    </p>
  <?php endif; ?>

  <?php if ($hasDept): ?>
    <div class="marks-board__forms">
      <?php foreach ($classesByForm as $formKey => $rows):
        $formStudents = array_sum(array_map(static fn ($r) => (int) $r['student_count'], $rows));
      ?>
        <section class="marks-form" aria-label="<?= View::e($formKey) ?>">
          <h3 class="marks-form__head">
            <?= View::e($formKey) ?>
            <span class="marks-form__meta">
              <?= $rows ? count($rows) . ' class' . (count($rows) === 1 ? '' : 'es') . ' · ' . number_format($formStudents) . ' students' : 'No class' ?>
            </span>
          </h3>
          <div class="marks-form__body">
            <?php if (!$rows): ?>
              <p class="marks-form__empty">
                No <?= View::e($formKey) ?> class for your access.
                <?php if ($isAdmin): ?><a href="<?= $base ?>/classes">Classes</a><?php endif; ?>
              </p>
            <?php endif; ?>

            <?php foreach ($rows as $c):
              $cid = (int) $c['id'];
              $reportHref = $base . $portalPrefix . '/reports/class/' . $cid
                  . '?year=' . rawurlencode($year) . '&term=' . rawurlencode($term);
            ?>
              <div class="marks-class">
                <div class="marks-class__head">
                  <span class="marks-class__name"><?= View::e($c['name']) ?></span>
                  <span class="marks-class__count" title="Students"><?= (int) $c['student_count'] ?></span>
                  <a class="marks-class__report" href="<?= View::e($reportHref) ?>" title="<?= View::e($c['name']) ?> class report">
                    <i class="bi bi-printer"></i><span class="visually-hidden">Class report</span>
                  </a>
                </div>
                <?php foreach ($hodMarkCategories as $cat):
                  if (empty($classDeptCats[$cid][$cat])) {
                      continue;
                  }
                  $matrixHref = $base . $portalPrefix . '/marks/department?class_id=' . $cid
                      . '&category=' . rawurlencode($cat) . $periodQs;
                ?>
                  <div class="marks-class__row">
                    <a class="marks-cat marks-cat--<?= View::e($cat) ?>" href="<?= View::e($matrixHref) ?>"
                       title="All <?= View::e(strtolower($catShort[$cat] ?? $cat)) ?> subjects for <?= View::e($c['name']) ?> on one sheet">
                      <i class="bi bi-grid-3x3-gap" aria-hidden="true"></i> <?= View::e($catShort[$cat] ?? ucfirst($cat)) ?>
                    </a>
                    <?php foreach (($subjectsByCategory[$cat] ?? []) as $sub):
                      $href = $base . $portalPrefix . '/marks/entry?class_id=' . $cid
                          . '&subject_id=' . (int) $sub['id'] . $periodQs;
                    ?>
                      <a class="marks-sub" href="<?= View::e($href) ?>" title="<?= View::e($sub['name']) ?> — <?= View::e($c['name']) ?>">
                        <?= View::e($subLabel($sub['code'] ?? null, (string) $sub['name'])) ?>
                      </a>
                    <?php endforeach; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($assignByClass): ?>
    <section class="marks-board__assign" aria-labelledby="mbAssignHead">
      <h3 class="marks-board__section-head" id="mbAssignHead">
        <?= $hasDept ? 'Your subject assignments' : 'Your classes and subjects' ?>
      </h3>
      <div class="marks-assign">
        <?php foreach ($assignByClass as $cid => $grp): ?>
          <div class="marks-class marks-assign__card">
            <div class="marks-class__head">
              <span class="marks-class__name"><?= View::e($grp['name']) ?></span>
              <span class="marks-class__count" title="Students"><?= (int) $grp['count'] ?></span>
            </div>
            <div class="marks-class__row">
            <?php foreach ($grp['subjects'] as $a):
              $url = $base . $portalPrefix . '/marks/entry?class_id=' . (int) $a['class_id']
                   . '&subject_id=' . (int) $a['subject_id'] . $periodQs;
              $teacher = $isAdmin && !empty($a['first_name']) ? ' · ' . trim($a['first_name'] . ' ' . $a['last_name']) : '';
            ?>
              <a class="marks-sub marks-sub--<?= View::e((string) $a['category']) ?>" href="<?= View::e($url) ?>"
                 title="<?= View::e($a['subject_name'] . $teacher) ?>">
                <?= View::e($subLabel($a['subject_code'] ?? null, (string) $a['subject_name'])) ?>
              </a>
            <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if (!$hasDept && !$assignByClass): ?>
    <p class="marks-board__empty">
      <i class="bi bi-inbox" aria-hidden="true"></i>
      <?php if ($isAdmin): ?>
        No teaching assignments or department heads yet. <a href="<?= $base ?>/teaching">Set up assignments</a>.
      <?php else: ?>
        You haven’t been assigned any classes, subjects or departments yet. Ask an administrator to set this up.
      <?php endif; ?>
    </p>
  <?php endif; ?>
</div>
