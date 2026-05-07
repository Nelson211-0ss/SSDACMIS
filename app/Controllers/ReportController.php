<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\AcademicMarking;

/**
 * Report cards.
 *   GET /reports                          -> selector (year + term + class + student)
 *   GET /reports/student/{id}?year=&term= -> printable single-student report
 *   GET /reports/class/{id}?year=&term=   -> printable class-wide matrix
 *
 * Authorization rules:
 *  - admin: everything
 *  - student: only their own student report
 *  - staff: any class they have a teaching assignment in, OR a class where
 *           they're the homeroom (class_teacher_id) -> reports for any
 *           student in that class
 */
class ReportController extends Controller
{
    private const TERMS = ['Term 1', 'Term 2', 'Term 3'];

    private static function defaultYear(): string
    {
        return (date('n') >= 9)
            ? date('Y') . '/' . (date('Y') + 1)
            : (date('Y') - 1) . '/' . date('Y');
    }

    private function isAdmin(): bool   { return Auth::role() === 'admin'; }
    private function isStudent(): bool { return Auth::role() === 'student'; }
    private function isStaff(): bool   { return Auth::role() === 'staff'; }

    private function staffId(): ?int
    {
        $u = Auth::user();
        if (!$u) return null;
        $r = Database::query("SELECT id FROM staff WHERE user_id = ? LIMIT 1", [(int) $u['id']])->fetch();
        return $r ? (int) $r['id'] : null;
    }

    /** Shared `hod` login or a staff user who heads a department. */
    private function isHod(): bool
    {
        if (Auth::role() === 'hod') return true;
        $sid = $this->staffId();
        if (!$sid) return false;
        $row = Database::query(
            "SELECT 1 FROM department_heads WHERE staff_id = ? LIMIT 1",
            [$sid]
        )->fetch();
        return (bool) $row;
    }

    /** Class IDs this user is allowed to see reports for. */
    private function visibleClassIds(): array
    {
        if ($this->isAdmin() || $this->isHod()) {
            // HODs grade across the whole school; let them view every class.
            $rows = Database::query("SELECT id FROM classes ORDER BY name")->fetchAll();
            return array_map(fn ($r) => (int) $r['id'], $rows);
        }
        if ($this->isStaff()) {
            $sid = $this->staffId();
            if (!$sid) return [];
            $rows = Database::query(
                "SELECT DISTINCT class_id FROM teaching_assignments WHERE staff_id = ?
                 UNION
                 SELECT id AS class_id FROM classes WHERE class_teacher_id = ?",
                [$sid, $sid]
            )->fetchAll();
            return array_map(fn ($r) => (int) $r['class_id'], $rows);
        }
        return []; // students don't list classes
    }

    private function canSeeStudent(int $studentId): bool
    {
        if ($this->isAdmin()) return true;
        if ($this->isStudent()) {
            $u = Auth::user();
            $r = Database::query("SELECT id FROM students WHERE user_id = ? LIMIT 1", [(int) $u['id']])->fetch();
            return $r && (int) $r['id'] === $studentId;
        }
        if ($this->isHod()) {
            $r = Database::query("SELECT 1 FROM students WHERE id = ? LIMIT 1", [$studentId])->fetch();
            return (bool) $r;
        }
        if ($this->isStaff()) {
            $sid = $this->staffId();
            if (!$sid) return false;
            $r = Database::query(
                "SELECT 1 FROM students s
                 WHERE s.id = ?
                   AND (
                     s.class_id IN (SELECT class_id FROM teaching_assignments WHERE staff_id = ?)
                     OR s.class_id IN (SELECT id FROM classes WHERE class_teacher_id = ?)
                   ) LIMIT 1",
                [$studentId, $sid, $sid]
            )->fetch();
            return (bool) $r;
        }
        return false;
    }

