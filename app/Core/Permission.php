<?php
namespace App\Core;

/**
 * Role- and user-level permissions.
 *
 * Access is decided in three layers, most specific first:
 *
 *   1. `user_permissions`  — a per-account grant (1) or revoke (0). Set by an
 *      admin on one user, overrides everything below.
 *   2. `role_permissions`  — the per-school capability matrix an admin edits
 *      at /users/permissions. One row per (school, role, permission).
 *   3. self::DEFAULTS      — the built-in matrix. It mirrors exactly what each
 *      role could reach before permissions existed, so an install that has
 *      never opened the matrix page behaves the same as it always did.
 *
 * The super admin (role='admin') is never restricted: they own the matrix, so
 * locking themselves out of it has to be impossible.
 */
final class Permission
{
    /** Grouped catalogue: group label => [permission key => human label]. */
    public const CATALOG = [
        'Students & academics' => [
            'students.view'      => 'View students',
            'students.manage'    => 'Add / edit / delete students',
            'students.import'    => 'Bulk import & mass delete students',
            'classes.view'       => 'View classes',
            'classes.manage'     => 'Create & edit classes',
            'subjects.view'      => 'View subjects',
            'subjects.manage'    => 'Create & curate subjects',
            'teaching.manage'    => 'Assign teaching & department heads',
            'attendance.manage'  => 'Record attendance',
        ],
        'Assessment' => [
            'marks.enter'     => 'Enter & edit marks',
            'results.view'    => 'View computed results',
            'reports.view'    => 'View & print report cards',
            'analytics.view'  => 'View performance analytics',
        ],
        'People & accounts' => [
            'staff.manage'    => 'Manage staff records',
            'users.manage'    => 'Manage user accounts & permissions',
            'accounts.hod'    => 'Manage HOD accounts',
            'accounts.bursar' => 'Manage bursar accounts',
            'accounts.parent' => 'Manage parent accounts',
        ],
        'Administration' => [
            'announcements.post' => 'Post announcements',
            'settings.manage'    => 'Change school settings & branding',
            'activity.view'      => 'View the activity log',
            'schools.manage'     => 'Manage schools (super admin)',
        ],
    ];

    /**
     * Built-in matrix. Anything not listed for a role is denied.
     *
     * @var array<string, list<string>>
     */
    private const DEFAULTS = [
        'admin' => ['*'],
        'school_admin' => [
            'students.view', 'students.manage', 'students.import',
            'classes.view', 'classes.manage',
            'subjects.view', 'subjects.manage',
            'teaching.manage', 'attendance.manage',
            'marks.enter', 'results.view', 'reports.view', 'analytics.view',
            'staff.manage', 'users.manage',
            'accounts.hod', 'accounts.bursar', 'accounts.parent',
            'announcements.post', 'activity.view',
        ],
        'staff' => [
            'students.view', 'classes.view', 'subjects.view',
            'attendance.manage', 'marks.enter', 'results.view',
            'reports.view', 'analytics.view', 'announcements.post',
        ],
        'hod' => [
            'students.view', 'subjects.view',
            'marks.enter', 'results.view', 'reports.view', 'analytics.view',
            'announcements.post',
        ],
        'bursar'  => [],
        'parent'  => ['reports.view'],
        'student' => ['reports.view'],
    ];

    /** Roles an admin can tune in the permissions matrix. */
    public const EDITABLE_ROLES = [
        'school_admin' => 'School Admin',
        'staff'        => 'Teacher / Staff',
        'hod'          => 'Head of Department',
        'bursar'       => 'Bursar',
        'parent'       => 'Parent',
        'student'      => 'Student',
    ];

    /** @var array<string,bool>|null per-request cache of the signed-in user's effective set */
    private static ?array $cache = null;
    private static ?bool $tablesReady = null;

    /** @return list<string> every permission key in the catalogue */
    public static function keys(): array
    {
        $out = [];
        foreach (self::CATALOG as $group) {
            foreach ($group as $key => $_label) {
                $out[] = $key;
            }
        }
        return $out;
    }

    public static function label(string $key): string
    {
        foreach (self::CATALOG as $group) {
            if (isset($group[$key])) {
                return $group[$key];
            }
        }
        return $key;
    }

