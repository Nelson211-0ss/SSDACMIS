<?php
use App\Core\View;
$layout = 'app';
$title  = 'Subjects';

$catLabel = [
    'core'     => 'Compulsory Core',
    'science'  => 'Science (Form 3/4 stream)',
    'arts'     => 'Arts (Form 3/4 stream)',
    'optional' => 'Optional & Additional',
];
$catBadge = [
    'core'     => 'bg-primary-subtle text-primary-emphasis',
    'science'  => 'bg-success-subtle text-success-emphasis',
    'arts'     => 'bg-warning-subtle text-warning-emphasis',
    'optional' => 'bg-secondary-subtle text-secondary-emphasis',
];
$catHint = [
    'core'     => 'Compulsory for every form.',
    'science'  => 'Compulsory for Form 3 & 4 students who chose the Science stream.',
    'arts'     => 'Compulsory for Form 3 & 4 students who chose the Arts stream.',
    'optional' => 'Optional / additional subjects offered alongside the compulsory list.',
];

// Group by category for the curation panel.
$grouped = [];
foreach (['core', 'science', 'arts', 'optional'] as $k) $grouped[$k] = [];
foreach ($subjects as $s) {
    $cat = $s['category'] ?: 'optional';
    if (!isset($grouped[$cat])) $grouped[$cat] = [];
    $grouped[$cat][] = $s;
}

$totalOffered = 0;
foreach ($subjects as $s) if ((int) ($s['is_offered'] ?? 1) === 1) $totalOffered++;
$totalAll = count($subjects);
?>

<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-1"><i class="bi bi-book"></i> Curriculum &amp; Subjects</h4>
    <p class="text-muted small mb-0">
      Curate the subjects this school actually teaches. Anything switched OFF is hidden from
      mark entry, the HOD dashboard and report cards — historic grades remain untouched.
    </p>
  </div>
  <span class="badge bg-info-subtle text-info-emphasis fs-6 align-self-center">
    <i class="bi bi-toggles"></i>
    <?= $totalOffered ?>/<?= $totalAll ?> subjects offered
  </span>
</div>