    private function canSeeClass(int $classId): bool
    {
        if ($this->isAdmin()) return true;
        if ($this->isStudent()) return false;
        if ($this->isHod()) return true;
        $sid = $this->staffId();
        if (!$sid) return false;
        $r = Database::query(
            "SELECT 1 FROM teaching_assignments WHERE staff_id = ? AND class_id = ?
             UNION
             SELECT 1 FROM classes WHERE id = ? AND class_teacher_id = ?
             LIMIT 1",
            [$sid, $classId, $classId, $sid]
        )->fetch();
        return (bool) $r;
    }

    /* -------------------------- selector -------------------------- */

    public function index(): string
    {
        $year = (string) ($this->input('year') ?: self::defaultYear());
        $term = (string) ($this->input('term') ?: 'Term 1');

        if ($this->isStudent()) {
            $u = Auth::user();
            $row = Database::query("SELECT id FROM students WHERE user_id = ? LIMIT 1", [(int) $u['id']])->fetch();
            return $this->view('reports/index', [
                'year'         => $year,
                'term'         => $term,
                'terms'        => self::TERMS,
                'role'         => 'student',
                'studentId'    => $row['id'] ?? null,
                'classes'      => [],
                'students'     => [],
            ]);
        }

        $classIds = $this->visibleClassIds();
        if (empty($classIds)) {
            return $this->view('reports/index', [
                'year' => $year, 'term' => $term, 'terms' => self::TERMS,
                'role' => Auth::role(), 'classes' => [], 'students' => [],
            ]);
        }

        $ph = implode(',', array_fill(0, count($classIds), '?'));
        $classes = Database::query(
            "SELECT c.id, c.name, c.level,
                    (SELECT COUNT(*) FROM students st WHERE st.class_id = c.id) AS student_count
             FROM classes c WHERE c.id IN ($ph)
             ORDER BY c.level, c.name",
            $classIds
        )->fetchAll();
        $students = Database::query(
            "SELECT id, admission_no, first_name, last_name, class_id
             FROM students WHERE class_id IN ($ph)
             ORDER BY class_id, first_name, last_name",
            $classIds
        )->fetchAll();

        return $this->view('reports/index', [
            'year' => $year, 'term' => $term, 'terms' => self::TERMS,
            'role' => Auth::role(),
            'classes' => $classes, 'students' => $students,
        ]);
    }

    /* -------------------------- helpers (data) -------------------- */

    /**
     * Subjects the school currently offers, narrowed to what a student at the
     * given level/stream is expected to study (admin “Subjects offered” / `is_offered`).
     * Report cards list all of these for the cohort, including rows not yet marked.
     *
     *   - Form 1 / Form 2: every offered subject.
     *   - Form 3/4 Science: offered subjects EXCEPT the Arts category.
     *   - Form 3/4 Arts:    offered subjects EXCEPT the Science category.
     *   - Form 3/4 stream='none' (not yet assigned): only Compulsory + Optional.
     */
    private function offeredCurriculum(string $level, string $stream): array
    {
        $sql = "SELECT id, name, code, category FROM subjects WHERE is_offered = 1";
        $isUpper = ($level === 'Form 3' || $level === 'Form 4');
        if ($isUpper) {
            if ($stream === 'science') {
                $sql .= " AND category <> 'arts'";
            } elseif ($stream === 'arts') {
                $sql .= " AND category <> 'science'";
            } else {
                $sql .= " AND category NOT IN ('science','arts')";
            }
        }
        $sql .= " ORDER BY FIELD(category, 'core','science','arts','optional'), name";
        return Database::query($sql)->fetchAll();
    }

    /** South Sudan ACMIS rules: Mid (×/30) + End (×/70); overall average = Σ totals ÷ subjects counted. */
    private function studentScoreSheet(int $studentId, string $year, string $term): array
    {
        return AcademicMarking::buildScoreSheet($studentId, $year, $term);
    }

    /** Competition ranking (1,2,2,4) on overall average within stream where applicable. */
    private function classPosition(int $studentId, int $classId, string $year, string $term): array
    {
        return AcademicMarking::classPositionRow($studentId, $classId, $year, $term);
    }

