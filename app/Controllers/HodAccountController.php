<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;

/**
 * Admin-only management of Head-of-Department login accounts.
 *
 *   GET  /hods               -> list every users.role='hod' account
 *   GET  /hods/create        -> "new HOD" form
 *   POST /hods               -> create the HOD (name, dept, email, password)
 *   GET  /hods/{id}/edit     -> edit form
 *   POST /hods/{id}          -> update name/dept/email/status (+ optional password)
 *   POST /hods/{id}/delete   -> delete the user row
 *
 * HODs created here sign in at /login and land on /hod (HOD portal).
 * They have full Form 1–4 mark-entry access for every subject because
 * users.role='hod' already grants the same shared-HOD privileges that
 * MarksController and ReportController honour.
 *
 * The `department` column is **informational only** — it does NOT scope what
 * the HOD can grade. It just labels which department they head.
 */
class HodAccountController extends Controller
{
    public function index(): string
    {
        $hods = Database::query(
            "SELECT id, name, email, department, status, created_at
             FROM users WHERE role = 'hod'
             ORDER BY status DESC, name"
        )->fetchAll();

        return $this->view('hods/index', [
            'hods' => $hods,
        ]);
    }

    public function create(): string
    {
        return $this->view('hods/form', [
            'hod' => null,
        ]);
    }

    public function store(): string
    {
        $this->validateCsrf();
        $d = $this->payload();

        if ($d['name'] === '' || $d['email'] === '' || $d['password'] === '') {
            Flash::set('danger', 'Name, email and password are required.');
            $this->redirect('/hods/create');
            return '';
        }
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            Flash::set('danger', 'That email address looks invalid.');
            $this->redirect('/hods/create');
            return '';
        }
        if (strlen($d['password']) < 6) {
            Flash::set('danger', 'Password must be at least 6 characters.');
            $this->redirect('/hods/create');
            return '';
        }

        $exists = Database::query("SELECT 1 FROM users WHERE email = ? LIMIT 1", [$d['email']])->fetch();
        if ($exists) {
            Flash::set('danger', 'That email is already in use.');
            $this->redirect('/hods/create');
            return '';
        }

        Database::query(
            "INSERT INTO users (name, email, password, role, department, status)
             VALUES (?, ?, ?, 'hod', ?, ?)",
            [
                $d['name'],
                $d['email'],
                password_hash($d['password'], PASSWORD_DEFAULT),
                $d['department'] ?: null,
                $d['status'],
            ]
        );

        Flash::set('success', 'HOD account created. They can sign in at /login.');
        $this->redirect('/hods');
        return '';
    }

    public function edit(string $id): string
    {
        $hod = Database::query(
            "SELECT id, name, email, department, status FROM users WHERE id = ? AND role = 'hod' LIMIT 1",
            [(int) $id]
        )->fetch();
        if (!$hod) {
            http_response_code(404);
            return $this->view('errors/404');
        }
        return $this->view('hods/form', [
            'hod' => $hod,
        ]);
    }

    public function update(string $id): string
    {
        $this->validateCsrf();
        $hod = Database::query(
            "SELECT id, email FROM users WHERE id = ? AND role = 'hod' LIMIT 1",
            [(int) $id]
        )->fetch();
        if (!$hod) {
            http_response_code(404);
            return $this->view('errors/404');
        }

        $d = $this->payload();

        if ($d['name'] === '' || $d['email'] === '') {
            Flash::set('danger', 'Name and email are required.');
            $this->redirect('/hods/' . (int) $id . '/edit');
            return '';
        }
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            Flash::set('danger', 'That email address looks invalid.');
            $this->redirect('/hods/' . (int) $id . '/edit');
            return '';
        }

        // Email-uniqueness check (allow keeping the same email).
        $clash = Database::query(
            "SELECT 1 FROM users WHERE email = ? AND id <> ? LIMIT 1",
            [$d['email'], (int) $id]
        )->fetch();
        if ($clash) {
            Flash::set('danger', 'Another user already has that email.');
            $this->redirect('/hods/' . (int) $id . '/edit');
            return '';
        }

        if ($d['password'] !== '') {
            if (strlen($d['password']) < 6) {
                Flash::set('danger', 'Password must be at least 6 characters.');
                $this->redirect('/hods/' . (int) $id . '/edit');
                return '';
            }
            Database::query(
                "UPDATE users SET name = ?, email = ?, department = ?, status = ?, password = ?
                 WHERE id = ?",
                [
                    $d['name'],
                    $d['email'],
                    $d['department'] ?: null,
                    $d['status'],
                    password_hash($d['password'], PASSWORD_DEFAULT),
                    (int) $id,
                ]
            );
        } else {
            Database::query(
                "UPDATE users SET name = ?, email = ?, department = ?, status = ?
                 WHERE id = ?",
                [
                    $d['name'],
                    $d['email'],
                    $d['department'] ?: null,
                    $d['status'],
                    (int) $id,
                ]
            );
        }

        Flash::set('success', 'HOD account updated.');
        $this->redirect('/hods');
        return '';
    }

    public function destroy(string $id): string
    {
        $this->validateCsrf();
        $hod = Database::query(
            "SELECT id FROM users WHERE id = ? AND role = 'hod' LIMIT 1",
            [(int) $id]
        )->fetch();
        if (!$hod) {
            http_response_code(404);
            return $this->view('errors/404');
        }
        Database::query("DELETE FROM users WHERE id = ?", [(int) $id]);
        Flash::set('success', 'HOD account removed.');
        $this->redirect('/hods');
        return '';
    }

    /* -------- helpers --------------------------------------------------- */

    private function payload(): array
    {
        return [
            'name'       => trim((string) $this->input('name', '')),
            'email'      => trim((string) $this->input('email', '')),
            'department' => trim((string) $this->input('department', '')),
            'password'   => (string) $this->input('password', ''),
            'status'     => in_array($this->input('status'), ['active', 'disabled'], true)
                                ? (string) $this->input('status')
                                : 'active',
        ];
    }
}
