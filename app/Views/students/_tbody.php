<?php
use App\Core\View;

/** @var array<int, array<string, mixed>> $students */
/** @var string $studentsEmptyMessage */
/** @var int $totalMatching */
/** @var int $listLimit */
/** @var bool $truncated */

if (empty($students)): ?>
  <tr><td colspan="9" class="text-center text-muted py-5"><?= View::e($studentsEmptyMessage ?? 'No matching students.') ?></td></tr>
<?php else:
  foreach ($students as $s):
    $section = $s['section'] ?? 'day';
    $stream  = $s['stream']  ?? 'none';
    $streamBadge = $stream === 'science'
        ? 'bg-success-subtle text-success-emphasis'
        : ($stream === 'arts' ? 'bg-warning-subtle text-warning-emphasis' : 'bg-light text-secondary border');
?>
          <tr>
            <td class="fw-semibold font-monospace small"><?= View::e($s['admission_no']) ?></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <?php
                  $av_photo = $s['photo_path'] ?? '';
                  $av_first = $s['first_name'] ?? '';
                  $av_last  = $s['last_name']  ?? '';
                  $av_size  = 32;
                  include dirname(__DIR__) . '/_partials/student_avatar.php';
                ?>
                <span><?= View::e($s['first_name'] . ' ' . $s['last_name']) ?></span>
              </div>
            </td>
            <td class="d-none d-md-table-cell"><span class="badge bg-secondary-subtle text-secondary-emphasis"><?= View::studentEnumUpper('gender', $s['gender'] ?? '') ?></span></td>
            <td class="small"><?= View::upper($s['class_name'] ?? '') ?: '—' ?></td>
            <td class="d-none d-lg-table-cell"><span class="badge <?= $section === 'boarding' ? 'bg-info-subtle text-info-emphasis' : 'bg-light text-secondary border' ?>"><?= View::studentEnumUpper('section', $section) ?></span></td>
            <td class="d-none d-xl-table-cell">
              <?php if ($stream === 'none'): ?>
                <span class="small text-muted"><?= View::studentEnumUpper('stream', $stream) ?></span>
              <?php else: ?>
                <span class="badge <?= $streamBadge ?>"><?= View::studentEnumUpper('stream', $stream) ?></span>
              <?php endif; ?>
            </td>
            <td class="d-none d-xl-table-cell small"><?= View::e($s['guardian_name'] ?: '—') ?></td>
            <td class="d-none d-xl-table-cell font-monospace small"><?= View::e($s['guardian_phone'] ?: '—') ?></td>
            <td class="text-end text-nowrap">
              <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/students/<?= (int)$s['id'] ?>/edit" title="Edit"><i class="bi bi-pencil"></i></a>
              <?php if (in_array($auth['role'] ?? '', ['admin', 'school_admin'], true)): ?>
                <a class="btn btn-sm btn-outline-secondary"
                   href="<?= $base ?>/students/<?= (int)$s['id'] ?>/admission-letter"
                   data-inline-print
                   title="Print admission letter">
                  <i class="bi bi-envelope-paper"></i>
                </a>
              <?php endif; ?>
              <?php if (($auth['role'] ?? '') === 'admin'): ?>
                <form class="d-inline" method="post" action="<?= $base ?>/students/<?= (int)$s['id'] ?>/delete" data-confirm="Delete this student?">
                  <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
<?php
  endforeach;

  if (!empty($truncated)):
    $shown = count($students);
    $extra = max(0, ((int) ($totalMatching ?? 0)) - $shown);
?>
          <tr class="table-warning">
            <td colspan="9" class="text-center small text-warning-emphasis py-3">
              <i class="bi bi-funnel-fill me-1"></i>
              Showing <?= $shown ?> of <?= (int) $totalMatching ?> students.
              <?= $extra ?> more match — refine your search above to narrow the list.
            </td>
          </tr>
<?php endif;
endif;
