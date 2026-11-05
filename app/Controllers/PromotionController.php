<?php
namespace App\Controllers;

use App\Core\AcademicYear;
use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;
use App\Services\PromotionService;

/**
 * End-of-year promotions: move a whole class, or one student, up a level,
 * keep them back (repeat), move them down, or graduate the final class.
 *
 *   GET  /promotions                   pick a class (+ recent runs, undo)
 *   GET  /promotions/class/{id}        decide per student (?student= for one)
 *   POST /promotions/class/{id}        apply
 *   POST /promotions/undo/{batch}      reverse one run
 */
class PromotionController extends Controller
{
    public function index(): string
    {
        PromotionService::ensureSchema();
        [$schoolId, $schools, $selectedSchoolId] = $this->schoolScope();
        $year = $this->year();

        $sf = $schoolId !== null ? ' WHERE c.school_id = ?' : '';
        $classes = Database::query(
            "SELECT c.id, c.name, c.level, c.school_id,
                    (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) AS student_count
             FROM classes c{$sf}
             ORDER BY c.level, c.name",
            $schoolId !== null ? [$schoolId] : []
        )->fetchAll();

        // How many of each class's former members were already moved this year.
        $doneRows = Database::query(
            "SELECT from_class_id, COUNT(*) AS n
             FROM student_promotions
             WHERE academic_year = ? AND undone_at IS NULL" . ($schoolId !== null ? ' AND school_id = ?' : '') . "
             GROUP BY from_class_id",
            $schoolId !== null ? [$year, $schoolId] : [$year]
        )->fetchAll();
        $doneByClass = [];
        foreach ($doneRows as $r) {
            $doneByClass[(int) $r['from_class_id']] = (int) $r['n'];
        }

        $batches = PromotionService::recentBatches($schoolId);
        $years = AcademicYear::options();

        return $this->view('promotions/index', compact(
            'classes', 'doneByClass', 'batches', 'year', 'years', 'schools', 'selectedSchoolId'
        ));
    }

    public function classForm(string $id): string
    {
        PromotionService::ensureSchema();
        $class = $this->findClass((int) $id);
        if (!$class) {
            Flash::set('danger', 'Class not found.');
            $this->redirect('/promotions');
            return '';
        }
        $year = $this->year();
        $onlyStudent = (int) $this->input('student', 0);

        $classes = $this->schoolClasses((int) $class['school_id']);
        $others = array_values(array_filter($classes, static fn ($c) => (int) $c['id'] !== (int) $class['id']));
        $promoteGuess = PromotionService::guessNeighbour($class, $classes, +1);
        $demoteGuess  = PromotionService::guessNeighbour($class, $classes, -1);
        $level = PromotionService::levelNumber($class['level'] ?? null);
        $isFinal = $level === PromotionService::FINAL_LEVEL;

        $params = [(int) $class['id']];
        $only = '';
        if ($onlyStudent > 0) {
            $only = ' AND s.id = ?';
            $params[] = $onlyStudent;
        }
        $students = Database::query(
            "SELECT s.id, s.admission_no, s.first_name, s.last_name, s.gender, s.stream
             FROM students s
             WHERE s.class_id = ?{$only}
             ORDER BY s.first_name, s.last_name",
            $params
        )->fetchAll();

        $ids = array_map(static fn ($s) => (int) $s['id'], $students);
        $processed = PromotionService::processedFor($ids, $year);
        $averages = $this->latestAverages($ids, $year);

        return $this->view('promotions/class', compact(
            'class', 'year', 'students', 'processed', 'averages', 'others',
            'promoteGuess', 'demoteGuess', 'isFinal', 'level', 'onlyStudent'
        ) + ['years' => AcademicYear::options()]);
    }

