<?php
namespace App\Controllers;

use App\Core\App;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Settings;
use App\Services\AcademicMarking;
use App\Services\TermResultsService;

/**
 * Published term results: averages and positions derived from Mid (×/30) + End (×/70).
 *
 * GET /results                   — pick year + term, list classes
 * GET /results/class/{id}       — class leaderboard + optional subject totals
 */
class ResultsController extends Controller
{
    private const TERMS = ['Term 1', 'Term 2', 'Term 3'];

    private static function defaultYear(): string
    {
        return (date('n') >= 9)
            ? date('Y') . '/' . (date('Y') + 1)
            : (date('Y') - 1) . '/' . date('Y');
    }

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

    private static function isValidYear(string $year): bool
    {
        if (!preg_match('~^(\d{4})/(\d{4})$~', $year, $m)) {
            return false;
        }
        return ((int) $m[2]) === ((int) $m[1]) + 1;
    }

    private function isAdmin(): bool
    {
        return in_array(Auth::role(), ['admin', 'school_admin'], true);
    }

    private function staffId(): ?int
    {
        $u = Auth::user();
        if (!$u) {
            return null;
        }
        $r = Database::query('SELECT id FROM staff WHERE user_id = ? LIMIT 1', [(int) $u['id']])->fetch();

        return $r ? (int) $r['id'] : null;
    }

    private function isHod(): bool
    {
        if (Auth::role() === 'hod') {
            return true;
        }
        $sid = $this->staffId();
        if (!$sid) {
            return false;
        }
        $row = Database::query(
            'SELECT 1 FROM department_heads WHERE staff_id = ? LIMIT 1',
            [$sid]
        )->fetch();

        return (bool) $row;
    }

    /** Class IDs visible to the current user (aligned with reports). */
    private function visibleClassIds(): array
    {
        if ($this->isAdmin() || $this->isHod()) {
            $schoolId = Auth::schoolId();
            $ssf = $schoolId !== null ? ' WHERE school_id = ?' : '';
            $ssp = $schoolId !== null ? [$schoolId] : [];
            $rows = Database::query("SELECT id FROM classes{$ssf} ORDER BY name", $ssp)->fetchAll();

            return array_map(static fn ($r) => (int) $r['id'], $rows);
        }
        if (Auth::role() === 'staff') {
            $sid = $this->staffId();
            if (!$sid) {
                return [];
            }
            $rows = Database::query(
                'SELECT DISTINCT class_id FROM teaching_assignments WHERE staff_id = ?
                 UNION
                 SELECT id AS class_id FROM classes WHERE class_teacher_id = ?',
                [$sid, $sid]
            )->fetchAll();

            return array_map(static fn ($r) => (int) $r['class_id'], $rows);
        }

        return [];
    }

    private function canSeeClass(int $classId): bool
    {
        return in_array($classId, $this->visibleClassIds(), true);
    }

    public function index(): string
    {
        $year = trim((string) $this->input('year', ''));
        $term = trim((string) $this->input('term', ''));

        $periodReady = ($year !== '' && $term !== ''
            && self::isValidYear($year)
            && in_array($term, self::TERMS, true));

        if (!$periodReady) {
            return $this->view('results/period', [
                'years'         => self::selectableYears(),
                'defaultYear'   => self::defaultYear(),
                'terms'         => self::TERMS,
                'submittedYear' => $year,
                'submittedTerm' => $term,
                'invalid'       => ($year !== '' || $term !== ''),
            ]);
        }

        $ids = $this->visibleClassIds();
        $classes = [];
        if ($ids !== []) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $classes = Database::query(
                "SELECT id, name, level FROM classes WHERE id IN ($ph) ORDER BY level, name",
                $ids
            )->fetchAll();
        }

