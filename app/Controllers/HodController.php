<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;

/**
 * Head-of-Department dashboard.
 *
 *   GET /hod    -> personalised landing page summarising the HOD's department
 *
 * The dashboard is restricted to staff members who have at least one row in
 * `department_heads` (admins also pass through, in case they want to preview).
 *
 * The page surfaces:
 *   - Form 1–4: classes grouped by `level`, with a button per subject to
 *     open the per-class mark entry sheet
 *   - the categories (departments) they head, with subjects and teachers
 *   - all classes (with student counts) and quick links into the department
 *     matrix mark-entry sheet for that class × department
 */
class HodController extends Controller
{
    public function dashboard(): string
    {
        $user = Auth::user();
        $isAdmin     = ($user['role'] ?? '') === 'admin';
        // Any users.role='hod' user (created by admin under /hods or the legacy
        // shared HOD account) gets all-departments access on the HOD dashboard.
        $isSharedHod = ($user['role'] ?? '') === 'hod';

        // Pull the optional informational department label set by the admin
        // when this HOD account was created (Admin → HODs). Done as a
        // separate query so login doesn't break on databases that haven't
        // applied the users.department migration yet.
        $hodDepartmentLabel = '';
        if ($isSharedHod && !empty($user['id'])) {
            try {
                $row = Database::query(
                    "SELECT department FROM users WHERE id = ? LIMIT 1",
                    [(int) $user['id']]
                )->fetch();
                if ($row && !empty($row['department'])) {
                    $hodDepartmentLabel = trim((string) $row['department']);
                }
            } catch (\Throwable $e) {
                // Column may not exist yet on un-migrated databases — that's fine.
            }
        }

        // Strict isolation: the system admin never lands on the HOD home.
        // Admins still control everything HODs do via Teaching, Subjects,
        // Marks, etc. — but their dashboard is /dashboard, not /hod.
        // Silent redirect — no flash — so the admin sees no interruption.
        if ($isAdmin) {
            $this->redirect('/dashboard');
            return '';
        }

        $staffId = null;
        if ($user) {
            $row = Database::query(
                "SELECT id FROM staff WHERE user_id = ? LIMIT 1",
                [(int) $user['id']]
            )->fetch();
            $staffId = $row ? (int) $row['id'] : null;
        }

        // The shared HOD account heads every department; staff users must
        // have at least one row in department_heads to land here.
        if ($isSharedHod) {
            $categories = ['core', 'science', 'arts', 'optional'];
        } else {
            if (!$staffId) {
                http_response_code(403);
                return $this->view('errors/403');
            }
            $rows = Database::query(
                "SELECT category FROM department_heads WHERE staff_id = ? ORDER BY category",
                [$staffId]
            )->fetchAll();
            $categories = array_map(static fn ($r) => (string) $r['category'], $rows);
            if (!$categories) {
                http_response_code(403);
                return $this->view('errors/403');
            }
        }

        $place = implode(',', array_fill(0, count($categories), '?'));

        // Subjects in the user's departments, with the names of every staff
        // member who teaches that subject.
        $subjects = Database::query(
            "SELECT sub.id, sub.name, sub.code, sub.category,
                    GROUP_CONCAT(DISTINCT CONCAT(st.first_name, ' ', st.last_name)
                                 ORDER BY st.first_name SEPARATOR ', ') AS teachers
             FROM subjects sub
             LEFT JOIN staff_subjects ss ON ss.subject_id = sub.id
             LEFT JOIN staff st          ON st.id = ss.staff_id
             WHERE sub.category IN ($place) AND sub.is_offered = 1
             GROUP BY sub.id
             ORDER BY sub.category, sub.name",
            $categories
        )->fetchAll();

        // Staff members who teach at least one subject in the departments,
        // with the matching subject names.
        $teachers = Database::query(
            "SELECT st.id, st.first_name, st.last_name, st.position, u.email,
                    GROUP_CONCAT(DISTINCT sub.name ORDER BY sub.name SEPARATOR ', ') AS subjects
             FROM staff st
             JOIN staff_subjects ss ON ss.staff_id = st.id
             JOIN subjects sub      ON sub.id = ss.subject_id
             LEFT JOIN users u      ON u.id = st.user_id
             WHERE sub.category IN ($place) AND sub.is_offered = 1
             GROUP BY st.id
             ORDER BY st.first_name, st.last_name",
            $categories
        )->fetchAll();

        // Classes + student counts. Every class effectively studies every
        // department's subjects, so we surface the whole roster (admin can
        // also reach this view).
        $classes = Database::query(
            "SELECT c.id, c.name, c.level, c.admission_prefix,
                    (SELECT COUNT(*) FROM students st WHERE st.class_id = c.id) AS student_count,
                    CONCAT(t.first_name, ' ', t.last_name) AS class_teacher
             FROM classes c
             LEFT JOIN staff t ON t.id = c.class_teacher_id
             ORDER BY c.level, c.name"
        )->fetchAll();

        // Group DB classes into Form 1–4 (by `level`, e.g. "Form 1" … "Form 4")
        $classesByForm = [
            'Form 1' => [],
            'Form 2' => [],
            'Form 3' => [],
            'Form 4' => [],
        ];
        foreach ($classes as $c) {
            $lv = trim((string) ($c['level'] ?? ''));
            if (isset($classesByForm[$lv])) {
                $classesByForm[$lv][] = $c;
            }
        }

        // Recent grades posted by this user (helpful "you last saved..." nudge).
        $recent = $user ? Database::query(
            "SELECT g.id, g.score, g.exam_type, g.term, g.academic_year,
                    g.created_at,
                    s.first_name, s.last_name,
                    sub.name AS subject_name
             FROM grades g
             JOIN students s   ON s.id = g.student_id
             JOIN subjects sub ON sub.id = g.subject_id
             WHERE g.recorded_by = ?
             ORDER BY g.id DESC
             LIMIT 8",
            [(int) $user['id']]
        )->fetchAll() : [];

        // Headline numbers.
        $totalStudents = 0;
        foreach ($classes as $c) $totalStudents += (int) $c['student_count'];

        $stats = [
            'departments' => count($categories),
            'subjects'    => count($subjects),
            'teachers'    => count($teachers),
            'students'    => $totalStudents,
            'classes'     => count($classes),
        ];

        return $this->view('hod/dashboard', [
            'user'               => $user,
            'isAdmin'            => $isAdmin,
            'isSharedHod'        => $isSharedHod,
            'hodDepartmentLabel' => $hodDepartmentLabel,
            'categories'         => $categories,
            'subjects'           => $subjects,
            'teachers'           => $teachers,
            'classes'            => $classes,
            'classesByForm'      => $classesByForm,
            'recent'             => $recent,
            'stats'              => $stats,
        ]);
    }