    /* -------------------------- per-student ----------------------- */

    public function student(string $id): string
    {
        $studentId = (int) $id;
        if (!$this->canSeeStudent($studentId)) {
            http_response_code(403); return $this->view('errors/403');
        }

        $year = (string) ($this->input('year') ?: self::defaultYear());
        $term = (string) ($this->input('term') ?: 'Term 1');
        if (!in_array($term, self::TERMS, true)) $term = 'Term 1';

        $student = Database::query(
            "SELECT s.*, c.name AS class_name, c.id AS class_id,
                    t.first_name AS teacher_first, t.last_name AS teacher_last
             FROM students s
             LEFT JOIN classes c ON c.id = s.class_id
             LEFT JOIN staff t   ON t.id = c.class_teacher_id
             WHERE s.id = ?",
            [$studentId]
        )->fetch();

        if (!$student) { http_response_code(404); return $this->view('errors/404'); }

        $sheet    = $this->studentScoreSheet($studentId, $year, $term);
        $position = $student['class_id']
            ? $this->classPosition($studentId, (int) $student['class_id'], $year, $term)
            : ['position' => null, 'cohort' => 0];

        $classId = isset($student['class_id']) ? (int) $student['class_id'] : 0;
        $reportPeers = [];
        if ($classId > 0 && $this->canSeeClass($classId)) {
            $reportPeers = Database::query(
                'SELECT id, admission_no, first_name, last_name FROM students WHERE class_id = ? ORDER BY first_name, last_name',
                [$classId]
            )->fetchAll();
        } elseif ($this->isStudent()) {
            $reportPeers = [[
                'id'            => $studentId,
                'admission_no'  => $student['admission_no'] ?? '',
                'first_name'    => $student['first_name'] ?? '',
                'last_name'     => $student['last_name'] ?? '',
            ]];
        }

        $peerPosition = null;
        $peerTotal    = count($reportPeers);
        $prevStudentId = null;
        $nextStudentId = null;
        foreach ($reportPeers as $i => $p) {
            if ((int) $p['id'] === $studentId) {
                $peerPosition = $i + 1;
                if ($i > 0) {
                    $prevStudentId = (int) $reportPeers[$i - 1]['id'];
                }
                if ($i < $peerTotal - 1) {
                    $nextStudentId = (int) $reportPeers[$i + 1]['id'];
                }
                break;
            }
        }

        $reportPeersJson = [];
        foreach ($reportPeers as $p) {
            $reportPeersJson[] = [
                'id'        => (int) $p['id'],
                'admission' => (string) ($p['admission_no'] ?? ''),
                'name'      => trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')),
            ];
        }

        return $this->view('reports/student', [
            'student' => $student, 'sheet' => $sheet, 'position' => $position,
            'year'    => $year,    'term'  => $term,
            'terms'   => self::TERMS,
            'reportPeersJson' => $reportPeersJson,
            'peerPosition'    => $peerPosition,
            'peerTotal'       => $peerTotal,
            'prevStudentId'   => $prevStudentId,
            'nextStudentId'   => $nextStudentId,
        ]);
    }

    /* -------------------------- per-class ------------------------- */