    /** The built-in answer for a role, used when nothing overrides it. */
    public static function defaultAllows(string $role, string $key): bool
    {
        $set = self::DEFAULTS[$role] ?? [];
        return in_array('*', $set, true) || in_array($key, $set, true);
    }

    /**
     * Effective role matrix for one school: built-in defaults with any saved
     * `role_permissions` rows applied on top.
     *
     * @return array<string, array<string, bool>> role => key => allowed
     */
    public static function matrix(?int $schoolId): array
    {
        $matrix = [];
        foreach (self::EDITABLE_ROLES as $role => $_label) {
            foreach (self::keys() as $key) {
                $matrix[$role][$key] = self::defaultAllows($role, $key);
            }
        }
        foreach (self::savedRoleRows($schoolId) as $row) {
            $role = (string) $row['role'];
            $key  = (string) $row['permission'];
            if (isset($matrix[$role]) && array_key_exists($key, $matrix[$role])) {
                $matrix[$role][$key] = (bool) $row['allowed'];
            }
        }
        return $matrix;
    }

    /**
     * Does the signed-in user (or the given user row) hold this permission?
     */
    public static function allows(string $key, ?array $user = null): bool
    {
        if ($user === null) {
            $user = Auth::user();
            if (!$user) {
                return false;
            }
            if (self::$cache === null) {
                self::$cache = self::effectiveFor($user);
            }
            return self::$cache[$key] ?? false;
        }
        $set = self::effectiveFor($user);
        return $set[$key] ?? false;
    }

