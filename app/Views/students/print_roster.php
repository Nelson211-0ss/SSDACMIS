<?php
use App\Core\View;

$layout = 'app';
$title = 'Print student roster';

$total = count($students ?? []);
$grouped = empty($classId) || (int) $classId <= 0; // viewing more than one class -> group by class instead of a per-row Class column
$showSchoolInGroup = empty($selectedSchoolId); // super admin, "All schools"
$letterhead = $letterhead ?? [];
$logoRel = trim((string) ($letterhead['logo'] ?? ''));
$logoUrl = '';
if ($logoRel !== '') {
    $abs = dirname(__DIR__, 3) . '/public/' . ltrim($logoRel, '/');
    if (is_file($abs)) $logoUrl = rtrim($base, '/') . '/' . ltrim($logoRel, '/');
}
$motto  = trim((string) ($letterhead['motto']  ?? ''));
$addr   = trim((string) ($letterhead['address'] ?? ''));
$htName = trim((string) ($letterhead['headteacher_name']  ?? ''));
$htTitle = trim((string) ($letterhead['headteacher_title'] ?? '')) ?: 'Head Teacher';
?>
<div class="student-roster-print results-print-area report-page--print-landscape">
  <div class="page-header student-roster-page-head d-print-none">
    <div>
      <h2 class="h4 mb-1"><i class="bi bi-printer text-primary"></i> Student roster</h2>
      <p class="page-header__sub mb-0">Print the official list of enrolled students — whole school or one class.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-outline-secondary" href="<?= $base ?>/students">
        <i class="bi bi-arrow-left"></i> Back
      </a>
      <button type="button" class="btn btn-primary" onclick="window.print()">
        <i class="bi bi-printer"></i> Print
      </button>
    </div>
  </div>

  <div class="card border-0 shadow-sm mb-4 student-roster-filter-card d-print-none">
    <div class="card-header py-3 d-flex align-items-center gap-2 border-0 border-bottom bg-transparent">
      <span class="card-header-icon card-header-icon--blue flex-shrink-0" aria-hidden="true"><i class="bi bi-funnel-fill"></i></span>
      <div>
        <strong class="d-block">Filter</strong>
        <span class="small text-muted fw-normal">Choose scope, then apply to refresh the preview below.</span>
      </div>
    </div>
    <div class="card-body pt-3 pb-4">
      <form method="get" action="<?= $base ?>/students/print" class="student-roster-filter-form">
        <label class="form-label fw-semibold mb-2 mb-lg-3">
          Who to include <span class="text-danger">*</span>
        </label>
        <div class="student-roster-filter-controls rounded-4 border shadow-sm">
          <div class="row g-3 g-lg-4 align-items-end">
            <?php if (!empty($isSuperAdmin)): ?>
              <div class="col-12 col-lg-4">
                <label class="form-label small fw-semibold mb-1" for="roster-school-filter">School</label>
                <select id="roster-school-filter" name="school_id" class="form-select form-select-lg student-roster-filter-select">
                  <option value="0" <?= empty($selectedSchoolId) ? 'selected' : '' ?>>All schools</option>
                  <?php foreach (($schools ?? []) as $sch): ?>
                    <option value="<?= (int) $sch['id'] ?>" <?= (int) ($selectedSchoolId ?? 0) === (int) $sch['id'] ? 'selected' : '' ?>>
                      <?= View::e($sch['name'] ?? '') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php endif; ?>
            <div class="col-12 col-lg">
              <label class="form-label small fw-semibold mb-1" for="roster-class-filter">Class</label>
              <select id="roster-class-filter" name="class_id" class="form-select form-select-lg student-roster-filter-select">
                <option value="0" <?= empty($classId) ? 'selected' : '' ?>>All classes</option>
                <?php foreach (($classes ?? []) as $c): ?>
                  <option value="<?= (int) $c['id'] ?>" <?= (int) ($classId ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                    <?= View::e(($c['name'] ?? '') . (!empty($c['level']) ? ' · ' . $c['level'] : '')) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-lg-auto" style="min-width: 11rem;">
              <label class="form-label small fw-semibold mb-1" for="roster-gender-filter">Gender</label>
              <select id="roster-gender-filter" name="gender" class="form-select form-select-lg student-roster-filter-select">
                <?php $g = (string) ($gender ?? ''); ?>
                <option value="" <?= $g === '' ? 'selected' : '' ?>>All</option>
                <option value="male" <?= $g === 'male' ? 'selected' : '' ?>>Male</option>
                <option value="female" <?= $g === 'female' ? 'selected' : '' ?>>Female</option>
                <option value="other" <?= $g === 'other' ? 'selected' : '' ?>>Other</option>
              </select>
            </div>
            <div class="col-12 col-lg-auto">
              <button type="submit" class="btn btn-primary btn-lg student-roster-filter-apply w-100">
                <i class="bi bi-arrow-clockwise" aria-hidden="true"></i>
                <span>Apply filter</span>
              </button>
            </div>
          </div>
        </div>
        <p class="form-text mb-0 mt-3 small">
          <i class="bi bi-shield-lock text-muted me-1"></i>
          Administrators only. Lists reflect students currently assigned in the system.
        </p>
      </form>
    </div>
  </div>

  <div class="results-table-panel student-roster-sheet border-0 shadow-sm">
    <header class="report-head roster-letterhead">
      <div class="report-head__brand">
        <?php if ($logoUrl !== ''): ?>
          <img src="<?= View::e($logoUrl) ?>" alt="">
        <?php else: ?>
          <i class="bi bi-mortarboard-fill"></i>
        <?php endif; ?>
        <div>
          <div class="report-head__school"><?= View::e($schoolName ?? '') ?></div>
          <?php if ($motto !== ''): ?>
            <div class="report-head__motto fst-italic"><?= View::e($motto) ?></div>
          <?php endif; ?>
          <div class="report-head__sub">
            Student Enrollment Roster<?php if ($addr !== ''): ?> &middot; <?= View::e($addr) ?><?php endif; ?>
          </div>
        </div>
      </div>
      <div class="report-head__period">
        <div><strong>Students:</strong> <?= (int) $total ?></div>
        <div>
          <strong>Scope:</strong>
          <?= !empty($filterClass)
              ? View::e(($filterClass['name'] ?? '') . (!empty($filterClass['level']) ? ' · ' . $filterClass['level'] : ''))
              : 'All classes' ?>
        </div>
        <div><strong>Gender:</strong> <?= !empty($gender) ? View::studentEnumUpper('gender', $gender) : 'All' ?></div>
      </div>
    </header>

    <p class="small text-muted mb-3 d-print-none">
      <i class="bi bi-info-circle"></i> Preview matches what will print (landscape). Use <strong>Print</strong> above.
    </p>

    <?php if ($total === 0): ?>
      <div class="alert alert-info border-0 shadow-none d-print-none mb-0">
        <i class="bi bi-inbox me-2"></i>No students match this filter.
      </div>
      <p class="d-none d-print-block text-muted small mb-0">No students on file for this selection.</p>
    <?php else: ?>
      <div class="table-edge rounded-2 border overflow-hidden">
        <table class="table table-sm table-bordered align-middle results-density-table student-roster-table mb-0">
          <thead class="table-light">
            <?php
              // Printed rosters are handed round as loose sheets, and only
              // page 1 carries the letterhead. Everything in <thead> repeats
              // at the top of every printed page, so this caption row makes
              // each sheet self-identifying. Print-only — on screen the
              // letterhead is right there above the table.
              $scopeLabel = !empty($filterClass)
                  ? ($filterClass['name'] ?? '') . (!empty($filterClass['level']) ? ' · ' . $filterClass['level'] : '')
                  : 'All classes';
            ?>
            <tr class="roster-print-caption">
              <th colspan="9">
                <span class="roster-print-caption__school"><?= View::e($schoolName ?? '') ?></span>
                <span class="roster-print-caption__meta">
                  Student Enrollment Roster &middot; <?= View::e($scopeLabel) ?>
                  &middot; <?= (int) $total ?> student<?= $total === 1 ? '' : 's' ?>
                  &middot; <?= View::e($printedAt ?? '') ?>
                </span>
              </th>
            </tr>
            <tr>
              <th class="text-center rd-pos">#</th>
              <th scope="col">Admission no.</th>
              <th scope="col">Student name</th>
              <th class="text-center">Gender</th>
              <th class="text-center">DOB</th>
              <th class="text-center">Section</th>
              <th class="text-center">Stream</th>
              <th scope="col">Guardian</th>
              <th scope="col">Phone</th>
            </tr>
          </thead>
          <tbody>
            <?php
              $prevClassId = false; // sentinel: no row seen yet
              $rowNum = 0;
              foreach (array_values($students) as $s):
                $dob = $s['dob'] ?? null;
                $dobStr = ($dob && $dob !== '0000-00-00') ? date('d M Y', strtotime((string) $dob)) : '—';
                $stream = (string) ($s['stream'] ?? 'none');
                $curClassId = $s['class_id'] ?? null;

                if ($grouped && $curClassId !== $prevClassId):
                  $prevClassId = $curClassId;
                  $groupLabel = trim((string) ($s['class_name'] ?? '')) !== ''
                      ? $s['class_name'] . (!empty($s['class_level']) ? ' · ' . $s['class_level'] : '')
                      : 'Unassigned';
                  if ($showSchoolInGroup && trim((string) ($s['school_name'] ?? '')) !== '') {
                      $groupLabel = $s['school_name'] . ' — ' . $groupLabel;
                  }
            ?>
              <tr class="roster-group-row">
                <td colspan="9">
                  <i class="bi bi-mortarboard"></i> <?= View::e($groupLabel) ?>
                </td>
              </tr>
            <?php endif; $rowNum++; ?>
              <tr>
                <td class="text-center text-muted rd-pos"><?= $rowNum ?></td>
                <td class="font-monospace small"><?= View::e($s['admission_no'] ?? '') ?></td>
                <td class="fw-medium"><?= View::e(trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? ''))) ?></td>
                <td class="text-center"><span class="badge rounded-pill bg-light text-secondary border"><?= View::studentEnumUpper('gender', $s['gender'] ?? '') ?></span></td>
                <td class="text-center small font-monospace"><?= View::e($dobStr) ?></td>
                <td class="text-center small"><?= View::studentEnumUpper('section', $s['section'] ?? '') ?></td>
                <td class="text-center small">
                  <?php if ($stream === 'none'): ?>
                    <?= View::studentEnumUpper('stream', $stream) ?>
                  <?php else: ?>
                    <span class="badge rounded-pill bg-light border"><?= View::studentEnumUpper('stream', $stream) ?></span>
                  <?php endif; ?>
                </td>
                <td class="small"><?= View::e($s['guardian_name'] ?: '—') ?></td>
                <td class="small font-monospace"><?= View::e($s['guardian_phone'] ?: '—') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="report-signature-row roster-signature-row">
        <div>
          <div class="report-signature-line"></div>
          <div class="report-signature-lbl">Prepared by (Registrar / Class teacher)</div>
        </div>
        <div>
          <div class="report-signature-line"></div>
          <div class="report-signature-lbl">
            Verified by<?= $htName !== '' ? ' &middot; <strong>' . View::e($htName) . '</strong> (' . View::e($htTitle) . ')' : ' (' . View::e($htTitle) . ')' ?>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <footer class="report-footer roster-print-footer">
      <?= (int) $total ?> student<?= $total === 1 ? '' : 's' ?> listed &middot; Generated <?= View::e($printedAt ?? '') ?>
    </footer>
  </div>
</div>
