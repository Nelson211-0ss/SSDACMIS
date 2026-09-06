<?php
namespace App\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Permission;

/**
 * User accounts and permissions, for both the super admin and a school admin.
 *
 *   GET  /users                      -> every account in scope
 *   GET  /users/create               -> new account form
 *   POST /users                      -> create
 *   GET  /users/{id}/edit            -> edit account + per-user permission overrides
 *   POST /users/{id}                 -> update (name, email, role, status, department, password)
 *   POST /users/{id}/status          -> enable / disable in one click
 *   POST /users/{id}/delete          -> delete the account
 *   GET  /users/permissions          -> role permission matrix
 *   POST /users/permissions          -> save the matrix
 *
 * Scope rules:
 *   - super admin (role='admin'): every school, every role, and may pick the
 *     school an account belongs to.
 *   - school admin: only accounts in their OWN school, and never a super
 *     admin. They cannot grant the 'admin' role either — that would be a
 *     privilege escalation out of their own tenant.
 *
 * Nobody can delete, disable or demote their own account: a locked-out
 * administrator has no way back in.
 */
class UserController extends Controller
{
    /** Roles a school admin may create and assign. */
    private const SCHOOL_ROLES = [
        'school_admin' => 'School Admin',
        'staff'        => 'Teacher / Staff',
        'hod'          => 'Head of Department',
        'bursar'       => 'Bursar',
        'parent'       => 'Parent',
        'student'      => 'Student',
    ];

    /** Everything above plus the global super admin. */
    private const ALL_ROLES = [
        'admin'        => 'Super Admin',
        'school_admin' => 'School Admin',
        'staff'        => 'Teacher / Staff',
        'hod'          => 'Head of Department',
        'bursar'       => 'Bursar',
        'parent'       => 'Parent',
        'student'      => 'Student',
    ];

    private function isSuperAdmin(): bool
    {
        return Auth::role() === 'admin';
    }

    /** @return array<string,string> role key => label, for the current viewer */
    private function assignableRoles(): array
    {
        return $this->isSuperAdmin() ? self::ALL_ROLES : self::SCHOOL_ROLES;
    }

    private function currentUserId(): int
    {
        $u = Auth::user();
        return (int) ($u['id'] ?? 0);
    }

    /**
     * Load one account, enforcing the viewer's scope. Returns null when the
     * account does not exist or is out of scope — callers turn that into a
     * 404 so a school admin cannot probe for accounts in other schools.
     */
    private function findInScope(int $id): ?array
    {
        $row = Database::query(
            'SELECT id, school_id, name, email, role, department, status, created_at
             FROM users WHERE id = ? LIMIT 1',
            [$id]
        )->fetch();
        if (!$row) {
            return null;
        }
        if ($this->isSuperAdmin()) {
            return $row;
        }
        $schoolId = Auth::schoolId();
        if ($schoolId === null) {
            return null;
        }
        if ((int) ($row['school_id'] ?? 0) !== $schoolId) {
            return null;
        }
        if ($row['role'] === 'admin') {
            return null; // a school admin never manages the super admin
        }
        return $row;
    }

    /** Schools the viewer may assign an account to. */
    private function schoolOptions(): array
    {
        if (!$this->isSuperAdmin()) {
            return [];
        }
        return Database::query('SELECT id, name, code FROM schools ORDER BY name')->fetchAll();
    }

    /* ----------------------------- list ---------------------------- */

