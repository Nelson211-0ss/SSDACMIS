<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;
use App\Services\AcademicMarking;
use App\Services\TermResultsService;

/**
 * Teacher mark entry.
 *
 *   GET  /marks                 -> tile list of (class, subject) pairs the
 *                                  current teacher is assigned to. Admins see
 *                                  every assignment.
 *   GET  /marks/entry?...       -> bulk entry sheet for one assignment in a
 *                                  given academic year + term + exam_type.
 *   POST /marks                 -> save the sheet (UPSERTs one row per student).
 *
 * Authorization: every action verifies that the requested (class, subject)
 * is one the current user is allowed to grade. Admins bypass that check.
 */
class MarksController extends Controller
{
    private const TERMS = ['Term 1', 'Term 2', 'Term 3'];
    private const EXAMS = ['midterm' => 'Mid-term', 'endterm' => 'End-term'];

    /* -------- helpers --------------------------------------------------- */

    private static function defaultYear(): string
    {
        return (date('n') >= 9)
            ? date('Y') . '/' . (date('Y') + 1)
            : (date('Y') - 1) . '/' . date('Y');
    }

    /**
     * The list of academic years a user may pick on the period chooser:
     * current year ± 2. Always returns "YYYY/YYYY" strings.
     */
    private static function selectableYears(): array
    {
        [$startStr] = explode('/', self::defaultYear());
        $start = (int) $startStr;
        $years = [];
        for ($i = -2; $i <= 2; $i++) {
            $a = $start + $i;
            $years[] = $a . '/' . ($a + 1);
        }
        return $years;
    }

    /** Strict server-side check: the academic year must look like "YYYY/YYYY" with consecutive years. */
    private static function isValidYear(string $year): bool
    {
        if (!preg_match('~^(\d{4})/(\d{4})$~', $year, $m)) return false;
        return ((int) $m[2]) === ((int) $m[1]) + 1;
    }

    private function isAdmin(): bool
    {
        return Auth::role() === 'admin';
    }

    /**
     * Any user with role='hod' — i.e. every account created under
     * Admin → HODs (and the legacy shared-HOD seed account). All such users
     * can enter marks for every subject across Forms 1–4.
     */
    private function isSharedHodAccount(): bool
    {
        return Auth::role() === 'hod';
    }

    /** Staff row for the logged-in user, or null. */
    private function currentStaff(): ?array
    {
        $u = Auth::user();
        if (!$u) return null;
        return Database::query(
            "SELECT id, first_name, last_name FROM staff WHERE user_id = ? LIMIT 1",
            [(int) $u['id']]
        )->fetch() ?: null;
    }

    /** Assignments visible to the current actor. */
    private function visibleAssignments(): array
    {
        if ($this->isAdmin()) {
            return Database::query(
                "SELECT ta.id, ta.class_id, ta.subject_id, ta.staff_id,
                        c.name AS class_name,
                        sub.name AS subject_name, sub.category,
                        s.first_name, s.last_name,
                        (SELECT COUNT(*) FROM students st WHERE st.class_id = ta.class_id) AS student_count
                 FROM teaching_assignments ta
                 JOIN classes  c   ON c.id   = ta.class_id
                 JOIN subjects sub ON sub.id = ta.subject_id
                 JOIN staff    s   ON s.id   = ta.staff_id
                 WHERE sub.is_offered = 1
                 ORDER BY c.name, sub.name"
            )->fetchAll();
        }
        $staff = $this->currentStaff();
        if (!$staff) return [];
        return Database::query(
            "SELECT ta.id, ta.class_id, ta.subject_id, ta.staff_id,
                    c.name AS class_name,
                    sub.name AS subject_name, sub.category,
                    (SELECT COUNT(*) FROM students st WHERE st.class_id = ta.class_id) AS student_count
             FROM teaching_assignments ta
             JOIN classes  c   ON c.id   = ta.class_id
             JOIN subjects sub ON sub.id = ta.subject_id
             WHERE ta.staff_id = ? AND sub.is_offered = 1
             ORDER BY c.name, sub.name",
            [(int) $staff['id']]
        )->fetchAll();
    }

