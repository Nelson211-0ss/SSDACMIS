<?php
namespace App\Controllers;

use App\Core\AcademicYear;
use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;
use App\Services\ClassRoster;

/**
 * Students who did not sit a term's exams: no mark at all — neither
 * mid-term nor end of term, in any subject — for the chosen year and term.
 * Filter by term and class, then delete the selected students.
 *
 *   GET  /students/no-marks?year=&term=&class_id=
 *   POST /students/no-marks/delete
 *
 * "No mark" means no row in `grades`: clearing a mark on the entry sheet
 * deletes its row, while a real score of 0 is kept as a row, so a student
 * who scored 0 is never listed here.
 */
class NoMarksController extends Controller
{
    private const TERMS = ['Term 1', 'Term 2', 'Term 3'];

    public function index(): string
    {
        [$year, $term, $classId] = $this->filters();
        $classes = $this->classes();

        $students = [];
        $scanned  = 0;
        if ($classes !== []) {
            [$students, $scanned] = $this->findWithoutMarks($year, $term, $classId, $classes);
        }

        return $this->view('students/no_marks', [
            'years'    => AcademicYear::options(),
            'terms'    => self::TERMS,
            'year'     => $year,
            'term'     => $term,
            'classId'  => $classId,
            'classes'  => $classes,
            'students' => $students,
            'scanned'  => $scanned,
        ]);
    }