    /**
     * Performance overview: charts and KPIs for grades in the HOD's subject
     * departments (scoped academic year + term).
     *
     *   GET /hod/overview[?year=&term=]
     */
    public function overview(): string
    {
        $user = Auth::user();
        $isAdmin     = ($user['role'] ?? '') === 'admin';
        $isSharedHod = ($user['role'] ?? '') === 'hod';

        $hodDepartmentLabel = '';
        if ($isSharedHod && !empty($user['id'])) {
            try {
                $row = Database::query(
                    "SELECT department FROM users WHERE id = ? LIMIT 1",
                    [(int) $user['id']]
                )->fetch();
                if ($row && !empty($row['department'])) {
                    $hodDepartmentLabel = trim((string) $row['department']);
                }
            } catch (\Throwable $e) {
            }
        }

        if ($isAdmin) {
            $this->redirect('/dashboard');
            return '';
        }

        $staffId = null;
        if ($user) {
            $row = Database::query(
                "SELECT id FROM staff WHERE user_id = ? LIMIT 1",
                [(int) $user['id']]
            )->fetch();
            $staffId = $row ? (int) $row['id'] : null;
        }

        if ($isSharedHod) {
            $categories = ['core', 'science', 'arts', 'optional'];
        } else {
            if (!$staffId) {
                http_response_code(403);
                return $this->view('errors/403');
            }
            $rows = Database::query(
                "SELECT category FROM department_heads WHERE staff_id = ? ORDER BY category",
                [$staffId]
            )->fetchAll();
            $categories = array_map(static fn ($r) => (string) $r['category'], $rows);
            if (!$categories) {
                http_response_code(403);
                return $this->view('errors/403');
            }
        }

        $place = implode(',', array_fill(0, count($categories), '?'));
        $bindPeriod = static fn (array $extra = []) => array_merge($categories, $extra);

        // Default academic year / term (same rules as HOD dashboard).
        $defaultYear = (date('n') >= 9)
            ? date('Y') . '/' . (date('Y') + 1)
            : (date('Y') - 1) . '/' . date('Y');
        [$startStr] = explode('/', $defaultYear);
        $start = (int) $startStr;
        $availableYears = [];
        for ($i = -2; $i <= 2; $i++) {
            $a = $start + $i;
            $availableYears[] = $a . '/' . ($a + 1);
        }
        $availableTerms = ['Term 1', 'Term 2', 'Term 3'];

        $selYear = trim((string) $this->input('year'));
        $selTerm = trim((string) $this->input('term'));
        $periodSet = ($selYear !== '' && $selTerm !== ''
            && in_array($selYear, $availableYears, true)
            && in_array($selTerm, $availableTerms, true));
        if ($periodSet) {
            $year = $selYear;
            $term = $selTerm;
        } else {
            $year = $defaultYear;
            $termMap = [
                1 => 'Term 1', 2 => 'Term 1', 3 => 'Term 1',
                4 => 'Term 2', 5 => 'Term 2', 6 => 'Term 2',
                7 => 'Term 3', 8 => 'Term 3', 9 => 'Term 3',
                10 => 'Term 3', 11 => 'Term 3', 12 => 'Term 3',
            ];
            $term = $termMap[(int) date('n')] ?? 'Term 1';
        }

        $summaryParams = $bindPeriod([$year, $term]);

        $summaryRow = Database::query(
            "SELECT COUNT(*) AS grade_count,
                    COUNT(DISTINCT g.student_id) AS students_count,
                    AVG(g.score) AS avg_score
             FROM grades g
             JOIN subjects sub ON sub.id = g.subject_id
             WHERE sub.category IN ($place) AND sub.is_offered = 1
               AND g.academic_year = ? AND g.term = ?",
            $summaryParams
        )->fetch();

        $gradeCount   = (int) ($summaryRow['grade_count'] ?? 0);
        $studentsTouch = (int) ($summaryRow['students_count'] ?? 0);
        $avgOverall   = $summaryRow['avg_score'] !== null
            ? round((float) $summaryRow['avg_score'], 2) : null;

        $subjectRows = Database::query(
            "SELECT sub.name AS subject_name,
                    AVG(g.score) AS avg_score,
                    COUNT(*) AS n
             FROM grades g
             JOIN subjects sub ON sub.id = g.subject_id
             WHERE sub.category IN ($place) AND sub.is_offered = 1
               AND g.academic_year = ? AND g.term = ?
             GROUP BY sub.id, sub.name
             ORDER BY sub.name",
            $summaryParams
        )->fetchAll();

        $classRows = Database::query(
            "SELECT c.id, c.name AS class_name, c.level,
                    AVG(g.score) AS avg_score,
                    COUNT(*) AS n
             FROM grades g
             JOIN students s ON s.id = g.student_id
             JOIN classes c ON c.id = s.class_id
             JOIN subjects sub ON sub.id = g.subject_id
             WHERE sub.category IN ($place) AND sub.is_offered = 1
               AND g.academic_year = ? AND g.term = ?
             GROUP BY c.id, c.name, c.level
             ORDER BY c.level, c.name",
            $summaryParams
        )->fetchAll();

        $bandRow = Database::query(
            "SELECT
                COALESCE(SUM(CASE WHEN g.score >= 80 THEN 1 ELSE 0 END), 0) AS band_a,
                COALESCE(SUM(CASE WHEN g.score >= 60 AND g.score < 80 THEN 1 ELSE 0 END), 0) AS band_b,
                COALESCE(SUM(CASE WHEN g.score >= 40 AND g.score < 60 THEN 1 ELSE 0 END), 0) AS band_c,
                COALESCE(SUM(CASE WHEN g.score < 40 THEN 1 ELSE 0 END), 0) AS band_d
             FROM grades g
             JOIN subjects sub ON sub.id = g.subject_id
             WHERE sub.category IN ($place) AND sub.is_offered = 1
               AND g.academic_year = ? AND g.term = ?",
            $summaryParams
        )->fetch() ?: ['band_a' => 0, 'band_b' => 0, 'band_c' => 0, 'band_d' => 0];

        $examRows = Database::query(
            "SELECT g.exam_type, AVG(g.score) AS avg_score, COUNT(*) AS n
             FROM grades g
             JOIN subjects sub ON sub.id = g.subject_id
             WHERE sub.category IN ($place) AND sub.is_offered = 1
               AND g.academic_year = ? AND g.term = ?
             GROUP BY g.exam_type",
            $summaryParams
        )->fetchAll();

        $midAvg = null;
        $endAvg = null;
        $examMidN = 0;
        $examEndN = 0;
        foreach ($examRows as $er) {
            $t = (string) ($er['exam_type'] ?? '');
            if ($t === 'midterm') {
                $midAvg = round((float) $er['avg_score'], 2);
                $examMidN = (int) $er['n'];
            } elseif ($t === 'endterm') {
                $endAvg = round((float) $er['avg_score'], 2);
                $examEndN = (int) $er['n'];
            }
        }

        $chartExamLabels = ['Mid-term', 'End of term'];
        $chartExamCounts = [$examMidN, $examEndN];

        $subjectCountTracked = Database::query(
            "SELECT COUNT(*) AS c FROM subjects sub
             WHERE sub.category IN ($place) AND sub.is_offered = 1",
            $categories
        )->fetch();
        $subjectsOffered = (int) ($subjectCountTracked['c'] ?? 0);

        $chartSubjectLabels = [];
        $chartSubjectAvgs   = [];
        $chartSubjectNs     = [];
        foreach ($subjectRows as $sr) {
            $chartSubjectLabels[] = (string) $sr['subject_name'];
            $chartSubjectAvgs[]   = round((float) $sr['avg_score'], 2);
            $chartSubjectNs[]     = (int) $sr['n'];
        }

        $chartClassLabels = [];
        $chartClassAvgs   = [];
        $chartClassMeta   = [];
        foreach ($classRows as $cr) {
            $chartClassLabels[] = (string) $cr['class_name'];
            $chartClassAvgs[]   = round((float) $cr['avg_score'], 2);
            $chartClassMeta[]   = (string) ($cr['level'] ?? '');
        }

        $bandTotal = (int) $bandRow['band_a'] + (int) $bandRow['band_b']
            + (int) $bandRow['band_c'] + (int) $bandRow['band_d'];
        $bandLabels = ['80+ (Strong)', '60–79', '40–59', 'Below 40'];
        $bandData   = [
            (int) $bandRow['band_a'],
            (int) $bandRow['band_b'],
            (int) $bandRow['band_c'],
            (int) $bandRow['band_d'],
        ];

        return $this->view('hod/overview', [
            'title'                => 'Performance overview',
            'user'                 => $user,
            'isSharedHod'          => $isSharedHod,
            'hodDepartmentLabel'   => $hodDepartmentLabel,
            'categories'           => $categories,
            'availableYears'       => $availableYears,
            'availableTerms'       => $availableTerms,
            'defaultYear'          => $defaultYear,
            'selYear'              => $selYear,
            'selTerm'              => $selTerm,
            'periodSet'            => $periodSet,
            'year'                 => $year,
            'term'                 => $term,
            'avgOverall'           => $avgOverall,
            'gradeCount'           => $gradeCount,
            'studentsTouch'        => $studentsTouch,
            'subjectsOffered'      => $subjectsOffered,
            'subjectRows'          => $subjectRows,
            'midAvg'               => $midAvg,
            'endAvg'               => $endAvg,
            'bandLabels'           => $bandLabels,
            'bandData'             => $bandData,
            'bandTotal'            => $bandTotal,
            'chartSubjectLabels'   => $chartSubjectLabels,
            'chartSubjectAvgs'     => $chartSubjectAvgs,
            'chartSubjectNs'       => $chartSubjectNs,
            'chartClassLabels'     => $chartClassLabels,
            'chartClassAvgs'       => $chartClassAvgs,
            'chartClassMeta'       => $chartClassMeta,
            'chartExamLabels'      => $chartExamLabels,
            'chartExamCounts'      => $chartExamCounts,
        ]);
    }