        return $this->view('results/index', [
            'year'       => $year,
            'term'       => $term,
            'terms'      => self::TERMS,
            'years'      => self::selectableYears(),
            'classes'    => $classes,
            'midMax'     => AcademicMarking::MID_MAX,
            'endMax'     => AcademicMarking::END_MAX,
            'schoolName' => Settings::get('school_name') ?: App::config('app.name'),
        ]);
    }

    public function classView(string $id): string
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
            'SELECT id, name, level FROM classes WHERE id = ?',
            [$classId]
        )->fetch();
        if (!$class) {
            http_response_code(404);

            return $this->view('errors/404');
        }

        TermResultsService::ensureTables();

        $rows = Database::query(
            "SELECT tsr.student_id, tsr.average_percentage, tsr.class_position, tsr.rank_cohort,
                    tsr.subjects_with_totals,
                    s.admission_no, s.first_name, s.last_name, s.stream
             FROM term_student_results tsr
             JOIN students s ON s.id = tsr.student_id
             WHERE tsr.class_id = ? AND tsr.academic_year = ? AND tsr.term = ?
             ORDER BY
               CASE WHEN tsr.class_position IS NULL THEN 1 ELSE 0 END,
               tsr.class_position ASC,
               s.first_name,
               s.last_name",
            [$classId, $year, $term]
        )->fetchAll();

        $subjectCols = AcademicMarking::offeredSubjectsForSchoolReport();

        $cells = [];
        if ($subjectCols !== []) {
            $schoolId = Auth::schoolId();
            $srSql = 'SELECT tsr.student_id, tsr.subject_id, tsr.total_marks, tsr.letter_grade,
                             tsr.mid_marks, tsr.end_marks
                 FROM term_subject_results tsr
                 INNER JOIN subjects sub ON sub.id = tsr.subject_id AND sub.is_offered = 1'
                 . ($schoolId !== null ? ' AND sub.school_id = ?' : '')
                 . ' WHERE tsr.class_id = ? AND tsr.academic_year = ? AND tsr.term = ?';
            $srParams = $schoolId !== null ? [$schoolId, $classId, $year, $term] : [$classId, $year, $term];
            $sr = Database::query($srSql, $srParams)->fetchAll();
            foreach ($sr as $r) {
                $sid = (int) $r['student_id'];
                $bid = (int) $r['subject_id'];
                $mid = $r['mid_marks'] !== null ? (float) $r['mid_marks'] : null;
                $end = $r['end_marks'] !== null ? (float) $r['end_marks'] : null;
                $cells[$sid][$bid] = [
                    'total' => $r['total_marks'],
                    'max'   => AcademicMarking::subjectMax($mid, $end),
                    'grade' => $r['letter_grade'],
                ];
            }
        }

        return $this->view('results/class', [
            'class'       => $class,
            'year'        => $year,
            'term'        => $term,
            'terms'       => self::TERMS,
            'years'       => self::selectableYears(),
            'rows'        => $rows,
            'subjectCols' => $subjectCols,
            'cells'       => $cells,
            'midMax'      => AcademicMarking::MID_MAX,
            'endMax'      => AcademicMarking::END_MAX,
            'schoolName'  => Settings::get('school_name') ?: App::config('app.name'),
        ]);
    }

    /**
     * Boys-vs-girls performance breakdown: student counts, average %, and
     * pass rate (>= 50%), aggregated per class and — for the super admin,
     * who is not scoped to a single school — per school as well.
     */
    public function genderPerformance(): string
    {
        $year = (string) ($this->input('year') ?: self::defaultYear());
        $term = (string) ($this->input('term') ?: 'Term 1');
        if (!in_array($term, self::TERMS, true)) {
            $term = 'Term 1';
        }

        TermResultsService::ensureTables();

        $classIds = $this->visibleClassIds();
        $byClass  = [];
        $totals   = ['male' => self::emptyGenderBucket(), 'female' => self::emptyGenderBucket()];
        $bySchool = [];
        $otherCount = 0;

        if ($classIds !== []) {
            $ph = implode(',', array_fill(0, count($classIds), '?'));

            $classRows = Database::query(
                "SELECT id, name, level FROM classes WHERE id IN ($ph) ORDER BY level, name",
                $classIds
            )->fetchAll();
            foreach ($classRows as $c) {
                $byClass[(int) $c['id']] = [
                    'name'  => $c['name'],
                    'level' => $c['level'],
                    'male'   => self::emptyGenderBucket(),
                    'female' => self::emptyGenderBucket(),
                ];
            }

            $params = array_merge($classIds, [$year, $term]);

            $classGender = Database::query(
                "SELECT tsr.class_id, s.gender,
                        COUNT(*) AS n,
                        AVG(tsr.average_percentage) AS avg_pct,
                        SUM(CASE WHEN tsr.average_percentage >= 50 THEN 1 ELSE 0 END) AS passed
                 FROM term_student_results tsr
                 JOIN students s ON s.id = tsr.student_id
                 WHERE tsr.class_id IN ($ph) AND tsr.academic_year = ? AND tsr.term = ?
                       AND s.gender IN ('male','female')
                 GROUP BY tsr.class_id, s.gender",
                $params
            )->fetchAll();
            foreach ($classGender as $r) {
                $cid = (int) $r['class_id'];
                if (!isset($byClass[$cid])) {
                    continue;
                }
                $byClass[$cid][$r['gender']] = self::genderBucketFromRow($r);
            }

            $overall = Database::query(
                "SELECT s.gender,
                        COUNT(*) AS n,
                        AVG(tsr.average_percentage) AS avg_pct,
                        SUM(CASE WHEN tsr.average_percentage >= 50 THEN 1 ELSE 0 END) AS passed
                 FROM term_student_results tsr
                 JOIN students s ON s.id = tsr.student_id
                 WHERE tsr.class_id IN ($ph) AND tsr.academic_year = ? AND tsr.term = ?
                       AND s.gender IN ('male','female')
                 GROUP BY s.gender",
                $params
            )->fetchAll();
            foreach ($overall as $r) {
                $totals[$r['gender']] = self::genderBucketFromRow($r);
            }

            $otherCount = (int) (Database::query(
                "SELECT COUNT(*) AS n
                 FROM term_student_results tsr
                 JOIN students s ON s.id = tsr.student_id
                 WHERE tsr.class_id IN ($ph) AND tsr.academic_year = ? AND tsr.term = ?
                       AND s.gender = 'other'",
                $params
            )->fetch()['n'] ?? 0);

            // Per-school breakdown only makes sense for the super admin, who
            // isn't already scoped to one school — everyone else's "totals"
            // above already ARE their one school's numbers.
            if (Auth::schoolId() === null) {
                $schoolRows = Database::query(
                    "SELECT sch.id AS school_id, sch.name AS school_name, s.gender,
                            COUNT(*) AS n,
                            AVG(tsr.average_percentage) AS avg_pct,
                            SUM(CASE WHEN tsr.average_percentage >= 50 THEN 1 ELSE 0 END) AS passed
                     FROM term_student_results tsr
                     JOIN students s ON s.id = tsr.student_id
                     JOIN schools sch ON sch.id = s.school_id
                     WHERE tsr.class_id IN ($ph) AND tsr.academic_year = ? AND tsr.term = ?
                           AND s.gender IN ('male','female')
                     GROUP BY sch.id, sch.name, s.gender
                     ORDER BY sch.name",
                    $params
                )->fetchAll();
                foreach ($schoolRows as $r) {
                    $sid = (int) $r['school_id'];
                    if (!isset($bySchool[$sid])) {
                        $bySchool[$sid] = [
                            'name'   => $r['school_name'],
                            'male'   => self::emptyGenderBucket(),
                            'female' => self::emptyGenderBucket(),
                        ];
                    }
                    $bySchool[$sid][$r['gender']] = self::genderBucketFromRow($r);
                }
            }
        }

        return $this->view('results/gender', [
            'year'       => $year,
            'term'       => $term,
            'terms'      => self::TERMS,
            'years'      => self::selectableYears(),
            'byClass'    => $byClass,
            'bySchool'   => $bySchool,
            'totals'     => $totals,
            'otherCount' => $otherCount,
            'schoolName' => Settings::get('school_name') ?: App::config('app.name'),
        ]);
    }

    /** @return array{n:int,avg:?float,passed:int,passPct:?float} */
    private static function emptyGenderBucket(): array
    {
        return ['n' => 0, 'avg' => null, 'passed' => 0, 'passPct' => null];
    }

    /** @return array{n:int,avg:?float,passed:int,passPct:?float} */
    private static function genderBucketFromRow(array $r): array
    {
        $n = (int) $r['n'];
        return [
            'n'       => $n,
            'avg'     => $r['avg_pct'] !== null ? (float) $r['avg_pct'] : null,
            'passed'  => (int) $r['passed'],
            'passPct' => $n > 0 ? ((float) $r['passed'] / $n) * 100 : null,
        ];
    }
}