<?php if ($auth['role'] === 'admin'): ?>
  <div class="row g-3">

    <!-- Curation panel -->
    <div class="col-lg-8">
      <form method="post" action="<?= $base ?>/subjects/offered" class="card entity-form__card border-0 shadow-sm">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
          <strong class="d-flex align-items-center mb-0">
            <span class="card-header-icon card-header-icon--orange me-2" aria-hidden="true"><i class="bi bi-toggles"></i></span>
            Subjects offered at this school
          </strong>
          <div class="d-flex gap-1">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle-all="1">
              <i class="bi bi-check2-all"></i> Select all
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle-all="0">
              <i class="bi bi-x-square"></i> Clear all
            </button>
          </div>
        </div>
        <div class="card-body">
          <?php foreach ($grouped as $cat => $list): ?>
            <?php if (empty($list)) continue; ?>
            <div class="mb-4">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">
                  <span class="badge <?= $catBadge[$cat] ?>"><?= View::e($catLabel[$cat] ?? $cat) ?></span>
                </h6>
                <div class="d-flex gap-1">
                  <button type="button"
                          class="btn btn-sm btn-link text-decoration-none p-0"
                          data-cat-toggle="<?= View::e($cat) ?>"
                          data-cat-state="1">All on</button>
                  <span class="text-muted small">·</span>
                  <button type="button"
                          class="btn btn-sm btn-link text-decoration-none p-0"
                          data-cat-toggle="<?= View::e($cat) ?>"
                          data-cat-state="0">All off</button>
                </div>
              </div>
              <div class="text-muted small mb-2"><?= View::e($catHint[$cat] ?? '') ?></div>
              <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-2">
                <?php foreach ($list as $s):
                  $sid = (int) $s['id'];
                  $on  = (int) ($s['is_offered'] ?? 1) === 1;
                ?>
                  <div class="col">
                    <div class="d-flex align-items-start gap-2 border rounded p-2 m-0 subject-row <?= $on ? 'is-on' : '' ?>">
                      <label class="d-flex align-items-start gap-2 flex-grow-1 m-0" style="cursor:pointer">
                        <input class="form-check-input mt-1 subject-check"
                               type="checkbox"
                               name="offered[]"
                               value="<?= $sid ?>"
                               data-cat="<?= View::e($cat) ?>"
                               <?= $on ? 'checked' : '' ?>>
                        <span class="flex-grow-1">
                          <span class="fw-medium d-block"><?= View::e($s['name']) ?></span>
                          <?php if (!empty($s['code'])): ?>
                            <span class="text-muted small font-monospace"><?= View::e($s['code']) ?></span>
                          <?php endif; ?>
                        </span>
                      </label>
                      <button type="submit"
                              form="del-subject-<?= $sid ?>"
                              class="btn btn-sm btn-link text-danger p-0 align-self-center"
                              title="Permanently delete this subject (rare)">
                        <i class="bi bi-trash"></i>
                      </button>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>

          <?php if (empty($subjects)): ?>
            <div class="text-center text-muted py-4">
              <i class="bi bi-inbox fs-2 d-block mb-2"></i>
              No subjects yet. Use the panel on the right to add the school's first subject.
            </div>
          <?php endif; ?>
        </div>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
          <span class="small text-muted">
            <i class="bi bi-info-circle"></i>
            Disabling is preferred over deleting — you keep grade history but the subject stops appearing on new mark sheets and report cards.
          </span>
          <button class="btn btn-primary"><i class="bi bi-save"></i> Save curriculum</button>
        </div>
      </form>
    </div>

    <!-- Add subject -->
    <div class="col-lg-4">
      <div class="card entity-form__card border-0 shadow-sm mb-3">
        <div class="card-body pb-3">
          <div class="entity-form__col-title mb-3">
            <span class="card-header-icon card-header-icon--green" style="width: 1.35rem; height: 1.35rem; font-size: 0.75rem;"><i class="bi bi-plus-square"></i></span>
            Add subject
          </div>
          <form method="post" action="<?= $base ?>/subjects">
            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
            <div class="mb-2">
              <label class="form-label small fw-semibold mb-1">Name <span class="text-danger">*</span></label>
              <input name="name" class="form-control form-control-sm shadow-sm" required placeholder="e.g. Mathematics">
            </div>
            <div class="mb-2">
              <label class="form-label small fw-semibold mb-1">Code</label>
              <input name="code" class="form-control form-control-sm shadow-sm" placeholder="e.g. MATH">
            </div>
            <div class="mb-2">
              <label class="form-label small fw-semibold mb-1">Category</label>
              <select name="category" class="form-select form-select-sm shadow-sm">
                <option value="core">Compulsory Core</option>
                <option value="science">Science (Form 3/4 stream)</option>
                <option value="arts">Arts (Form 3/4 stream)</option>
                <option value="optional" selected>Optional &amp; Additional</option>
              </select>
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="newOffered" name="is_offered" value="1" checked>
              <label class="form-check-label small" for="newOffered">Offer immediately</label>
            </div>
            <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-plus-lg me-1"></i>Add subject</button>
          </form>
        </div>
      </div>

      <div class="entity-form__panel small text-muted mb-0">
        <strong class="text-body d-block mb-1"><i class="bi bi-lightbulb"></i> Tip</strong>
        Curate subjects here, assign teachers under <a href="<?= $base ?>/teaching">Teaching</a>, then enter marks under <a href="<?= $base ?>/marks">Marks</a>.
      </div>
    </div>
  </div>

  <!-- Stand-alone delete forms (out-of-DOM submitters via form="…") -->
  <?php foreach ($subjects as $s):
    $sid = (int) $s['id'];
  ?>
    <form id="del-subject-<?= $sid ?>"
          method="post"
          action="<?= $base ?>/subjects/<?= $sid ?>/delete"
          data-confirm="Permanently delete '<?= View::e($s['name']) ?>'? This also removes all of its grade history."
          class="d-none">
      <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    </form>
  <?php endforeach; ?>

  <script>
  (function () {
    function refresh(label) {
      var input = label.querySelector('.subject-check');
      label.classList.toggle('is-on', !!(input && input.checked));
    }
    document.querySelectorAll('.subject-row').forEach(refresh);

    document.querySelectorAll('.subject-check').forEach(function (cb) {
      cb.addEventListener('change', function () { refresh(cb.closest('.subject-row')); });
    });

    document.querySelectorAll('[data-toggle-all]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var on = btn.getAttribute('data-toggle-all') === '1';
        document.querySelectorAll('.subject-check').forEach(function (cb) {
          cb.checked = on;
          refresh(cb.closest('.subject-row'));
        });
      });
    });

    document.querySelectorAll('[data-cat-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var cat = btn.getAttribute('data-cat-toggle');
        var on  = btn.getAttribute('data-cat-state') === '1';
        document.querySelectorAll('.subject-check[data-cat="' + cat + '"]').forEach(function (cb) {
          cb.checked = on;
          refresh(cb.closest('.subject-row'));
        });
      });
    });
  })();
  </script>
  <style>
    .subject-row { cursor: pointer; transition: background-color .12s ease, border-color .12s ease; }
    .subject-row.is-on { background: rgba(var(--accent-rgb), .06); border-color: var(--accent); }
    .subject-row:hover { background: rgba(0,0,0,.02); }
  </style>

<?php else: ?>
  <!-- Read-only view for non-admins -->
  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th>Name</th><th>Code</th><th>Category</th><th>Offered?</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($subjects)): ?>
          <tr><td colspan="4" class="text-center text-muted py-4">No subjects yet.</td></tr>
        <?php else: foreach ($subjects as $s):
          $cat = $s['category'] ?? 'optional';
          $on  = (int) ($s['is_offered'] ?? 1) === 1;
        ?>
          <tr class="<?= $on ? '' : 'text-muted' ?>">
            <td><?= View::e($s['name']) ?></td>
            <td><?= View::e($s['code'] ?: '—') ?></td>
            <td>
              <span class="badge <?= $catBadge[$cat] ?? 'bg-secondary' ?>">
                <?= View::e($catLabel[$cat] ?? ucfirst($cat)) ?>
              </span>
            </td>
            <td>
              <?php if ($on): ?>
                <span class="badge bg-success-subtle text-success-emphasis">
                  <i class="bi bi-check-circle"></i> Offered
                </span>
              <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary-emphasis">
                  <i class="bi bi-slash-circle"></i> Not offered
                </span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
