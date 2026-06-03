<?php
use App\Core\View;
$layout = 'app';
$title = 'Staff';
$catBadge = ['core'=>'bg-primary-subtle text-primary-emphasis','science'=>'bg-success-subtle text-success-emphasis','arts'=>'bg-warning-subtle text-warning-emphasis','optional'=>'bg-secondary-subtle text-secondary-emphasis'];
$catLabel = ['core'=>'Core','science'=>'Science','arts'=>'Arts','optional'=>'Optional'];

$pageTitle = 'Staff';
$pageSubtitle = 'Teachers, roles, subjects, and department heads.';
$pageIcon = 'bi-person-badge';
$pageActionsHtml = '<a class="btn btn-primary" href="' . View::e($base) . '/staff/create"><i class="bi bi-plus-lg"></i> Add staff</a>';
include dirname(__DIR__) . '/_partials/app_page_header.php';
?>

<div class="app-panel">
  <div class="sa-table-wrap">
    <table class="table table-hover sa-table align-middle mb-0">
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Subjects</th>
          <th>Position</th>
          <th>Phone</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($staff)): ?>
        <tr><td colspan="7" class="sa-empty">No staff yet. Add your first staff member to get started.</td></tr>
      <?php else: foreach ($staff as $s):
        $hodCats = array_filter(array_map('trim', explode(',', (string) ($s['hod_categories'] ?? ''))));
        $subjects = [];
        if (!empty($s['subjects_csv'])) {
          foreach (explode(';;', $s['subjects_csv']) as $token) {
            [$id, $name, $cat] = array_pad(explode('|', $token, 3), 3, '');
            $subjects[] = ['name' => $name, 'category' => $cat];
          }
        }
      ?>
        <tr>
          <td>
            <div class="fw-semibold"><?= View::e($s['first_name'].' '.$s['last_name']) ?></div>
            <?php if ($hodCats): ?>
              <?php foreach ($hodCats as $cat): ?>
                <span class="badge mt-1 <?= $catBadge[$cat] ?? 'bg-secondary' ?>" title="Head of Department">
                  <i class="bi bi-mortarboard"></i> HOD · <?= View::e($catLabel[$cat] ?? ucfirst($cat)) ?>
                </span>
              <?php endforeach; ?>
            <?php endif; ?>
          </td>
          <td><?= View::e($s['email'] ?? '—') ?></td>
          <td><span class="badge bg-info text-uppercase"><?= View::e($s['role'] ?? '—') ?></span></td>
          <td>
            <?php if (empty($subjects)): ?>
              <span class="text-muted small">—</span>
            <?php else: ?>
              <div class="d-flex flex-wrap gap-1">
                <?php foreach ($subjects as $sub): ?>
                  <span class="badge text-capitalize <?= $catBadge[$sub['category']] ?? 'bg-light text-dark border' ?>">
                    <?= View::e($sub['name']) ?>
                  </span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </td>
          <td><?= View::e($s['position'] ?: '—') ?></td>
          <td><?= View::e($s['phone'] ?: '—') ?></td>
          <td class="text-end text-nowrap">
            <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/staff/<?= (int)$s['id'] ?>/edit"><i class="bi bi-pencil"></i></a>
            <form class="d-inline" method="post" action="<?= $base ?>/staff/<?= (int)$s['id'] ?>/delete" data-confirm="Delete this staff member and their login?">
              <input type="hidden" name="_csrf" value="<?= $csrf ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
