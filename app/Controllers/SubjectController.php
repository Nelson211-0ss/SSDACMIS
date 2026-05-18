<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;

/**
 * Curriculum management.
 *
 *   GET  /subjects            -> list every subject and let the admin curate
 *                                which ones the school actually teaches.
 *   POST /subjects            -> add a brand-new subject row.
 *   POST /subjects/offered    -> bulk-update which subjects are offered.
 *   POST /subjects/{id}/delete -> hard-delete (rare, cascades to grades).
 *
 * The "is_offered" flag is what the rest of the app reads (mark entry,
 * dashboards, report cards). Disabling a subject keeps any historic grades
 * intact but hides it from new entry and from report cards going forward.
 */
class SubjectController extends Controller
{
    private const CATEGORIES = ['core', 'science', 'arts', 'optional'];

    private const DEFAULTS = [
        ['English Language',      'ENG',   'core'],
        ['Mathematics',           'MATH',  'core'],
        ['Citizenship',           'CITZ',  'core'],
        ['Religious Education',   'RE',    'core'],
        ['Biology',               'BIO',   'science'],
        ['Chemistry',             'CHEM',  'science'],
        ['Physics',               'PHY',   'science'],
        ['Geography',             'GEO',   'arts'],
        ['History',               'HIST',  'arts'],
        ['Commerce',              'COM',   'arts'],
        ['Arabic',                'ARAB',  'optional'],
        ['French',                'FREN',  'optional'],
        ['Agriculture',           'AGRI',  'optional'],
        ['ICT',                   'ICT',   'optional'],
        ['Accounting',            'ACC',   'optional'],
        ['Additional Mathematics','AMATH', 'optional'],
        ['Literature in English', 'LIT',   'optional'],
        ['Fine Art',              'ART',   'optional'],
    ];

    public function index(): string
    {
        $schoolId = Auth::schoolId();
        $isAdmin = Auth::role() === 'admin';
        $selectedSchool = null;
        if ($isAdmin) {
            $sel = (int) $this->input('school_id', 0) ?: null;
            if ($sel !== null) { $schoolId = $sel; $selectedSchool = $sel; }
        }
        $sf = $schoolId !== null ? ' AND school_id = ?' : '';
        $sp = $schoolId !== null ? [$schoolId] : [];

        // Auto-seed default curriculum for new schools so school admins
        // see the full subject list on first visit instead of a blank page.
        if ($schoolId !== null) {
            $count = (int) Database::query(
                "SELECT COUNT(*) FROM subjects WHERE school_id = ?",
                [$schoolId]
            )->fetchColumn();

            if ($count === 0) {
                foreach (self::DEFAULTS as [$name, $code, $cat]) {
                    Database::query(
                        "INSERT IGNORE INTO subjects (school_id, name, code, category, is_offered)
                         VALUES (?, ?, ?, ?, 0)",
                        [$schoolId, $name, $code, $cat]
                    );
                }
            }
        }

        $subjects = Database::query(
            "SELECT id, name, code, category, is_offered FROM subjects
             WHERE 1=1{$sf}
             ORDER BY FIELD(category, 'core','science','arts','optional'), name",
            $sp
        )->fetchAll();
        $schools = $isAdmin ? Database::query("SELECT id, name FROM schools WHERE status='active' ORDER BY name")->fetchAll() : [];
        return $this->view('subjects/index', compact('subjects', 'schools', 'selectedSchool'));
    }

    public function store(): string
    {
        $this->validateCsrf();
        $name    = trim((string) $this->input('name'));
        $code    = trim((string) $this->input('code'));
        $cat     = (string) $this->input('category', 'optional');
        $offered = (string) $this->input('is_offered', '1') === '1' ? 1 : 0;

        if (!in_array($cat, self::CATEGORIES, true)) {
            $cat = 'optional';
        }
        if ($name === '') {
            Flash::set('danger', 'Subject name is required.');
            $this->redirect('/subjects');
            return '';
        }
        $schoolId = Auth::schoolId() ?? (int) $this->input('school_id', 0) ?: 1;
        Database::query(
            "INSERT INTO subjects (school_id, name, code, category, is_offered) VALUES (?, ?, ?, ?, ?)",
            [$schoolId, $name, $code, $cat, $offered]
        );
        Flash::set('success', 'Subject added.');
        $this->redirect('/subjects');
        return '';
    }

    /**
     * Bulk update of `is_offered`. Scoped to the current user's school.
     */
    public function updateOffered(): string
    {
        $this->validateCsrf();
        $on = array_map('intval', (array) ($_POST['offered'] ?? []));
        $on = array_values(array_unique(array_filter($on, static fn ($v) => $v > 0)));

        $schoolId = Auth::schoolId();
        $sf = $schoolId !== null ? ' AND school_id = ?' : '';
        $sp = $schoolId !== null ? [$schoolId] : [];

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE subjects SET is_offered = 0 WHERE 1=1{$sf}")->execute($sp);
            if ($on) {
                $place = implode(',', array_fill(0, count($on), '?'));
                $pdo->prepare("UPDATE subjects SET is_offered = 1 WHERE id IN ($place){$sf}")
                    ->execute(array_merge($on, $sp));
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Flash::set('danger', 'Could not save curriculum: ' . $e->getMessage());
            $this->redirect('/subjects');
            return '';
        }

        Flash::set('success', count($on) . ' subject' . (count($on) === 1 ? '' : 's') . ' offered. The rest are hidden from mark entry and report cards.');
        $this->redirect('/subjects');
        return '';
    }

    public function destroy(string $id): string
    {
        $this->validateCsrf();
        $schoolId = Auth::schoolId();
        if ($schoolId !== null) {
            Database::query("DELETE FROM subjects WHERE id = ? AND school_id = ?", [(int) $id, $schoolId]);
        } else {
            Database::query("DELETE FROM subjects WHERE id = ?", [(int) $id]);
        }
        Flash::set('success', 'Subject removed.');
        $this->redirect('/subjects');
        return '';
    }
}
