<?php
use App\Core\View;

$layout   = 'app';
$title    = 'Import results';
$imported = $imported ?? [];
$errors   = $errors ?? [];
$classId  = (int) ($classId ?? 0);
?>

<div class="page-header mb-4">
  <div>
    <nav aria-label="breadcrumb" class="mb-2">
      <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="<?= $base ?>/students">Students</a></li>
        <li class="breadcrumb-item active" aria-current="page">Import results</li>
      </ol>
    </nav>
    <h2 class="h4 mb-1"><i class="bi bi-file-earmark-check"></i> Import finished</h2>
    <p class="page-header__sub mb-0">
      <?= count($imported) ?> student<?= count($imported) === 1 ? '' : 's' ?> admitted<?php if (!empty($errors)): ?>,
      <?= count($errors) ?> row<?= count($errors) === 1 ? '' : 's' ?> skipped<?php endif; ?>.
    </p>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-lg-9">

    <?php if (!empty($imported)): ?>
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-success-subtle text-success-emphasis fw-semibold">
        <i class="bi bi-check-circle"></i> Admitted (<?= count($imported) ?>)
      </div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>Row</th><th>Name</th><th>Admission no.</th></tr></thead>
          <tbody>
            <?php foreach ($imported as $r): ?>
              <tr>
                <td><?= (int) $r['row'] ?></td>
                <td><?= View::e((string) $r['name']) ?></td>
                <td class="font-monospace"><?= View::e((string) $r['admission_no']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-danger-subtle text-danger-emphasis fw-semibold">
        <i class="bi bi-exclamation-triangle"></i> Skipped (<?= count($errors) ?>)
      </div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>Row</th><th>Name</th><th>Reason</th></tr></thead>
          <tbody>
            <?php foreach ($errors as $e): ?>
              <tr>
                <td><?= $e['row'] !== null ? (int) $e['row'] : '—' ?></td>
                <td><?= View::e((string) ($e['name'] ?? '—')) ?></td>
                <td><?= View::e((string) $e['reason']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <div class="d-flex flex-wrap gap-2">
      <a href="<?= $base ?>/students/import?class_id=<?= $classId ?>" class="btn btn-outline-primary">
        <i class="bi bi-arrow-repeat"></i> Import another file
      </a>
      <a href="<?= $base ?>/students" class="btn btn-primary">
        <i class="bi bi-people"></i> Go to students list
      </a>
    </div>

  </div>
</div>