    public function delete(): string
    {
        $this->validateCsrf();
        [$year, $term, $classId] = $this->filters();
        $back = '/students/no-marks?year=' . rawurlencode($year) . '&term=' . rawurlencode($term)
              . ($classId > 0 ? '&class_id=' . $classId : '');

        $ids = array_values(array_unique(array_filter(array_map(
            'intval',
            (array) ($_POST['student_ids'] ?? [])
        ))));
        if ($ids === []) {
            Flash::set('warning', 'Tick at least one student to delete.');
            $this->redirect($back);
            return '';
        }
        if (strtoupper(trim((string) $this->input('confirm_word', ''))) !== 'DELETE') {
            Flash::set('danger', 'Type DELETE in the confirmation box to remove the selected students.');
            $this->redirect($back);
            return '';
        }

        // Re-check every id on the server: it must be in this school and
        // still have no marks for the period. A mark entered since the list
        // loaded, or a tampered id, leaves that student untouched.
        $classes = $this->classes();
        [$eligible] = $this->findWithoutMarks($year, $term, $classId, $classes);
        $eligibleById = [];
        foreach ($eligible as $s) {
            $eligibleById[(int) $s['id']] = $s;
        }

        $deleted = 0;
        $skipped = 0;
        $names = [];
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            foreach ($ids as $id) {
                if (!isset($eligibleById[$id])) {
                    $skipped++;
                    continue;
                }
                $s = $eligibleById[$id];
                Database::query('DELETE FROM students WHERE id = ?', [$id]);
                if (!empty($s['user_id'])) {
                    Database::query("DELETE FROM users WHERE id = ? AND role = 'student'", [(int) $s['user_id']]);
                }
                $deleted++;
                $names[] = $s['admission_no'];
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        // Photos go after the commit, so a rollback never leaves a record
        // pointing at a deleted file.
        foreach ($ids as $id) {
            if (isset($eligibleById[$id])) {
                $this->deletePhotoFile((string) ($eligibleById[$id]['photo_path'] ?? ''));
            }
        }

        if ($deleted > 0) {
            ActivityLog::record(
                'delete', 'student', null,
                "Deleted {$deleted} student(s) with no marks for {$term} {$year}: " . implode(', ', $names)
            );
            Flash::set('success', "Deleted {$deleted} student(s) with no {$term} {$year} marks, with their attendance, fees and login accounts."
                . ($skipped > 0 ? " {$skipped} skipped because they now have marks or are not in your school." : ''));
        } else {
            Flash::set('warning', 'No students were deleted: the selected students now have marks or are not in your school.');
        }
        $this->redirect($back);
        return '';
    }

    /** @return array{0:string,1:string,2:int} year, term, class id (0 = all classes) */
    private function filters(): array
    {
        $year = AcademicYear::resolve((string) $this->input('year', ''));
        $term = (string) $this->input('term', '');
        if (!in_array($term, self::TERMS, true)) {
            $term = 'Term 1';
        }

        return [$year, $term, max(0, (int) $this->input('class_id', 0))];
    }

    /** Classes in the signed-in user's school (every class for the super admin). */
    private function classes(): array
    {
        $schoolId = Auth::schoolId();
        $sf = $schoolId !== null ? ' WHERE school_id = ?' : '';

        return Database::query(
            "SELECT id, name, level FROM classes{$sf} ORDER BY level, name",
            $schoolId !== null ? [$schoolId] : []
        )->fetchAll();
    }

    /**
     * Students on each class's roster for $year (year-aware, so a past term
     * lists the class as it was then) who have no mark at all for $term.
     *
     * @return array{0:list<array<string,mixed>>,1:int} matches, students checked
     */
    private function findWithoutMarks(string $year, string $term, int $classId, array $classes): array
    {
        $schoolId = Auth::schoolId();
        $matches = [];
        $seen = [];
        $scanned = 0;

        foreach ($classes as $c) {
            $cid = (int) $c['id'];
            if ($classId > 0 && $cid !== $classId) {
                continue;
            }
            [$rosterSql, $rosterParams] = ClassRoster::where($cid, $year, 's');
            $params = $rosterParams;
            $schoolSql = '';
            if ($schoolId !== null) {
                $schoolSql = ' AND s.school_id = ?';
                $params[] = $schoolId;
            }

            $scanned += (int) (Database::query(
                "SELECT COUNT(*) AS n FROM students s WHERE {$rosterSql}{$schoolSql}",
                $params
            )->fetch()['n'] ?? 0);

            $rows = Database::query(
                "SELECT s.id, s.user_id, s.admission_no, s.first_name, s.last_name, s.gender,
                        s.stream, s.photo_path, s.created_at
                 FROM students s
                 WHERE {$rosterSql}{$schoolSql}
                   AND NOT EXISTS (
                       SELECT 1 FROM grades g
                       WHERE g.student_id = s.id AND g.academic_year = ? AND g.term = ?
                   )
                 ORDER BY s.first_name, s.last_name",
                array_merge($params, [$year, $term])
            )->fetchAll();

            foreach ($rows as $r) {
                $sid = (int) $r['id'];
                if (isset($seen[$sid])) {
                    continue;
                }
                $seen[$sid] = true;
                $r['class_id']   = $cid;
                $r['class_name'] = (string) $c['name'];
                $r['other_terms'] = [];
                $matches[] = $r;
            }
        }

        // Which other terms of the year DO have marks — the difference
        // between a student who missed one exam and one who has left.
        if ($matches !== []) {
            $ids = array_map(static fn ($r) => (int) $r['id'], $matches);
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $other = Database::query(
                "SELECT student_id, term FROM grades
                 WHERE academic_year = ? AND student_id IN ({$ph})
                 GROUP BY student_id, term",
                array_merge([$year], $ids)
            )->fetchAll();
            $byStudent = [];
            foreach ($other as $o) {
                $byStudent[(int) $o['student_id']][] = (string) $o['term'];
            }
            foreach ($matches as &$m) {
                $terms = $byStudent[(int) $m['id']] ?? [];
                sort($terms);
                $m['other_terms'] = $terms;
            }
            unset($m);
        }

        return [$matches, $scanned];
    }

    /** Remove a passport photo, only ever from public/uploads/students (same rule as StudentController). */
    private function deletePhotoFile(string $relativePath): void
    {
        if (trim($relativePath) === '') return;
        $studentsDir = realpath(dirname(__DIR__, 2) . '/public/uploads/students');
        if ($studentsDir === false) return;
        $abs = realpath(dirname(__DIR__, 2) . '/public/' . ltrim($relativePath, '/'));
        if ($abs === false) return;
        if (!str_starts_with($abs, $studentsDir . DIRECTORY_SEPARATOR)) return;
        if (is_file($abs)) {
            @unlink($abs);
        }
    }
}