    public function index(): string
    {
        $roleFilter   = (string) $this->input('role', '');
        $statusFilter = (string) $this->input('status', '');
        $schoolFilter = (int) $this->input('school_id', 0);
        $q            = trim((string) $this->input('q', ''));

        $where  = [];
        $params = [];

        $schoolId = Auth::schoolId();
        if (!$this->isSuperAdmin()) {
            // A school admin sees only their own school, and never the
            // global super admin account.
            $where[]  = 'u.school_id = ?';
            $params[] = $schoolId;
            $where[]  = "u.role <> 'admin'";
        } elseif ($schoolFilter > 0) {
            $where[]  = 'u.school_id = ?';
            $params[] = $schoolFilter;
        }

        if (isset($this->assignableRoles()[$roleFilter])) {
            $where[]  = 'u.role = ?';
            $params[] = $roleFilter;
        }
        if ($statusFilter === 'active' || $statusFilter === 'disabled') {
            $where[]  = 'u.status = ?';
            $params[] = $statusFilter;
        }
        if ($q !== '') {
            $where[]  = '(u.name LIKE ? OR u.email LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }

        $sql = 'SELECT u.id, u.school_id, u.name, u.email, u.role, u.department,
                       u.status, u.created_at, s.name AS school_name
                FROM users u
                LEFT JOIN schools s ON s.id = u.school_id';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY FIELD(u.role,'admin','school_admin','hod','staff','bursar','parent','student'), u.name";

        $users = Database::query($sql, $params)->fetchAll();

        // Per-role headline counts for the summary strip.
        $counts = ['total' => count($users), 'active' => 0, 'disabled' => 0, 'byRole' => []];
        foreach ($users as $u) {
            $counts[$u['status'] === 'active' ? 'active' : 'disabled']++;
            $role = (string) $u['role'];
            $counts['byRole'][$role] = ($counts['byRole'][$role] ?? 0) + 1;
        }

        // Accounts carrying at least one per-user override, so the list can
        // flag them without a query per row.
        $overridden = [];
        if (Permission::ensureTables() && $users) {
            $ids = array_map(static fn ($u) => (int) $u['id'], $users);
            $ph  = implode(',', array_fill(0, count($ids), '?'));
            foreach (
                Database::query(
                    "SELECT DISTINCT user_id FROM user_permissions WHERE user_id IN ($ph)",
                    $ids
                )->fetchAll() as $r
            ) {
                $overridden[(int) $r['user_id']] = true;
            }
        }

        return $this->view('users/index', [
            'users'        => $users,
            'counts'       => $counts,
            'roles'        => $this->assignableRoles(),
            'roleLabels'   => self::ALL_ROLES,
            'isSuperAdmin' => $this->isSuperAdmin(),
            'schools'      => $this->schoolOptions(),
            'overridden'   => $overridden,
            'filters'      => [
                'role'      => $roleFilter,
                'status'    => $statusFilter,
                'school_id' => $schoolFilter,
                'q'         => $q,
            ],
            'currentUserId' => $this->currentUserId(),
        ]);
    }

    /* ---------------------------- create --------------------------- */

    public function create(): string
    {
        return $this->view('users/form', [
            'user'         => null,
            'roles'        => $this->assignableRoles(),
            'isSuperAdmin' => $this->isSuperAdmin(),
            'schools'      => $this->schoolOptions(),
            'catalog'      => Permission::CATALOG,
            'overrides'    => [],
            'effective'    => [],
        ]);
    }

    public function store(): string
    {
        $this->validateCsrf();

        $name  = trim((string) $this->input('name', ''));
        $email = strtolower(trim((string) $this->input('email', '')));
        $role  = (string) $this->input('role', '');
        $dept  = trim((string) $this->input('department', ''));
        $pass  = (string) $this->input('password', '');

        if ($name === '' || $email === '') {
            Flash::set('danger', 'Name and email are required.');
            $this->redirect('/users/create');
            return '';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Flash::set('danger', 'That email address looks invalid.');
            $this->redirect('/users/create');
            return '';
        }
        if (!isset($this->assignableRoles()[$role])) {
            Flash::set('danger', 'Pick a role you are allowed to assign.');
            $this->redirect('/users/create');
            return '';
        }
        if (strlen($pass) < 8) {
            Flash::set('danger', 'The password must be at least 8 characters.');
            $this->redirect('/users/create');
            return '';
        }
        if (Database::query('SELECT 1 FROM users WHERE email = ? LIMIT 1', [$email])->fetch()) {
            Flash::set('danger', 'That email is already in use by another account.');
            $this->redirect('/users/create');
            return '';
        }

        $schoolId = $this->resolveSchoolIdFor($role);

        Database::query(
            'INSERT INTO users (school_id, name, email, password, role, department, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $schoolId,
                $name,
                $email,
                password_hash($pass, PASSWORD_DEFAULT),
                $role,
                $dept !== '' ? $dept : null,
                $this->input('status') === 'disabled' ? 'disabled' : 'active',
            ]
        );

        $newId = (int) Database::connection()->lastInsertId();
        Permission::saveUserOverrides($newId, (array) ($this->input('perm', []) ?: []));
        ActivityLog::record('create', 'user', $newId, "Created {$role} account for {$name}");

        Flash::set('success', "Account created for {$name}.");
        $this->redirect('/users');
        return '';
    }