    /**
     * Read-only "students admitted in each class" listing for HODs.
     *
     *   GET /hod/students[?class_id=]
     *
     * No edit / delete buttons — the HOD scope only allows viewing rosters
     * and printing reports. Admins can still manage students from /students.
     */
    public function students(): string
    {
        $user = Auth::user();
        if (($user['role'] ?? '') === 'admin') {
            $this->redirect('/students');
            return '';
        }

        $classes = Database::query(
            "SELECT c.id, c.name, c.level, c.admission_prefix,
                    (SELECT COUNT(*) FROM students st WHERE st.class_id = c.id) AS student_count
             FROM classes c
             ORDER BY c.level, c.name"
        )->fetchAll();

        $classFilter = (int) $this->input('class_id');
        if ($classFilter > 0) {
            $students = Database::query(
                "SELECT s.id, s.admission_no, s.first_name, s.last_name, s.gender,
                        s.section, s.stream, s.guardian_name, s.guardian_phone, s.photo_path,
                        c.id AS class_id, c.name AS class_name, c.level
                 FROM students s
                 LEFT JOIN classes c ON c.id = s.class_id
                 WHERE s.class_id = ?
                 ORDER BY s.first_name, s.last_name",
                [$classFilter]
            )->fetchAll();
        } else {
            $students = Database::query(
                "SELECT s.id, s.admission_no, s.first_name, s.last_name, s.gender,
                        s.section, s.stream, s.guardian_name, s.guardian_phone, s.photo_path,
                        c.id AS class_id, c.name AS class_name, c.level
                 FROM students s
                 LEFT JOIN classes c ON c.id = s.class_id
                 ORDER BY c.level, c.name, s.first_name, s.last_name"
            )->fetchAll();
        }

        // Group by class for the view (so Form 1A, Form 2A etc. each get their own card).
        $byClass = [];
        foreach ($students as $st) {
            $cid = (int) ($st['class_id'] ?? 0);
            $key = $cid ?: 0;
            if (!isset($byClass[$key])) {
                $byClass[$key] = [
                    'class_id'   => $cid,
                    'class_name' => $st['class_name'] ?? 'Unassigned',
                    'level'      => $st['level'] ?? '',
                    'students'   => [],
                ];
            }
            $byClass[$key]['students'][] = $st;
        }

        return $this->view('hod/students', [
            'classes'     => $classes,
            'byClass'     => $byClass,
            'classFilter' => $classFilter,
            'total'       => count($students),
        ]);
    }
}
