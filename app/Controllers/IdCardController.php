<?php
namespace App\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;
use App\Core\SchoolIdentity;
use App\Core\Settings;

class IdCardController extends Controller
{
    /**
     * A school_admin may only touch their own school's ID card theme; the
     * super admin (Auth::schoolId() === null) may touch any school's.
     */
    private function deniedForSchool(int $schoolId): bool
    {
        return Auth::role() === 'school_admin' && Auth::schoolId() !== $schoolId;
    }

    /** GET /schools/{id}/id-card-theme */
    public function themeForm(string $id): string
    {
        $schoolId = (int) $id;
        if ($this->deniedForSchool($schoolId)) {
            http_response_code(403);
            return $this->view('errors/403');
        }

        $school = Database::query(
            "SELECT id, name, logo, id_card_theme FROM schools WHERE id = ? LIMIT 1",
            [$schoolId]
        )->fetch();
        if (!$school) {
            http_response_code(404);
            return $this->view('errors/404');
        }

        return $this->view('schools/id_card_theme', [
            'school' => $school,
            'themes' => Settings::themes(),
        ]);
    }

    /** POST /schools/{id}/id-card-theme */
    public function themeUpdate(string $id): string
    {
        $this->validateCsrf();
        $schoolId = (int) $id;
        if ($this->deniedForSchool($schoolId)) {
            http_response_code(403);
            return $this->view('errors/403');
        }

        $school = Database::query("SELECT id, name FROM schools WHERE id = ? LIMIT 1", [$schoolId])->fetch();
        if (!$school) {
            http_response_code(404);
            return $this->view('errors/404');
        }

        $key = (string) $this->input('id_card_theme', '');
        if (!array_key_exists($key, Settings::themes())) {
            Flash::set('danger', 'Pick one of the available color themes.');
            $this->redirect('/schools/' . $schoolId . '/id-card-theme');
            return '';
        }

        Database::query("UPDATE schools SET id_card_theme = ? WHERE id = ?", [$key, $schoolId]);
        ActivityLog::record('update', 'school', $schoolId, "Set ID card theme for {$school['name']} to '{$key}'");
        Flash::set('success', 'ID card theme updated.');
        $this->redirect('/schools/' . $schoolId . '/id-card-theme');
        return '';
    }

    /** GET /students/{id}/id-card — one student's printable ID card. */
    public function show(string $id): string
    {
        $schoolId = Auth::schoolId();
        $sql = "SELECT s.*, c.name AS class_name, c.level
                FROM students s
                LEFT JOIN classes c ON c.id = s.class_id
                WHERE s.id = ?";
        $params = [(int) $id];
        if ($schoolId !== null) {
            $sql .= ' AND s.school_id = ?';
            $params[] = $schoolId;
        }
        $sql .= ' LIMIT 1';

        $student = Database::query($sql, $params)->fetch();
        if (!$student) {
            http_response_code(404);
            return $this->view('errors/404');
        }

        $school = Database::query(
            "SELECT name, logo FROM schools WHERE id = ? LIMIT 1",
            [(int) $student['school_id']]
        )->fetch() ?: [];

        return $this->view('students/id_card', [
            'student' => $student,
            'school'  => $school,
            'theme'   => SchoolIdentity::idCardTheme((int) $student['school_id']),
        ]);
    }

    /** GET /students/id-cards?class_id= — one printable sheet for a whole class. */
    public function bulk(): string
    {
        $classId = (int) $this->input('class_id', 0);
        if ($classId <= 0) {
            Flash::set('danger', 'Choose a class to print ID cards for.');
            $this->redirect('/classes');
            return '';
        }

        $schoolId = Auth::schoolId();
        $classSql = "SELECT id, name, school_id FROM classes WHERE id = ?";
        $classParams = [$classId];
        if ($schoolId !== null) {
            $classSql .= ' AND school_id = ?';
            $classParams[] = $schoolId;
        }
        $class = Database::query($classSql, $classParams)->fetch();
        if (!$class) {
            http_response_code(404);
            return $this->view('errors/404');
        }

        $students = Database::query(
            "SELECT s.*, c.name AS class_name, c.level
             FROM students s
             LEFT JOIN classes c ON c.id = s.class_id
             WHERE s.class_id = ?
             ORDER BY s.last_name, s.first_name",
            [$classId]
        )->fetchAll();

        $school = Database::query(
            "SELECT name, logo FROM schools WHERE id = ? LIMIT 1",
            [(int) $class['school_id']]
        )->fetch() ?: [];

        return $this->view('students/id_cards_bulk', [
            'students'  => $students,
            'className' => $class['name'],
            'school'    => $school,
            'theme'     => SchoolIdentity::idCardTheme((int) $class['school_id']),
        ]);
    }
}