    /** True when the user holds at least one of the given permissions. */
    public static function any(array $keys): bool
    {
        foreach ($keys as $k) {
            if (self::allows($k)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Route guard. Sends unauthenticated users to /login (via Auth::require)
     * and shows a 403 to a signed-in user who lacks the permission.
     */
    public static function require(string $key, ?array $roles = null): void
    {
        Auth::require($roles);
        if (!self::allows($key)) {
            http_response_code(403);
            echo View::render('errors/403');
            exit;
        }
    }

    /** Middleware closure for app/routes.php. */
    public static function gate(string $key, ?array $roles = null): callable
    {
        return static function () use ($key, $roles): void {
            self::require($key, $roles);
        };
    }

    /**
     * The full effective permission set for one user row.
     *
     * @return array<string,bool>
     */
    public static function effectiveFor(array $user): array
    {
        $role     = (string) ($user['role'] ?? '');
        $userId   = (int) ($user['id'] ?? 0);
        $schoolId = isset($user['school_id']) && $user['school_id'] !== null
            ? (int) $user['school_id']
            : null;

        $set = [];
        foreach (self::keys() as $key) {
            $set[$key] = self::defaultAllows($role, $key);
        }

        // The super admin keeps everything, whatever the tables say.
        if ($role === 'admin') {
            return $set;
        }

        foreach (self::savedRoleRows($schoolId) as $row) {
            if ((string) $row['role'] !== $role) {
                continue;
            }
            $key = (string) $row['permission'];
            if (array_key_exists($key, $set)) {
                $set[$key] = (bool) $row['allowed'];
            }
        }

        foreach (self::savedUserRows($userId) as $row) {
            $key = (string) $row['permission'];
            if (array_key_exists($key, $set)) {
                $set[$key] = (bool) $row['allowed'];
            }
        }

        return $set;
    }

    /** Per-user overrides as key => bool, for the edit form. */
    public static function overridesFor(int $userId): array
    {
        $out = [];
        foreach (self::savedUserRows($userId) as $row) {
            $out[(string) $row['permission']] = (bool) $row['allowed'];
        }
        return $out;
    }

    /**
     * Replace the saved matrix for one role in one school.
     *
     * @param array<string,bool> $values key => allowed
     */
    public static function saveRole(?int $schoolId, string $role, array $values): void
    {
        if (!self::ensureTables()) {
            return;
        }
        $sid = $schoolId ?? 0;
        foreach (self::keys() as $key) {
            $allowed = !empty($values[$key]) ? 1 : 0;
            Database::query(
                'INSERT INTO role_permissions (school_id, role, permission, allowed)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE allowed = VALUES(allowed)',
                [$sid, $role, $key, $allowed]
            );
        }
        self::resetCaches();
    }

    /**
     * Replace one user's overrides. `$values` maps key => 'allow'|'deny';
     * anything else (including a missing key) clears the override so the user
     * falls back to their role.
     */
    public static function saveUserOverrides(int $userId, array $values): void
    {
        if (!self::ensureTables()) {
            return;
        }
        Database::query('DELETE FROM user_permissions WHERE user_id = ?', [$userId]);
        foreach (self::keys() as $key) {
            $choice = (string) ($values[$key] ?? '');
            if ($choice !== 'allow' && $choice !== 'deny') {
                continue;
            }
            Database::query(
                'INSERT INTO user_permissions (user_id, permission, allowed)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE allowed = VALUES(allowed)',
                [$userId, $key, $choice === 'allow' ? 1 : 0]
            );
        }
        self::resetCaches();
    }

    /** Drop every per-request cache (after a permission change). */
    public static function resetCaches(): void
    {
        self::$cache = null;
        self::$roleRowCache = [];
        self::$userRowCache = [];
    }

    /* ------------------------------------------------------------------ */

    /** @var array<int, list<array<string,mixed>>> */
    private static array $roleRowCache = [];
    /** @var array<int, list<array<string,mixed>>> */
    private static array $userRowCache = [];

    /** @return list<array<string,mixed>> */
    private static function savedRoleRows(?int $schoolId): array
    {
        $sid = $schoolId ?? 0;
        if (isset(self::$roleRowCache[$sid])) {
            return self::$roleRowCache[$sid];
        }
        return self::$roleRowCache[$sid] = self::selectOrCreate(
            'SELECT role, permission, allowed FROM role_permissions WHERE school_id = ?',
            [$sid]
        );
    }

    /** @return list<array<string,mixed>> */
    private static function savedUserRows(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        if (isset(self::$userRowCache[$userId])) {
            return self::$userRowCache[$userId];
        }
        return self::$userRowCache[$userId] = self::selectOrCreate(
            'SELECT permission, allowed FROM user_permissions WHERE user_id = ?',
            [$userId]
        );
    }

    /**
     * Run a permission lookup, creating the tables only if the query fails
     * because they are missing. Every authenticated request reads these, so
     * issuing CREATE TABLE IF NOT EXISTS up front would add two DDL
     * statements to every page load for the sake of a one-off install step.
     *
     * @return list<array<string,mixed>>
     */
    private static function selectOrCreate(string $sql, array $params): array
    {
        try {
            return Database::query($sql, $params)->fetchAll();
        } catch (\Throwable $e) {
            // Missing tables (or anything else) fall back to the built-in
            // matrix, after one attempt to create them for next time.
            if (self::ensureTables()) {
                try {
                    return Database::query($sql, $params)->fetchAll();
                } catch (\Throwable $inner) {
                    return [];
                }
            }
            return [];
        }
    }

    /**
     * Create the two permission tables when they are missing, so an install
     * that has not run database/migrate.php still works. Checked once per
     * request; a failure just means "fall back to the built-in matrix".
     */
    public static function ensureTables(): bool
    {
        if (self::$tablesReady !== null) {
            return self::$tablesReady;
        }
        try {
            $pdo = Database::connection();
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS role_permissions (
                    school_id  INT UNSIGNED NOT NULL DEFAULT 0,
                    role       VARCHAR(30)  NOT NULL,
                    permission VARCHAR(60)  NOT NULL,
                    allowed    TINYINT(1)   NOT NULL DEFAULT 0,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (school_id, role, permission)
                ) ENGINE=InnoDB'
            );
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS user_permissions (
                    user_id    INT UNSIGNED NOT NULL,
                    permission VARCHAR(60)  NOT NULL,
                    allowed    TINYINT(1)   NOT NULL DEFAULT 0,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (user_id, permission),
                    CONSTRAINT fk_up_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB'
            );
            return self::$tablesReady = true;
        } catch (\Throwable $e) {
            return self::$tablesReady = false;
        }
    }
}