    /** True iff the current actor may enter marks for (class, subject). */
    private function canGrade(int $classId, int $subjectId): bool
    {
        // Anything the school no longer offers is off-limits — even for admins
        // and HODs — so grades are never recorded against a hidden subject.
        $sub = Database::query(
            "SELECT category, is_offered FROM subjects WHERE id = ?",
            [$subjectId]
        )->fetch();
        if (!$sub || (int) $sub['is_offered'] !== 1) return false;
        if ($this->isAdmin()) return true;
        if ($this->isSharedHodAccount()) {
            return true;
        }
        $staff = $this->currentStaff();
        if (!$staff) return false;
        // HOD of the subject's department -> grade ANY class for that subject.
        if (in_array($sub['category'], $this->headOfCategories(), true)) return true;
        // Otherwise must have an explicit teaching assignment.
        $row = Database::query(
            "SELECT 1 FROM teaching_assignments
             WHERE staff_id = ? AND class_id = ? AND subject_id = ? LIMIT 1",
            [(int) $staff['id'], $classId, $subjectId]
        )->fetch();
        return (bool) $row;
    }

    /** Categories the current user heads (empty for non-HOD or unauthenticated). */
    private function headOfCategories(): array
    {
        static $cache = null;
        if ($cache !== null) return $cache;
        if ($this->isSharedHodAccount()) {
            return $cache = ['core', 'science', 'arts', 'optional'];
        }
        $staff = $this->currentStaff();
        if (!$staff) return $cache = [];
        $rows = Database::query(
            "SELECT category FROM department_heads WHERE staff_id = ?",
            [(int) $staff['id']]
        )->fetchAll();
        return $cache = array_map(static fn($r) => (string) $r['category'], $rows);
    }

    /**
     * Department-level "tiles" for the current actor — one per
     * (class, category-the-user-heads). Each tile becomes a matrix entry view.
     * Admins get tiles for every (class × category-with-subjects) combination.
     */
    private function visibleDepartments(): array
    {
        if ($this->isAdmin() || $this->isSharedHodAccount()) {
            return Database::query(
                "SELECT c.id AS class_id, c.name AS class_name, c.level AS class_level,
                        sub.category,
                        COUNT(DISTINCT sub.id) AS subject_count,
                        (SELECT COUNT(*) FROM students st WHERE st.class_id = c.id) AS student_count
                 FROM classes c
                 CROSS JOIN subjects sub
                 WHERE sub.is_offered = 1
                 GROUP BY c.id, c.name, c.level, sub.category
                 HAVING subject_count > 0
                 ORDER BY c.level, c.name, sub.category"
            )->fetchAll();
        }
        $cats = $this->headOfCategories();
        if (!$cats) return [];
        $place = implode(',', array_fill(0, count($cats), '?'));
        return Database::query(
            "SELECT c.id AS class_id, c.name AS class_name, c.level AS class_level,
                    sub.category,
                    COUNT(DISTINCT sub.id) AS subject_count,
                    (SELECT COUNT(*) FROM students st WHERE st.class_id = c.id) AS student_count
             FROM classes c
             CROSS JOIN subjects sub
             WHERE sub.is_offered = 1 AND sub.category IN ($place)
             GROUP BY c.id, c.name, c.level, sub.category
             HAVING subject_count > 0
             ORDER BY c.level, c.name, sub.category",
            $cats
        )->fetchAll();
    }

    /**
     * Subjects offered in the given categories (for HOD-style mark-entry grid).
     *
     * @param  list<string>  $categoriesInOrder  e.g. ['core','science','arts','optional']
     * @return list<array{id:int,name:string,category:string}>
     */
    private function hodMarkEntrySubjects(array $categoriesInOrder): array
    {
        if (!$categoriesInOrder) {
            return [];
        }
        $place = implode(',', array_fill(0, count($categoriesInOrder), '?'));

        return Database::query(
            "SELECT sub.id, sub.name, sub.category
             FROM subjects sub
             WHERE sub.category IN ($place) AND sub.is_offered = 1
             ORDER BY sub.category, sub.name",
            $categoriesInOrder
        )->fetchAll();
    }

    private function canGradeDepartment(int $classId, string $category): bool
    {
        if ($this->isAdmin()) return true;
        if ($this->isSharedHodAccount()) {
            return in_array($category, ['core', 'science', 'arts', 'optional'], true);
        }
        return in_array($category, $this->headOfCategories(), true);
    }

    /**
     * Stream filter for Form 3/4 mark entry.
     *  - science subject  -> only science-stream students
     *  - arts subject     -> only arts-stream students
     *  - core / optional  -> every student
     *  - Form 1/2 classes -> every student (no streaming)
     *
     * Returns:
     *   ['where' => "AND stream = ?" or "", 'params' => ['science'|'arts'?]]
     */
    private function streamFilterFor(int $classId, string $category): array
    {
        $row = Database::query("SELECT level FROM classes WHERE id = ?", [$classId])->fetch();
        $level = trim((string) ($row['level'] ?? ''));
        $isUpper = ($level === 'Form 3' || $level === 'Form 4');
        if (!$isUpper) {
            return ['where' => '', 'params' => []];
        }
        if ($category === 'science' || $category === 'arts') {
            return ['where' => 'AND stream = ?', 'params' => [$category]];
        }
        return ['where' => '', 'params' => []];
    }