    public function classReport(string $id): string
    {
        $classId = (int) $id;
        if (!$this->canSeeClass($classId)) {
            http_response_code(403); return $this->view('errors/403');
        }

        $year = (string) ($this->input('year') ?: self::defaultYear());
        $term = (string) ($this->input('term') ?: 'Term 1');
        if (!in_array($term, self::TERMS, true)) $term = 'Term 1';

        $class = Database::query(
            "SELECT c.*, t.first_name AS teacher_first, t.last_name AS teacher_last
             FROM classes c LEFT JOIN staff t ON t.id = c.class_teacher_id
             WHERE c.id = ?", [$classId]
        )->fetch();
        if (!$class) { http_response_code(404); return $this->view('errors/404'); }

        $students = Database::query(
            "SELECT id, admission_no, first_name, last_name, stream
             FROM students WHERE class_id = ?
             ORDER BY first_name, last_name",
            [$classId]
        )->fetchAll();

        $level       = trim((string) ($class['level'] ?? ''));
        $isUpperForm = in_array($level, ['Form 3', 'Form 4'], true);

        // The class's curriculum is the union of every stream's offered
        // subjects — the per-stream views below trim the column list to
        // what their students actually study (Science vs Arts).
        if ($isUpperForm) {
            $subjects = Database::query(
                "SELECT id, name, code, category FROM subjects
                 WHERE is_offered = 1
                 ORDER BY FIELD(category, 'core','science','arts','optional'), name"
            )->fetchAll();
        } else {
            $subjects = $this->offeredCurriculum($level, 'none');
        }

        // Pull all grades for the class in one query, fold into a matrix.
        $matrix = []; // [student_id][subject_id] = ['mid'=>?, 'end'=>?, 'avg'=>?]
        foreach ($students as $s) $matrix[(int) $s['id']] = [];

        if ($students) {
            $rows = Database::query(
                "SELECT g.student_id, g.subject_id, g.exam_type, g.score
                 FROM grades g
                 INNER JOIN subjects sub ON sub.id = g.subject_id AND sub.is_offered = 1
                 WHERE g.academic_year = ? AND g.term = ?
                   AND g.student_id IN (SELECT id FROM students WHERE class_id = ?)",
                [$year, $term, $classId]
            )->fetchAll();
            foreach ($rows as $r) {
                $sid = (int) $r['student_id'];
                $bid = (int) $r['subject_id'];
                $matrix[$sid][$bid] ??= ['mid' => null, 'end' => null];
                if ($r['exam_type'] === 'midterm') {
                    $matrix[$sid][$bid]['mid'] = (float) $r['score'];
                } else {
                    $matrix[$sid][$bid]['end'] = (float) $r['score'];
                }
            }
            foreach ($matrix as $sid => &$cells) {
                foreach ($cells as $bid => &$c) {
                    if (!is_array($c)) {
                        continue;
                    }
                    $mid = $c['mid'] ?? null;
                    $end = $c['end'] ?? null;
                    $totalCell = AcademicMarking::subjectTotal(
                        $mid !== null ? (float) $mid : null,
                        $end !== null ? (float) $end : null
                    );
                    $c['total'] = $totalCell;
                    $c['avg'] = $totalCell;
                }
                unset($c);

                $sheet = AcademicMarking::buildScoreSheet((int) $sid, $year, $term);
                $cells['_total'] = $sheet['total'];
                $cells['_count'] = $sheet['count'];
                $cells['_average'] = $sheet['average'];
            }
            unset($cells);

            if ($isUpperForm) {
                $byStream = ['science' => [], 'arts' => [], 'none' => []];
                foreach ($students as $s) {
                    $stream = $s['stream'] ?? 'none';
                    if (!isset($byStream[$stream])) {
                        $stream = 'none';
                    }
                    $byStream[$stream][] = $s;
                }
                foreach ($byStream as $stream => $memberStudents) {
                    if (!$memberStudents) {
                        continue;
                    }
                    $members = [];
                    foreach ($memberStudents as $s) {
                        $stuId = (int) $s['id'];
                        $sh = AcademicMarking::buildScoreSheet($stuId, $year, $term);
                        $members[] = ['student_id' => $stuId, 'average' => $sh['average']];
                    }
                    $ranks = AcademicMarking::competitionRanksByAverage($members);
                    foreach ($memberStudents as $s) {
                        $stuId = (int) $s['id'];
                        $rk = $ranks[$stuId] ?? 0;
                        if ($rk === 0) {
                            $rk = null;
                        }
                        $matrix[$stuId]['_position'] = $rk;
                        $matrix[$stuId]['_stream'] = $stream;
                        $matrix[$stuId]['_cohort_label'] = $stream === 'none'
                            ? 'class'
                            : (ucfirst((string) $stream) . ' stream');
                    }
                }
            } else {
                $members = [];
                foreach ($students as $s) {
                    $stuId = (int) $s['id'];
                    $sh = AcademicMarking::buildScoreSheet($stuId, $year, $term);
                    $members[] = ['student_id' => $stuId, 'average' => $sh['average']];
                }
                $ranks = AcademicMarking::competitionRanksByAverage($members);
                foreach ($students as $s) {
                    $stuId = (int) $s['id'];
                    $rk = $ranks[$stuId] ?? 0;
                    if ($rk === 0) {
                        $rk = null;
                    }
                    $matrix[$stuId]['_position'] = $rk;
                    $matrix[$stuId]['_stream'] = 'none';
                    $matrix[$stuId]['_cohort_label'] = 'class';
                }
            }
        }

        // Group students for the view (so Form 3/4 reports get Science + Arts sections).
        $groups = [];
        if ($isUpperForm) {
            $groups['science'] = ['label' => 'Science Stream', 'students' => []];
            $groups['arts']    = ['label' => 'Arts Stream',    'students' => []];
            $groups['none']    = ['label' => 'Unassigned',     'students' => []];
            foreach ($students as $s) {
                $stream = $s['stream'] ?? 'none';
                if (!isset($groups[$stream])) $stream = 'none';
                $groups[$stream]['students'][] = $s;
            }
            foreach ($groups as $k => $g) {
                if (empty($g['students'])) unset($groups[$k]);
            }
        } else {
            $groups['all'] = ['label' => $class['name'], 'students' => $students];
        }

        return $this->view('reports/class', [
            'class' => $class, 'students' => $students, 'subjects' => $subjects,
            'matrix' => $matrix, 'year' => $year, 'term' => $term,
            'terms' => self::TERMS,
            'isUpperForm' => $isUpperForm,
            'groups'      => $groups,
        ]);
    }