    /* ----------------------------- edit ---------------------------- */

    public function edit(string $id): string
    {
        $user = $this->findInScope((int) $id);
        if (!$user) {
            http_response_code(404);
            return $this->view('errors/404');
        }

        return $this->view('users/form', [
            'user'         => $user,
            'roles'        => $this->assignableRoles(),
            'isSuperAdmin' => $this->isSuperAdmin(),
            'schools'      => $this->schoolOptions(),
            'catalog'      => Permission::CATALOG,
            'overrides'    => Permission::overridesFor((int) $user['id']),
            'effective'    => Permission::effectiveFor($user),
            'isSelf'       => (int) $user['id'] === $this->currentUserId(),
        ]);
    }

    public function update(string $id): string
    {
        $this->validateCsrf();

        $userId = (int) $id;
        $user   = $this->findInScope($userId);
        if (!$user) {
            http_response_code(404);
            return $this->view('errors/404');
        }
        $isSelf = ($userId === $this->currentUserId());

        $name  = trim((string) $this->input('name', ''));
        $email = strtolower(trim((string) $this->input('email', '')));
        $dept  = trim((string) $this->input('department', ''));
        $pass  = (string) $this->input('password', '');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Flash::set('danger', 'A name and a valid email address are required.');
            $this->redirect('/users/' . $userId . '/edit');
            return '';
        }
        $clash = Database::query(
            'SELECT 1 FROM users WHERE email = ? AND id <> ? LIMIT 1',
            [$email, $userId]
        )->fetch();
        if ($clash) {
            Flash::set('danger', 'That email is already in use by another account.');
            $this->redirect('/users/' . $userId . '/edit');
            return '';
        }

        // Role and status are locked on your own account, so an admin can
        // never demote or disable themselves out of the system.
        $role   = (string) $user['role'];
        $status = (string) $user['status'];
        if (!$isSelf) {
            $requested = (string) $this->input('role', $role);
            if (isset($this->assignableRoles()[$requested])) {
                $role = $requested;
            }
            $status = $this->input('status') === 'disabled' ? 'disabled' : 'active';
        }

        $schoolId = (int) ($user['school_id'] ?? 0) ?: null;
        if ($this->isSuperAdmin()) {
            $schoolId = $this->resolveSchoolIdFor($role);
        }

        if ($pass !== '') {
            if (strlen($pass) < 8) {
                Flash::set('danger', 'The password must be at least 8 characters.');
                $this->redirect('/users/' . $userId . '/edit');
                return '';
            }
            Database::query(
                'UPDATE users SET password = ? WHERE id = ?',
                [password_hash($pass, PASSWORD_DEFAULT), $userId]
            );
        }

        Database::query(
            'UPDATE users SET school_id = ?, name = ?, email = ?, role = ?, department = ?, status = ?
             WHERE id = ?',
            [$schoolId, $name, $email, $role, $dept !== '' ? $dept : null, $status, $userId]
        );

        Permission::saveUserOverrides($userId, (array) ($this->input('perm', []) ?: []));
        ActivityLog::record('update', 'user', $userId, "Updated account {$name} ({$role})");