    /* -------- actions --------------------------------------------------- */

    public function index(): string
    {
        $assignments = $this->visibleAssignments();
        $departments = $this->visibleDepartments();

        $rawYear  = trim((string) $this->input('year', ''));
        $rawTerm  = trim((string) $this->input('term', ''));
        $rawExam  = trim((string) $this->input('exam_type', ''));

        // Strict period gate — users must explicitly choose year + term before
        // any tile/link is exposed. Prevents accidentally entering marks under
        // the wrong period.
        $periodReady = ($rawYear !== '' && $rawTerm !== ''
            && self::isValidYear($rawYear)
            && in_array($rawTerm, self::TERMS, true));

        if (!$periodReady) {
            return $this->view('marks/period', [
                'mode'          => 'index',
                'years'         => self::selectableYears(),
                'defaultYear'   => self::defaultYear(),
                'terms'         => self::TERMS,
                'exams'         => self::EXAMS,
                'submittedYear' => $rawYear,
                'submittedTerm' => $rawTerm,
                'submittedExam' => $rawExam,
                'invalid'       => ($rawYear !== '' || $rawTerm !== ''),
                'extra'         => [],
            ]);
        }

        $examType = array_key_exists($rawExam, self::EXAMS) ? $rawExam : 'midterm';

        $hodOrder  = ['core', 'science', 'arts', 'optional'];
        $hodMarkCategories = ($this->isAdmin() || $this->isSharedHodAccount())
            ? $hodOrder
            : array_values(array_filter(
                $hodOrder,
                fn (string $c) => in_array($c, $this->headOfCategories(), true)
            ));

        $classesByForm = ['Form 1' => [], 'Form 2' => [], 'Form 3' => [], 'Form 4' => []];
        $classDeptCats = [];
        foreach ($departments as $d) {
            $cid = (int) $d['class_id'];
            $classDeptCats[$cid][(string) $d['category']] = true;
        }
        $seenClass = [];
        foreach ($departments as $d) {
            $cid = (int) $d['class_id'];
            if (isset($seenClass[$cid])) {
                continue;
            }
            $seenClass[$cid] = true;
            $lv = trim((string) ($d['class_level'] ?? ''));
            if (!isset($classesByForm[$lv])) {
                continue;
            }
            $classesByForm[$lv][] = [
                'id'            => $cid,
                'name'          => $d['class_name'],
                'student_count' => (int) $d['student_count'],
            ];
        }
        $hodMarkSubjects = $this->hodMarkEntrySubjects($hodMarkCategories);

        return $this->view('marks/index', [
            'assignments'       => $assignments,
            'departments'       => $departments,
            'classesByForm'     => $classesByForm,
            'classDeptCats'     => $classDeptCats,
            'hodMarkCategories' => $hodMarkCategories,
            'hodMarkSubjects'   => $hodMarkSubjects,
            'year'              => $rawYear,
            'term'              => $rawTerm,
            'examType'          => $examType,
            'terms'             => self::TERMS,
            'exams'             => self::EXAMS,
            'years'             => self::selectableYears(),
            'isAdmin'           => $this->isAdmin(),
            'headCats'          => $this->headOfCategories(),
        ]);
    }

