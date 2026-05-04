<?php use App\Core\View; $layout = 'app'; $title = 'Announcements'; ?>
<h4 class="mb-3"><i class="bi bi-megaphone"></i> Announcements</h4>

<?php if (in_array($auth['role'], ['admin','staff'])): ?>
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white d-flex align-items-center">
      <span class="card-header-icon card-header-icon--orange me-2" aria-hidden="true"><i class="bi bi-megaphone-fill"></i></span>
      <strong class="mb-0">New announcement</strong>
    </div>
    <div class="card-body">
      <form method="post" action="<?= $base ?><?= $portalPrefix ?>/announcements">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
        <div class="mb-2"><input name="title" class="form-control" placeholder="Title" required></div>
        <div class="mb-3"><textarea name="body" class="form-control" rows="3" placeholder="Body" required></textarea></div>
        <button class="btn btn-primary"><i class="bi bi-send"></i> Post</button>
      </form>
    </div>
  </div>
<?php endif; ?>

<div class="d-grid gap-3">
  <?php if (empty($items)): ?>
    <div class="text-muted text-center py-4">No announcements yet.</div>
  <?php else: foreach ($items as $a): ?>
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <div class="d-flex justify-content-between">
          <h6 class="mb-1"><?= View::e($a['title']) ?></h6>
          <small class="text-muted"><?= View::e(date('M j, Y H:i', strtotime($a['created_at']))) ?></small>
        </div>
        <div class="text-muted small mb-2">by <?= View::e($a['author_name'] ?? 'Unknown') ?></div>
        <p class="mb-0"><?= nl2br(View::e($a['body'])) ?></p>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>
