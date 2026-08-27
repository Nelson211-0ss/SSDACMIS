<?php
use App\Core\View;
$layout = 'app';
$title  = 'Parents';

$pageTitle = 'Parents';
$pageSubtitle = 'Parent accounts sign in at <code class="sa-code">' . View::e($base) . '/parent/login</code> with their primary child\'s admission number, and see only their linked child(ren) after sign-in.';
$pageIcon = 'bi-person-hearts';
$pageActionsHtml = '<a class="btn btn-primary" href="' . View::e($base) . '/parents/create"><i class="bi bi-plus-lg"></i> Add parent</a>';
include dirname(__DIR__) . '/_partials/app_page_header.php';
?>

<div class="app-panel">
  <div class="sa-table-wrap">
    <table class="table table-hover sa-table align-middle mb-0">
      <thead>
        <tr>
          <th>Name</th>
          <th>Sign-in (admission no.)</th>
          <th>Linked children</th>
          <th>Status</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($parents)): ?>
          <tr>
            <td colspan="5" class="sa-empty">No parent accounts yet. Click <strong>Add parent</strong> to create one.</td>
          </tr>
        <?php else: foreach ($parents as $p): ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= View::e($p['name']) ?></div>
              <span class="badge bg-info-subtle text-info-emphasis mt-1">
                <i class="bi bi-person-hearts"></i> Parent
              </span>
            </td>
            <td>
              <?php if (!empty($p['login_admission_no'])): ?>
                <code class="small"><?= View::e($p['login_admission_no']) ?></code>
              <?php else: ?>
                <span class="text-danger small fst-italic">No sign-in child set</span>
              <?php endif; ?>
              <div class="text-muted small"><?= View::e($p['email']) ?></div>
            </td>
            <td>
              <?php if ((int) $p['children_count'] > 0): ?>
                <span class="small"><?= View::e((string) $p['children_names']) ?></span>
              <?php else: ?>
                <span class="text-muted small fst-italic">No children linked yet</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (($p['status'] ?? 'active') === 'active'): ?>
                <span class="sa-status sa-status--on">Active</span>
              <?php else: ?>
                <span class="sa-status sa-status--off">Disabled</span>
              <?php endif; ?>
            </td>
            <td class="text-end text-nowrap">
              <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/parents/<?= (int) $p['id'] ?>/edit"><i class="bi bi-pencil"></i></a>
              <form class="d-inline" method="post" action="<?= $base ?>/parents/<?= (int) $p['id'] ?>/delete"
                    data-confirm="Delete this parent account? They will no longer be able to sign in.">
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