    public function entry(): string
    {
        $classId   = (int) $this->input('class_id');
        $subjectId = (int) $this->input('subject_id');
        $year      = trim((string) $this->input('year', ''));
        $term      = trim((string) $this->input('term', ''));
        $examType  = trim((string) $this->input('exam_type', ''));

        if (!$classId || !$subjectId || !$this->canGrade($classId, $subjectId)) {
            http_response_code(403);
            return $this->view('errors/403');
        }

        // Strict period gate: the entry sheet refuses to load until the user
        // has explicitly chosen a valid academic year AND term.
        $periodReady = ($year !== '' && $term !== ''
            && self::isValidYear($year)
            && in_array($term, self::TERMS, true));
        if (!$periodReady) {
            return $this->view('marks/period', [
                'mode'          => 'entry',
                'years'         => self::selectableYears(),
                'defaultYear'   => self::defaultYear(),
                'terms'         => self::TERMS,
                'exams'         => self::EXAMS,
                'submittedYear' => $year,
                'submittedTerm' => $term,
                'submittedExam' => $examType,
                'invalid'       => ($year !== '' || $term !== ''),
                'extra'         => [
                    'class_id'   => $classId,
                    'subject_id' => $subjectId,
                ],
            ]);
        }

        if (!array_key_exists($examType, self::EXAMS)) $examType = 'midterm';

        $dualEntry = Auth::isCurrentHod();

        $class   = Database::query("SELECT id, name, level FROM classes  WHERE id = ?", [$classId])->fetch();
        $subject = Database::query("SELECT id, name, category, is_offered FROM subjects WHERE id = ?", [$subjectId])->fetch();
        if (!$subject || (int) ($subject['is_offered'] ?? 0) !== 1) {
            \App\Core\Flash::set('danger', 'That subject is no longer offered. Ask the school admin to enable it under Subjects.');
            $this->redirect('/marks');
            return '';
        }

        $filter   = $this->streamFilterFor($classId, (string) ($subject['category'] ?? ''));
        $students = Database::query(
            "SELECT id, admission_no, first_name, last_name, stream
             FROM students WHERE class_id = ? {$filter['where']}
             ORDER BY first_name, last_name",
            array_merge([$classId], $filter['params'])
        )->fetchAll();

        $existing    = [];
        $existingMid = [];
        $existingEnd = [];
        if ($students) {
            $ids = array_column($students, 'id');
            $place = implode(',', array_fill(0, count($ids), '?'));
            if ($dualEntry) {
                $rows = Database::query(
                    "SELECT student_id, exam_type, score FROM grades
                     WHERE subject_id = ? AND academic_year = ? AND term = ?
                       AND exam_type IN ('midterm','endterm')
                       AND student_id IN ($place)",
                    array_merge([$subjectId, $year, $term], $ids)
                )->fetchAll();
                foreach ($rows as $r) {
                    $sid = (int) $r['student_id'];
                    if ($r['exam_type'] === 'midterm') {
                        $existingMid[$sid] = $r['score'];
                    } else {
                        $existingEnd[$sid] = $r['score'];
                    }
                }
            } else {
                $rows = Database::query(
                    "SELECT student_id, score FROM grades
                     WHERE subject_id = ? AND academic_year = ? AND term = ? AND exam_type = ?
                       AND student_id IN ($place)",
                    array_merge([$subjectId, $year, $term, $examType], $ids)
                )->fetchAll();
                foreach ($rows as $r) {
                    $existing[(int) $r['student_id']] = $r['score'];
                }
            }
        }

        return $this->view('marks/entry', [
            'class'        => $class,
            'subject'      => $subject,
            'students'     => $students,
            'existing'     => $existing,
            'existingMid'  => $existingMid,
            'existingEnd'  => $existingEnd,
            'dualEntry'    => $dualEntry,
            'year'         => $year,
            'term'         => $term,
            'examType'     => $examType,
            'terms'        => self::TERMS,
            'exams'        => self::EXAMS,
            'years'        => self::selectableYears(),
        ]);
    }