    public function classStore(string $id): string
    {
        $this->validateCsrf();
        $class = $this->findClass((int) $id);
        if (!$class) {
            Flash::set('danger', 'Class not found.');
            $this->redirect('/promotions');
            return '';
        }
        $year = $this->year();
        $back = '/promotions/class/' . (int) $class['id'] . '?year=' . rawurlencode($year);

        $classes = $this->schoolClasses((int) $class['school_id']);
        $byId = [];
        foreach ($classes as $c) {
            $byId[(int) $c['id']] = $c;
        }
        $promoteTo = $byId[(int) $this->input('promote_to', 0)] ?? null;
        $demoteTo  = $byId[(int) $this->input('demote_to', 0)] ?? null;
        if ($promoteTo && (int) $promoteTo['id'] === (int) $class['id']) $promoteTo = null;
        if ($demoteTo && (int) $demoteTo['id'] === (int) $class['id']) $demoteTo = null;

        $decisions = [];
        foreach ((array) ($_POST['action'] ?? []) as $sid => $action) {
            $decisions[(int) $sid] = [
                'action' => (string) $action,
                'stream' => (string) ($_POST['stream'][$sid] ?? ''),
            ];
        }

        $u = Auth::user();
        $result = PromotionService::process($class, $year, $decisions, $promoteTo, $demoteTo, $u ? (int) $u['id'] : null);
        foreach ($result['errors'] as $err) {
            Flash::set('danger', $err);
        }

        $c = $result['counts'];
        $total = $c['promote'] + $c['repeat'] + $c['demote'] + $c['graduate'];
        if ($total === 0) {
            if (!$result['errors']) Flash::set('warning', 'Nothing to do: no student had an action selected.');
            $this->redirect($back);
            return '';
        }

        $parts = [];
        if ($c['promote'])  $parts[] = $c['promote'] . ' promoted to ' . $promoteTo['name'];
        if ($c['repeat'])   $parts[] = $c['repeat'] . ' repeating ' . $class['name'];
        if ($c['demote'])   $parts[] = $c['demote'] . ' moved down to ' . $demoteTo['name'];
        if ($c['graduate']) $parts[] = $c['graduate'] . ' graduated';
        $summary = implode(', ', $parts);

        ActivityLog::record('update', 'class', (int) $class['id'],
            "Promotions {$year} from {$class['name']}: {$summary}");
        Flash::set('success', "{$class['name']} ({$year}): {$summary}. You can undo this from the Promotions page.");
        if ($result['needStream'] > 0) {
            Flash::set('warning', $result['needStream'] . ' student(s) moved into Form 3/4 still have no Science/Arts stream. '
                . 'Set it on their profile so they appear on stream subject mark sheets.');
        }

        $this->redirect($back);
        return '';
    }

    public function undo(string $batch): string
    {
        $this->validateCsrf();
        if (!preg_match('~^[a-f0-9]{16}$~', $batch)) {
            $this->redirect('/promotions');
            return '';
        }
        [$schoolId] = $this->schoolScope();
        $r = PromotionService::undo($batch, Auth::role() === 'admin' ? null : $schoolId);
        if ($r['restored'] === 0 && $r['skipped'] === 0) {
            Flash::set('warning', 'That promotion run was already undone.');
        } else {
            $msg = "Undone: {$r['restored']} student(s) put back where they were.";
            if ($r['skipped']) {
                $msg .= " {$r['skipped']} were left alone because they have been moved again since.";
            }
            ActivityLog::record('update', 'promotion', null, "Undid promotion run {$batch} ({$r['year']})");
            Flash::set('success', $msg);
        }
        $this->redirect('/promotions?year=' . rawurlencode($r['year'] ?: $this->year()));
        return '';
    }

    /* ---------------------------------------------------------------- */

    /**
     * The year being closed. Promotion usually happens at the end of the
     * school year, or in the first weeks of the next one, so January and
     * February default to the previous year.
     */
    private function year(): string
    {
        $raw = (string) $this->input('year', '');
        if ($raw !== '') return AcademicYear::resolve($raw);
        $y = (int) AcademicYear::current();
        return (string) ((int) date('n') <= 2 ? $y - 1 : $y);
    }

    /** @return array{0:?int,1:array,2:?int} */
    private function schoolScope(): array
    {
        $schoolId = Auth::schoolId();
        if (Auth::role() === 'admin' && $schoolId === null) {
            $sel = (int) $this->input('school_id', 0) ?: null;
            $schools = Database::query("SELECT id, name FROM schools WHERE status='active' ORDER BY name")->fetchAll();
            return [$sel, $schools, $sel];
        }
        return [$schoolId, [], $schoolId];
    }

    private function findClass(int $id): ?array
    {
        $schoolId = Auth::schoolId();
        $sql = 'SELECT id, name, level, school_id FROM classes WHERE id = ?';
        $params = [$id];
        if ($schoolId !== null) {
            $sql .= ' AND school_id = ?';
            $params[] = $schoolId;
        }
        $row = Database::query($sql, $params)->fetch();
        return $row ?: null;
    }

    private function schoolClasses(int $schoolId): array
    {
        return Database::query(
            'SELECT id, name, level, school_id FROM classes WHERE school_id = ? ORDER BY level, name',
            [$schoolId]
        )->fetchAll();
    }

    /**
     * Each student's most recent end-of-term average in the year (Term 3 if
     * it exists, else the latest term with results), to help the decision.
     *
     * @param int[] $ids
     * @return array<int,array{avg:float,term:string}>
     */
    private function latestAverages(array $ids, string $year): array
    {
        if (!$ids) return [];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        try {
            $rows = Database::query(
                "SELECT student_id, term, average_percentage
                 FROM term_student_results
                 WHERE academic_year = ? AND stage = 'endterm' AND average_percentage IS NOT NULL
                   AND student_id IN ($ph)
                 ORDER BY term DESC",
                array_merge([$year], $ids)
            )->fetchAll();
        } catch (\Throwable $e) {
            return []; // results table not created yet on a fresh install
        }
        $out = [];
        foreach ($rows as $r) {
            $sid = (int) $r['student_id'];
            if (!isset($out[$sid])) {
                $out[$sid] = ['avg' => (float) $r['average_percentage'], 'term' => (string) $r['term']];
            }
        }
        return $out;
    }
}
