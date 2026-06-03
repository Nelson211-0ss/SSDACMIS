<?php use App\Core\View;
$layout = 'app';
$title = 'Announcements';
$canPost = in_array($auth['role'], ['admin', 'school_admin', 'staff'], true);

$pageTitle = 'Announcements';
$pageSubtitle = $canPost ? 'Post notices for students and staff.' : 'School notices and updates.';
$pageIcon = 'bi-megaphone';
include dirname(__DIR__) . '/_partials/app_page_header.php';
?>

<?php if ($canPost): ?>
  <div class="card mb-3">
    <div class="card-header d-flex align-items-center gap-2">
      <span class="card-header-icon card-header-icon--orange" aria-hidden="true"><i class="bi bi-megaphone-fill"></i></span>
      <strong class="mb-0">New announcement</strong>
    </div>
    <div class="card-body">
      <form method="post" action="<?= $base ?><?= $portalPrefix ?? '' ?>/announcements">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
        <div class="mb-2"><input name="title" class="form-control" placeholder="Title" required></div>
        <div class="mb-3"><textarea name="body" class="form-control" rows="3" placeholder="Message" required></textarea></div>
        <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Post</button>
      </form>
    </div>
  </div>
<?php endif; ?>

<div class="d-grid gap-2">
  <?php if (empty($items)): ?>
    <div class="sa-empty card">
      <div class="card-body py-4 text-center text-muted">No announcements yet.</div>
    </div>
  <?php else: foreach ($items as $a): ?>
    <article class="announcement-card">
      <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
        <h6 class="mb-0 fw-semibold"><?= View::e($a['title']) ?></h6>
        <small class="text-muted text-nowrap"><?= View::e(date('M j, Y H:i', strtotime($a['created_at']))) ?></small>
      </div>
      <div class="announcement-card__meta mb-2">by <?= View::e($a['author_name'] ?? 'Unknown') ?></div>
      <p class="mb-0 small"><?= nl2br(View::e($a['body'])) ?></p>
    </article>
  <?php endforeach; endif; ?>
</div>