        Flash::set('success', "Account {$name} updated.");
        $this->redirect('/users');
        return '';
    }

    /* -------------------------- status / delete -------------------- */

    public function toggleStatus(string $id): string
    {
        $this->validateCsrf();

        $userId = (int) $id;
        $user   = $this->findInScope($userId);
        if (!$user) {
            http_response_code(404);
            return $this->view('errors/404');
        }
        if ($userId === $this->currentUserId()) {
            Flash::set('warning', 'You cannot disable your own account.');
            $this->redirect('/users');
            return '';
        }

        $next = $user['status'] === 'active' ? 'disabled' : 'active';
        Database::query('UPDATE users SET status = ? WHERE id = ?', [$next, $userId]);
        ActivityLog::record('update', 'user', $userId, "Set {$user['name']} to {$next}");

        Flash::set('success', "{$user['name']} is now {$next}.");
        $this->redirect('/users');
        return '';
    }

    public function destroy(string $id): string
    {
        $this->validateCsrf();

        $userId = (int) $id;
        $user   = $this->findInScope($userId);
        if (!$user) {
            http_response_code(404);
            return $this->view('errors/404');
        }
        if ($userId === $this->currentUserId()) {
            Flash::set('warning', 'You cannot delete your own account.');
            $this->redirect('/users');
            return '';
        }
        if ($user['role'] === 'admin') {
            // Never leave the installation without a super admin.
            $others = (int) Database::query(
                "SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active' AND id <> ?",
                [$userId]
            )->fetchColumn();
            if ($others === 0) {
                Flash::set('danger', 'This is the last active super admin — create another one first.');
                $this->redirect('/users');
                return '';
            }
        }

        Database::query('DELETE FROM users WHERE id = ?', [$userId]);
        ActivityLog::record('delete', 'user', $userId, "Deleted account {$user['name']} ({$user['role']})");

        Flash::set('success', "Account {$user['name']} deleted.");
        $this->redirect('/users');
        return '';
    }

    /* ------------------------ permission matrix -------------------- */

    public function permissions(): string
    {
        $schoolId = $this->matrixScope();

        return $this->view('users/permissions', [
            'catalog'      => Permission::CATALOG,
            'roles'        => Permission::EDITABLE_ROLES,
            'matrix'       => Permission::matrix($schoolId),
            'isSuperAdmin' => $this->isSuperAdmin(),
            'schoolId'     => $schoolId,
            'schools'      => $this->schoolOptions(),
        ]);
    }

    public function savePermissions(): string
    {
        $this->validateCsrf();

        $schoolId = $this->matrixScope();
        $posted   = (array) ($this->input('perm', []) ?: []);

        foreach (Permission::EDITABLE_ROLES as $role => $_label) {
            $values = [];
            foreach (Permission::keys() as $key) {
                $values[$key] = !empty($posted[$role][$key]);
            }
            Permission::saveRole($schoolId, $role, $values);
        }

        ActivityLog::record(
            'update',
            'permissions',
            $schoolId ?? 0,
            'Updated the role permission matrix'
        );
        Flash::set('success', 'Permissions saved.');

        $back = '/users/permissions';
        if ($this->isSuperAdmin() && $schoolId !== null) {
            $back .= '?school_id=' . $schoolId;
        }
        $this->redirect($back);
        return '';
    }

    /* ------------------------------ helpers ------------------------ */

    /**
     * Which school's matrix is being edited. A school admin only ever edits
     * their own; the super admin picks one, or edits the global defaults
     * (school_id 0, stored as null here).
     */
    private function matrixScope(): ?int
    {
        if (!$this->isSuperAdmin()) {
            return Auth::schoolId();
        }
        $requested = (int) $this->input('school_id', 0);
        return $requested > 0 ? $requested : null;
    }

    /**
     * The school an account belongs to. Super admins are global (NULL);
     * everyone else belongs to a school, chosen by the super admin or
     * inherited from the school admin doing the creating.
     */
    private function resolveSchoolIdFor(string $role): ?int
    {
        if ($role === 'admin') {
            return null;
        }
        if (!$this->isSuperAdmin()) {
            return Auth::schoolId();
        }
        $requested = (int) $this->input('school_id', 0);
        return $requested > 0 ? $requested : null;
    }
}
