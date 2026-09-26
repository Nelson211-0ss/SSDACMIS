<?php
use App\Core\View;

$layout = 'app';
$title  = 'Download students';

$isAdmin          = !empty($isAdmin);
$classes          = $classes ?? [];
$schools          = $schools ?? [];
$selectedSchoolId = $selectedSchoolId ?? null;
$selectedClass    = $selectedClass ?? null;
$classId          = (int) ($classId ?? 0);
$stream           = $stream ?? 'all';
$isUpperLevel     = !empty($isUpperLevel);
$effectiveStream  = $effectiveStream ?? 'all';
$studentCount     = (int) ($studentCount ?? 0);
$columns          = $columns ?? [];

$hasSchoolPicker = $isAdmin && !empty($schools);
$streamLocked    = ($classId > 0 && !$isUpperLevel);

$className  = $selectedClass ? mb_strtoupper((string) $selectedClass['name'], 'UTF-8') : 'All classes';
$streamName = $effectiveStream === 'all' ? '' : ucfirst($effectiveStream);

/** Filters carried onto the download link. */
$qs = http_build_query(array_filter([
    'school_id' => $selectedSchoolId,
    'class_id'  => $classId ?: null,
    'stream'    => $effectiveStream !== 'all' ? $effectiveStream : null,
], static fn ($v) => $v !== null && $v !== ''));

$fileName = 'students-'
    . ($selectedClass ? strtolower(trim(preg_replace('~[^a-z0-9]+~i', '-', (string) $selectedClass['name']), '-')) : 'all-classes')
    . ($effectiveStream !== 'all' ? '-' . $effectiveStream : '')
    . '-' . date('Y-m-d') . '.csv';
?>

<div class="export-page">

  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0 small">
      <li class="breadcrumb-item"><a href="<?= $base ?>/students">Students</a></li>
      <li class="breadcrumb-item active" aria-current="page">Download</li>
    </ol>
  </nav>

  <div class="export-card">

    <!-- Header -->
    <header class="export-card__head">
      <span class="export-card__icon" aria-hidden="true"><i class="bi bi-download"></i></span>
      <div>
        <h1 class="export-card__title">Download students</h1>
        <p class="export-card__sub">Pick a class and stream, then download the list as a spreadsheet.</p>
      </div>
    </header>

    <!-- Filters -->
    <form method="get" action="<?= $base ?>/students/download" class="export-card__body" id="exportForm">
      <div class="export-fields">

        <?php if ($hasSchoolPicker): ?>
          <div class="export-field">
            <label class="export-label" for="dlSchool">School</label>
            <select name="school_id" id="dlSchool" class="form-select" onchange="this.form.submit()">
              <option value="">All schools</option>
              <?php foreach ($schools as $sch): ?>
                <option value="<?= (int) $sch['id'] ?>" <?= $selectedSchoolId === (int) $sch['id'] ? 'selected' : '' ?>>
                  <?= View::e((string) $sch['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <div class="export-field">
          <label class="export-label" for="dlClass">Class</label>
          <select name="class_id" id="dlClass" class="form-select" onchange="this.form.submit()">
            <option value="">All classes</option>
            <?php foreach ($classes as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= $classId === (int) $c['id'] ? 'selected' : '' ?>>
                <?= View::e(mb_strtoupper((string) ($c['name'] ?? ''), 'UTF-8')) ?><?= !empty($c['level']) ? ' · ' . View::e((string) $c['level']) : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="export-field">
          <span class="export-label" id="dlStreamLabel">Stream</span>
          <div class="export-segmented <?= $streamLocked ? 'is-disabled' : '' ?>"
               role="group" aria-labelledby="dlStreamLabel">
            <?php foreach (['all' => 'All', 'science' => 'Science', 'arts' => 'Arts'] as $key => $label): ?>
              <input type="radio" name="stream" value="<?= $key ?>" id="dlStream_<?= $key ?>"
                     <?= $stream === $key ? 'checked' : '' ?>
                     <?= $streamLocked ? 'disabled' : '' ?>
                     onchange="this.form.submit()">
              <label for="dlStream_<?= $key ?>"><?= $label ?></label>
            <?php endforeach; ?>
          </div>
          <p class="export-hint">
            <?php if ($streamLocked): ?>
              <?= View::e((string) ($selectedClass['level'] ?? 'This class')) ?> isn’t streamed.
            <?php else: ?>
              Form 3 &amp; Form 4 only.
            <?php endif; ?>
          </p>
        </div>

      </div>
      <noscript>
        <button type="submit" class="btn btn-outline-secondary btn-sm mt-3">Apply filters</button>
      </noscript>
    </form>

    <!-- Result + action -->
    <div class="export-card__result <?= $studentCount === 0 ? 'is-empty' : '' ?>">
      <div class="export-result__meta">
        <div class="export-result__count"><?= number_format($studentCount) ?></div>
        <div class="export-result__scope">
          <span class="export-result__noun">student<?= $studentCount === 1 ? '' : 's' ?> in</span>
          <span class="export-result__class"><?= View::e($className) ?></span>
          <?php if ($streamName !== ''): ?>
            <span class="export-chip"><?= View::e($streamName) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($studentCount === 0): ?>
        <p class="export-result__empty mb-0">
          <i class="bi bi-info-circle"></i>
          Nothing to download<?= $effectiveStream !== 'all' ? ' — try ' . '<strong>All</strong> streams' : '' ?>.
        </p>
      <?php else: ?>
        <a class="btn btn-primary export-btn"
           href="<?= $base ?>/students/download.csv<?= $qs !== '' ? '?' . View::e($qs) : '' ?>">
          <i class="bi bi-file-earmark-arrow-down"></i>
          <span>Download CSV</span>
        </a>
      <?php endif; ?>
    </div>

    <!-- Quiet footnote -->
    <?php if ($studentCount > 0): ?>
      <footer class="export-card__foot">
        <details>
          <summary>
            <code><?= View::e($fileName) ?></code>
            <span class="export-foot__more">What’s inside?</span>
          </summary>
          <div class="export-cols">
            <?php foreach ($columns as $col): ?>
              <span class="export-col"><?= View::e($col) ?></span>
            <?php endforeach; ?>
          </div>
          <p class="export-hint mb-0">
            Column names match the <a href="<?= $base ?>/students/import">import template</a>,
            so an edited file can be brought back in.
          </p>
        </details>
      </footer>
    <?php endif; ?>

  </div>
</div>
