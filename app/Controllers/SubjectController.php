<?php
namespace App\Controllers;

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

    public function index(): string
    {
        $subjects = Database::query(
            "SELECT id, name, code, category, is_offered FROM subjects
             ORDER BY FIELD(category, 'core','science','arts','optional'), name"
        )->fetchAll();
        return $this->view('subjects/index', compact('subjects'));
    }

    public function store(): string
    {
        $this->validateCsrf();
        $name = trim((string) $this->input('name'));
        $code = trim((string) $this->input('code'));
        $cat  = (string) $this->input('category', 'optional');
        $offered = (string) $this->input('is_offered', '1') === '1' ? 1 : 0;

        if (!in_array($cat, self::CATEGORIES, true)) {
            $cat = 'optional';
        }
        if ($name === '') {
            Flash::set('danger', 'Subject name is required.');
            $this->redirect('/subjects');
            return '';
        }
        Database::query(
            "INSERT INTO subjects (name, code, category, is_offered) VALUES (?, ?, ?, ?)",
            [$name, $code, $cat, $offered]
        );
        Flash::set('success', 'Subject added.');
        $this->redirect('/subjects');
        return '';
    }

    /**
     * Bulk update of `is_offered`. The form posts an `offered[]` array of
     * subject ids that should be ON; everything else is set to OFF.
     */
    public function updateOffered(): string
    {
        $this->validateCsrf();
        $on = array_map('intval', (array) ($_POST['offered'] ?? []));
        $on = array_values(array_unique(array_filter($on, static fn ($v) => $v > 0)));

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            // First flip everything OFF, then turn the selected ones back ON.
            $pdo->exec("UPDATE subjects SET is_offered = 0");
            if ($on) {
                $place = implode(',', array_fill(0, count($on), '?'));
                $stmt  = $pdo->prepare("UPDATE subjects SET is_offered = 1 WHERE id IN ($place)");
                $stmt->execute($on);
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
        Database::query("DELETE FROM subjects WHERE id = ?", [(int) $id]);
        Flash::set('success', 'Subject removed.');
        $this->redirect('/subjects');
        return '';
    }
}
