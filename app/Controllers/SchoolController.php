<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;

/**
 * Super-admin (role=admin) management of schools and their admin accounts.
 *
 *   GET  /schools              -> list all schools
 *   GET  /schools/create       -> new school form
 *   POST /schools              -> create school
 *   GET  /schools/{id}         -> school detail + school_admin list
 *   GET  /schools/{id}/edit    -> edit school form
 *   POST /schools/{id}         -> update school
 */
class SchoolController extends Controller
{
    public function index(): string
    {
        $schools = Database::query(
            "SELECT s.*,
                    COUNT(DISTINCT u.id) AS admin_count
             FROM schools s
             LEFT JOIN users u ON u.school_id = s.id AND u.role = 'school_admin'
             GROUP BY s.id
             ORDER BY s.created_at DESC"
        )->fetchAll();

        return $this->view('schools/index', compact('schools'));
    }

    public function create(): string
    {
        return $this->view('schools/form', ['school' => null]);
    }

    public function store(): string
    {
        $this->validateCsrf();
        $d = $this->payload();

        if ($d['name'] === '' || $d['code'] === '') {
            Flash::set('danger', 'School name and code are required.');
            $this->redirect('/schools/create');
            return '';
        }

        $clash = Database::query("SELECT 1 FROM schools WHERE code = ? LIMIT 1", [$d['code']])->fetch();
        if ($clash) {
            Flash::set('danger', 'A school with that code already exists.');
            $this->redirect('/schools/create');
            return '';
        }

        Database::query(
            "INSERT INTO schools (name, code, email, phone, address, status)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$d['name'], strtoupper($d['code']), $d['email'], $d['phone'], $d['address'], $d['status']]
        );

        Flash::set('success', "School \"{$d['name']}\" created.");
        $this->redirect('/schools');
        return '';
    }

    public function show(string $id): string
    {
        $school = Database::query("SELECT * FROM schools WHERE id = ? LIMIT 1", [(int) $id])->fetch();
        if (!$school) { http_response_code(404); return $this->view('errors/404'); }

        $admins = Database::query(
            "SELECT id, name, email, status, created_at
             FROM users WHERE school_id = ? AND role = 'school_admin'
             ORDER BY status DESC, name",
            [(int) $id]
        )->fetchAll();

        return $this->view('schools/show', compact('school', 'admins'));
    }

    public function edit(string $id): string
    {
        $school = Database::query("SELECT * FROM schools WHERE id = ? LIMIT 1", [(int) $id])->fetch();
        if (!$school) { http_response_code(404); return $this->view('errors/404'); }
        return $this->view('schools/form', compact('school'));
    }

    public function update(string $id): string
    {
        $this->validateCsrf();
        $school = Database::query("SELECT id FROM schools WHERE id = ? LIMIT 1", [(int) $id])->fetch();
        if (!$school) { http_response_code(404); return $this->view('errors/404'); }

        $d = $this->payload();

        if ($d['name'] === '' || $d['code'] === '') {
            Flash::set('danger', 'School name and code are required.');
            $this->redirect('/schools/' . (int) $id . '/edit');
            return '';
        }

        $clash = Database::query(
            "SELECT 1 FROM schools WHERE code = ? AND id <> ? LIMIT 1",
            [strtoupper($d['code']), (int) $id]
        )->fetch();
        if ($clash) {
            Flash::set('danger', 'Another school already uses that code.');
            $this->redirect('/schools/' . (int) $id . '/edit');
            return '';
        }

        Database::query(
            "UPDATE schools SET name=?, code=?, email=?, phone=?, address=?, status=? WHERE id=?",
            [$d['name'], strtoupper($d['code']), $d['email'], $d['phone'], $d['address'], $d['status'], (int) $id]
        );

        Flash::set('success', 'School updated.');
        $this->redirect('/schools/' . (int) $id);
        return '';
    }

    private function payload(): array
    {
        return [
            'name'    => trim((string) $this->input('name', '')),
            'code'    => trim((string) $this->input('code', '')),
            'email'   => trim((string) $this->input('email', '')),
            'phone'   => trim((string) $this->input('phone', '')),
            'address' => trim((string) $this->input('address', '')),
            'status'  => in_array($this->input('status'), ['active', 'inactive'], true)
                             ? (string) $this->input('status') : 'active',
        ];
    }
}
