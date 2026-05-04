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
    $photo   = trim((string) ($s['photo_path'] ?? ''));
    $initials = mb_strtoupper(mb_substr((string) ($s['first_name'] ?? ''), 0, 1, 'UTF-8') . mb_substr((string) ($s['last_name'] ?? ''), 0, 1, 'UTF-8'), 'UTF-8');
?>
          <tr>
            <td class="fw-semibold font-monospace small"><?= View::e($s['admission_no']) ?></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <?php if ($photo !== ''): ?>
                  <img src="<?= View::e($base . '/' . $photo) ?>"
                       alt=""
                       class="rounded-circle border flex-shrink-0"
                       style="width: 32px; height: 32px; object-fit: cover;"
                       loading="lazy">
                <?php else: ?>
                  <span class="rounded-circle border bg-body-secondary text-secondary d-inline-flex align-items-center justify-content-center flex-shrink-0"
                        style="width: 32px; height: 32px; font-size: 0.75rem; font-weight: 600;"
                        aria-hidden="true">
                    <?= View::e($initials !== '' ? $initials : '?') ?>
                  </span>
                <?php endif; ?>
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
              <?php if (($auth['role'] ?? '') === 'admin'): ?>
                <a class="btn btn-sm btn-outline-secondary"
                   href="<?= $base ?>/students/<?= (int)$s['id'] ?>/admission-letter"
                   data-inline-print
                   title="Print admission letter">
                  <i class="bi bi-envelope-paper"></i>
                </a>
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