    public function store(): string
    {
        $this->validateCsrf();

        $classId   = (int) $this->input('class_id');
        $subjectId = (int) $this->input('subject_id');
        $year      = trim((string) $this->input('year'));
        $term      = trim((string) $this->input('term'));

        if (!$classId || !$subjectId || $year === '' || $term === '') {
            Flash::set('danger', 'Missing required fields.');
            $this->redirect('/marks');
            return '';
        }
        if (!self::isValidYear($year)) {
            Flash::set('danger', 'Invalid academic year. Choose a year from the dropdown.');
            $this->redirect('/marks');
            return '';
        }
        if (!in_array($term, self::TERMS, true)) {
            Flash::set('danger', 'Invalid term — pick Term 1, Term 2, or Term 3.');
            $this->redirect('/marks');
            return '';
        }
        if (!$this->canGrade($classId, $subjectId)) {
            http_response_code(403);
            return $this->view('errors/403');
        }

        $userId = (int) (Auth::user()['id'] ?? 0);

        $entryRedirQ = 'class_id=' . $classId . '&subject_id=' . $subjectId
            . '&year=' . rawurlencode($year) . '&term=' . rawurlencode($term);

        $subjectRow = Database::query("SELECT category FROM subjects WHERE id = ?", [$subjectId])->fetch();
        $subjectCat = (string) ($subjectRow['category'] ?? '');
        $filter     = $this->streamFilterFor($classId, $subjectCat);

        if ((string) $this->input('dual_exam') === '1') {
            if (!Auth::isCurrentHod()) {
                http_response_code(403);
                return $this->view('errors/403');
            }

            $midM = (array) ($_POST['scores_mid'] ?? []);
            $endM = (array) ($_POST['scores_end'] ?? []);

            $stRows = Database::query(
                "SELECT id, admission_no FROM students WHERE class_id = ? {$filter['where']}",
                array_merge([$classId], $filter['params'])
            )->fetchAll();

            $validationErrors = [];
            foreach ($stRows as $st) {
                $sid = (int) $st['id'];
                $adm = (string) ($st['admission_no'] ?? (string) $sid);
                foreach (['midterm' => $midM, 'endterm' => $endM] as $examType => $matrix) {
                    $raw = $matrix[$sid] ?? $matrix[(string) $sid] ?? null;
                    $raw = is_string($raw) ? trim($raw) : $raw;
                    if ($raw === '' || $raw === null) {
                        continue;
                    }
                    if (!is_numeric($raw)) {
                        $validationErrors[] = $adm . ' (' . self::EXAMS[$examType] . '): Invalid number.';
                        continue;
                    }
                    $score = (float) $raw;
                    $err = $examType === 'midterm'
                        ? AcademicMarking::validateMid($score)
                        : AcademicMarking::validateEnd($score);
                    if ($err !== null) {
                        $validationErrors[] = $adm . ' (' . self::EXAMS[$examType] . '): ' . $err;
                    }
                }
            }
            if ($validationErrors !== []) {
                $msg = 'Cannot save marks. ' . implode(' ', array_slice($validationErrors, 0, 6));
                if (count($validationErrors) > 6) {
                    $msg .= ' …and ' . (count($validationErrors) - 6) . ' more.';
                }
                Flash::set('danger', $msg);
                $this->redirect('/marks/entry?' . $entryRedirQ);
                return '';
            }

            $pdo = Database::connection();
            $pdo->beginTransaction();
            $saved = 0;
            $skipped = 0;
            try {
                $upsert = $pdo->prepare(
                    "INSERT INTO grades
                        (student_id, subject_id, academic_year, term, exam_type, score, recorded_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                        score       = VALUES(score),
                        recorded_by = VALUES(recorded_by)"
                );
                $belong = $pdo->prepare("SELECT 1 FROM students WHERE id = ? AND class_id = ? {$filter['where']}");

                foreach ($stRows as $st) {
                    $sid = (int) $st['id'];
                    foreach (['midterm' => $midM, 'endterm' => $endM] as $examType => $matrix) {
                        $raw = $matrix[$sid] ?? $matrix[(string) $sid] ?? null;
                        $raw = is_string($raw) ? trim($raw) : $raw;
                        if ($raw === '' || $raw === null) {
                            $skipped++;
                            continue;
                        }
                        $belong->execute(array_merge([$sid, $classId], $filter['params']));
                        if (!$belong->fetchColumn()) {
                            $skipped++;
                            continue;
                        }
                        $score = (float) $raw;
                        $upsert->execute([$sid, $subjectId, $year, $term, $examType, $score, $userId ?: null]);
                        $saved++;
                    }
                }
                $pdo->commit();
            } catch (\Throwable $e) {
                $pdo->rollBack();
                Flash::set('danger', 'Could not save marks: ' . $e->getMessage());
                $this->redirect('/marks/entry?' . $entryRedirQ);
                return '';
            }

            try {
                TermResultsService::syncClass($classId, $year, $term);
            } catch (\Throwable $e) {
                Flash::set('danger', 'Marks saved but results summary failed to update: ' . $e->getMessage());
                $this->redirect('/marks/entry?' . $entryRedirQ);
                return '';
            }

            Flash::set('success', "Saved $saved mark" . ($saved === 1 ? '' : 's')
                . ($skipped ? " ($skipped blank cells skipped)" : '') . '.');
            $this->redirect('/marks/entry?' . $entryRedirQ);
            return '';
        }

        $examType = trim((string) $this->input('exam_type'));
        $scores   = (array) ($_POST['scores'] ?? []);

        if ($examType === '' || !array_key_exists($examType, self::EXAMS)) {
            Flash::set('danger', 'Missing or invalid exam type.');
            $this->redirect('/marks');
            return '';
        }

        $stuRows = Database::query(
            "SELECT id, admission_no FROM students WHERE class_id = ? {$filter['where']}",
            array_merge([$classId], $filter['params'])
        )->fetchAll();
        $admById = [];
        foreach ($stuRows as $sr) {
            $admById[(int) $sr['id']] = (string) ($sr['admission_no'] ?? '');
        }

        $validationErrors = [];
        foreach ($scores as $studentId => $rawScore) {
            $sid = (int) $studentId;
            if ($sid <= 0) {
                continue;
            }
            $rawScore = is_string($rawScore) ? trim($rawScore) : $rawScore;
            if ($rawScore === '' || $rawScore === null) {
                continue;
            }
            if (!is_numeric($rawScore)) {
                $adm = $admById[$sid] ?? (string) $sid;
                $validationErrors[] = $adm . ': Invalid number.';
                continue;
            }
            $score = (float) $rawScore;
            $err = $examType === 'midterm'
                ? AcademicMarking::validateMid($score)
                : AcademicMarking::validateEnd($score);
            if ($err !== null) {
                $adm = $admById[$sid] ?? (string) $sid;
                $validationErrors[] = $adm . ': ' . $err;
            }
        }
        if ($validationErrors !== []) {
            $msg = 'Cannot save marks. ' . implode(' ', array_slice($validationErrors, 0, 6));
            if (count($validationErrors) > 6) {
                $msg .= ' …and ' . (count($validationErrors) - 6) . ' more.';
            }
            Flash::set('danger', $msg);
            $this->redirect("/marks/entry?class_id=$classId&subject_id=$subjectId&year="
                . rawurlencode($year) . "&term=" . rawurlencode($term) . "&exam_type=$examType");
            return '';
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        $saved = 0;
        $skipped = 0;
        try {
            $upsert = $pdo->prepare(
                "INSERT INTO grades
                    (student_id, subject_id, academic_year, term, exam_type, score, recorded_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    score       = VALUES(score),
                    recorded_by = VALUES(recorded_by)"
            );
            $belong = $pdo->prepare("SELECT 1 FROM students WHERE id = ? AND class_id = ? {$filter['where']}");

            foreach ($scores as $studentId => $rawScore) {
                $sid = (int) $studentId;
                if ($sid <= 0) {
                    $skipped++;
                    continue;
                }
                $rawScore = is_string($rawScore) ? trim($rawScore) : $rawScore;
                if ($rawScore === '' || $rawScore === null) {
                    $skipped++;
                    continue;
                }
                $belong->execute(array_merge([$sid, $classId], $filter['params']));
                if (!$belong->fetchColumn()) {
                    $skipped++;
                    continue;
                }
                $score = (float) $rawScore;
                $upsert->execute([$sid, $subjectId, $year, $term, $examType, $score, $userId ?: null]);
                $saved++;
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Flash::set('danger', 'Could not save marks: ' . $e->getMessage());
            $this->redirect("/marks/entry?class_id=$classId&subject_id=$subjectId&year="
                . rawurlencode($year) . "&term=" . rawurlencode($term) . "&exam_type=$examType");
            return '';
        }

        try {
            TermResultsService::syncClass($classId, $year, $term);
        } catch (\Throwable $e) {
            Flash::set('danger', 'Marks saved but results summary failed to update: ' . $e->getMessage());
            $this->redirect("/marks/entry?class_id=$classId&subject_id=$subjectId&year="
                . rawurlencode($year) . "&term=" . rawurlencode($term) . "&exam_type=$examType");
            return '';
        }

        Flash::set('success', "Saved $saved mark" . ($saved === 1 ? '' : 's')
            . ($skipped ? " ($skipped skipped)" : '') . '.');
        $this->redirect("/marks/entry?class_id=$classId&subject_id=$subjectId&year="
            . rawurlencode($year) . "&term=" . rawurlencode($term) . "&exam_type=$examType");
        return '';
    }

    /* -------- Department-wide entry (HOD dashboard) -------------------- */

    /**
     * Matrix entry sheet: rows = students in the class, columns = every
     * subject in the chosen department category. Used by HODs (and admins)
     * to enter all marks for a department in one go.
     */
    public function departmentEntry(): string
    {
        $classId  = (int) $this->input('class_id');
        $category = (string) $this->input('category');
        $year     = trim((string) $this->input('year', ''));
        $term     = trim((string) $this->input('term', ''));

        if (!$classId || !in_array($category, ['core','science','arts','optional'], true)
            || !$this->canGradeDepartment($classId, $category)) {
            http_response_code(403);
            return $this->view('errors/403');
        }

        // Strict period gate.
        $periodReady = ($year !== '' && $term !== ''
            && self::isValidYear($year)
            && in_array($term, self::TERMS, true));
        if (!$periodReady) {
            return $this->view('marks/period', [
                'mode'          => 'department',
                'years'         => self::selectableYears(),
                'defaultYear'   => self::defaultYear(),
                'terms'         => self::TERMS,
                'exams'         => self::EXAMS,
                'submittedYear' => $year,
                'submittedTerm' => $term,
                'submittedExam' => '',
                'invalid'       => ($year !== '' || $term !== ''),
                'extra'         => [
                    'class_id' => $classId,
                    'category' => $category,
                ],
            ]);
        }

        $class = Database::query("SELECT id, name, level FROM classes WHERE id = ?", [$classId])->fetch();
        if (!$class) { http_response_code(404); return $this->view('errors/404'); }

        $subjects = Database::query(
            "SELECT id, name, code FROM subjects WHERE category = ? AND is_offered = 1 ORDER BY name",
            [$category]
        )->fetchAll();
        $filter   = $this->streamFilterFor($classId, $category);
        $students = Database::query(
            "SELECT id, admission_no, first_name, last_name, stream
             FROM students WHERE class_id = ? {$filter['where']}
             ORDER BY first_name, last_name",
            array_merge([$classId], $filter['params'])
        )->fetchAll();

        // Existing scores keyed as $existing[student_id][subject_id] = score
        $existing = [];
        // Load both mid-term and end-term scores in one sheet.
        $existingMid = [];
        $existingEnd = [];
        if ($students && $subjects) {
            $sIds = array_column($students, 'id');
            $subIds = array_column($subjects, 'id');
            $sPlace   = implode(',', array_fill(0, count($sIds), '?'));
            $subPlace = implode(',', array_fill(0, count($subIds), '?'));
            $rows = Database::query(
                "SELECT student_id, subject_id, exam_type, score FROM grades
                 WHERE academic_year = ? AND term = ?
                   AND exam_type IN ('midterm','endterm')
                   AND student_id IN ($sPlace)
                   AND subject_id IN ($subPlace)",
                array_merge([$year, $term], $sIds, $subIds)
            )->fetchAll();
            foreach ($rows as $r) {
                $sid = (int) $r['student_id'];
                $bid = (int) $r['subject_id'];
                if ($r['exam_type'] === 'midterm') {
                    $existingMid[$sid][$bid] = $r['score'];
                } else {
                    $existingEnd[$sid][$bid] = $r['score'];
                }
            }
        }

        return $this->view('marks/department', [
            'class'       => $class,
            'category'    => $category,
            'subjects'    => $subjects,
            'students'    => $students,
            'existingMid' => $existingMid,
            'existingEnd' => $existingEnd,
            'year'        => $year,
            'term'        => $term,
            'terms'       => self::TERMS,
            'exams'       => self::EXAMS,
            'years'       => self::selectableYears(),
        ]);
    }

    /**
     * UPSERT mid-term and end-term matrices in one submit:
     *   scores_mid[student_id][subject_id]
     *   scores_end[student_id][subject_id]
     */
    public function departmentStore(): string
    {
        $this->validateCsrf();

        $classId  = (int) $this->input('class_id');
        $category = (string) $this->input('category');
        $year     = trim((string) $this->input('year'));
        $term     = trim((string) $this->input('term'));
        $midM     = (array) ($_POST['scores_mid'] ?? []);
        $endM     = (array) ($_POST['scores_end'] ?? []);

        if (!$classId || !in_array($category, ['core','science','arts','optional'], true)
            || $year === '' || $term === '') {
            Flash::set('danger', 'Missing required fields.');
            $this->redirect('/marks'); return '';
        }
        if (!self::isValidYear($year)) {
            Flash::set('danger', 'Invalid academic year — pick one from the dropdown.');
            $this->redirect('/marks'); return '';
        }
        if (!in_array($term, self::TERMS, true)) {
            Flash::set('danger', 'Invalid term — pick Term 1, Term 2, or Term 3.');
            $this->redirect('/marks'); return '';
        }
        if (!$this->canGradeDepartment($classId, $category)) {
            http_response_code(403);
            return $this->view('errors/403');
        }

        $allowedSubs = array_column(
            Database::query("SELECT id FROM subjects WHERE category = ? AND is_offered = 1", [$category])->fetchAll(),
            'id'
        );
        $allowedSubs = array_map('intval', $allowedSubs);
        $allowedSet  = array_flip($allowedSubs);
        $filter      = $this->streamFilterFor($classId, $category);

        $userId = (int) (Auth::user()['id'] ?? 0);
        $redirQ = "class_id=$classId&category=" . rawurlencode($category)
            . "&year=" . rawurlencode($year) . "&term=" . rawurlencode($term);

        $admRows = Database::query(
            "SELECT id, admission_no FROM students WHERE class_id = ? {$filter['where']}",
            array_merge([$classId], $filter['params'])
        )->fetchAll();
        $admById = [];
        foreach ($admRows as $ar) {
            $admById[(int) $ar['id']] = (string) ($ar['admission_no'] ?? '');
        }

        $subLabels = [];
        foreach (
            Database::query(
                'SELECT id, name FROM subjects WHERE category = ? AND is_offered = 1',
                [$category]
            )->fetchAll() as $sr
        ) {
            $subLabels[(int) $sr['id']] = (string) $sr['name'];
        }

        $validationErrors = [];
        foreach ([['matrix' => $midM, 'examType' => 'midterm'], ['matrix' => $endM, 'examType' => 'endterm']] as $block) {
            $matrix = $block['matrix'];
            $examType = $block['examType'];
            foreach ($matrix as $studentId => $perSubject) {
                $sid = (int) $studentId;
                if ($sid <= 0 || !is_array($perSubject)) {
                    continue;
                }
                $adm = $admById[$sid] ?? (string) $sid;
                foreach ($perSubject as $subjectId => $rawScore) {
                    $sub = (int) $subjectId;
                    if (!isset($allowedSet[$sub])) {
                        continue;
                    }
                    $rawScore = is_string($rawScore) ? trim($rawScore) : $rawScore;
                    if ($rawScore === '' || $rawScore === null) {
                        continue;
                    }
                    if (!is_numeric($rawScore)) {
                        $validationErrors[] = $adm . ' — ' . ($subLabels[$sub] ?? $sub)
                            . ' (' . self::EXAMS[$examType] . '): Invalid number.';
                        continue;
                    }
                    $score = (float) $rawScore;
                    $err = $examType === 'midterm'
                        ? AcademicMarking::validateMid($score)
                        : AcademicMarking::validateEnd($score);
                    if ($err !== null) {
                        $validationErrors[] = $adm . ' — ' . ($subLabels[$sub] ?? $sub)
                            . ' (' . self::EXAMS[$examType] . '): ' . $err;
                    }
                }
            }
        }
        if ($validationErrors !== []) {
            $msg = 'Cannot save marks. ' . implode(' ', array_slice($validationErrors, 0, 6));
            if (count($validationErrors) > 6) {
                $msg .= ' …and ' . (count($validationErrors) - 6) . ' more.';
            }
            Flash::set('danger', $msg);
            $this->redirect("/marks/department?$redirQ");
            return '';
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        $saved = 0;
        $skipped = 0;
        try {
            $upsert = $pdo->prepare(
                "INSERT INTO grades
                    (student_id, subject_id, academic_year, term, exam_type, score, recorded_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    score       = VALUES(score),
                    recorded_by = VALUES(recorded_by)"
            );
            $belong = $pdo->prepare("SELECT 1 FROM students WHERE id = ? AND class_id = ? {$filter['where']}");

            $applyMatrix = function (array $matrix, string $examType) use ($classId, $year, $term, $allowedSet, $upsert, $belong, $userId, $filter, &$saved, &$skipped) {
                foreach ($matrix as $studentId => $perSubject) {
                    $sid = (int) $studentId;
                    if ($sid <= 0 || !is_array($perSubject)) {
                        $skipped++;
                        continue;
                    }
                    $belong->execute(array_merge([$sid, $classId], $filter['params']));
                    if (!$belong->fetchColumn()) {
                        $skipped += count($perSubject);
                        continue;
                    }

                    foreach ($perSubject as $subjectId => $rawScore) {
                        $sub = (int) $subjectId;
                        if (!isset($allowedSet[$sub])) {
                            $skipped++;
                            continue;
                        }
                        $rawScore = is_string($rawScore) ? trim($rawScore) : $rawScore;
                        if ($rawScore === '' || $rawScore === null) {
                            $skipped++;
                            continue;
                        }
                        $score = (float) $rawScore;
                        $upsert->execute([$sid, $sub, $year, $term, $examType, $score, $userId ?: null]);
                        $saved++;
                    }
                }
            };

            $applyMatrix($midM, 'midterm');
            $applyMatrix($endM, 'endterm');

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Flash::set('danger', 'Could not save marks: ' . $e->getMessage());
            $this->redirect("/marks/department?$redirQ");
            return '';
        }

        try {
            TermResultsService::syncClass($classId, $year, $term);
        } catch (\Throwable $e) {
            Flash::set('danger', 'Marks saved but results summary failed to update: ' . $e->getMessage());
            $this->redirect("/marks/department?$redirQ");
            return '';
        }

        Flash::set('success', "Saved $saved mark" . ($saved === 1 ? '' : 's')
            . ($skipped ? " ($skipped blank cells skipped)" : '') . '.');
        $this->redirect("/marks/department?$redirQ");
        return '';
    }
}