    /**
     * Vertical A4 report card for every student in a class, stacked for
     * printing (one student per page).
     *   GET /reports/class/{id}/booklet?year=&term=
     */
    public function classBooklet(string $id): string
    {
        $classId = (int) $id;
        if (!$this->canSeeClass($classId)) {
            http_response_code(403);
            return $this->view('errors/403');
        }

        $year = (string) ($this->input('year') ?: self::defaultYear());
        $term = (string) ($this->input('term') ?: 'Term 1');
        if (!in_array($term, self::TERMS, true)) {
            $term = 'Term 1';
        }

        $class = Database::query(
            "SELECT c.*, t.first_name AS teacher_first, t.last_name AS teacher_last
             FROM classes c LEFT JOIN staff t ON t.id = c.class_teacher_id
             WHERE c.id = ?",
            [$classId]
        )->fetch();
        if (!$class) {
            http_response_code(404);
            return $this->view('errors/404');
        }

        $rows = Database::query(
            "SELECT s.*, c.name AS class_name, c.id AS class_id,
                    t.first_name AS teacher_first, t.last_name AS teacher_last
             FROM students s
             LEFT JOIN classes c ON c.id = s.class_id
             LEFT JOIN staff   t ON t.id = c.class_teacher_id
             WHERE s.class_id = ?
             ORDER BY s.first_name, s.last_name",
            [$classId]
        )->fetchAll();

        $booklet = [];
        foreach ($rows as $student) {
            $sid = (int) $student['id'];
            $booklet[] = [
                'student'  => $student,
                'sheet'    => $this->studentScoreSheet($sid, $year, $term),
                'position' => $this->classPosition($sid, $classId, $year, $term),
            ];
        }

        return $this->view('reports/class_booklet', [
            'class'   => $class,
            'booklet' => $booklet,
            'year'    => $year,
            'term'    => $term,
            'terms'   => self::TERMS,
        ]);
    }
}
