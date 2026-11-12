<?php
namespace App\Core;

/**
 * Audit trail: who did what, when, from where. Writes are best-effort — a
 * logging failure must never break the user-facing action it's describing.
 *
 * Every row is stamped with the acting user's school, which is what keeps
 * one school's activity out of another's log. For events with no signed-in
 * user (a failed login) the caller passes the school it can infer, if any.
 */
class ActivityLog
{
    /** Entries older than this are deleted automatically (MySQL interval). */
    public const RETENTION = '7 DAY';

    /**
     * Activity logging is switched off for now: record() writes nothing and
     * the Activity Log page and menu item are removed. Set back to true (and
     * restore the route in routes.php and the nav item in layouts/app.php)
     * to turn it on again. Existing rows are left untouched.
     */
    public const ENABLED = false;

    /** Guard so a request never prunes twice, even if the marker can't be written. */
    private static bool $prunedThisRequest = false;

    /**
     * Delete activity older than one week.
     *
     * There is no cron on the server, so this rides on normal traffic: it is
     * called after every log write and when the Activity Log page opens, but
     * does real work at most once a day — a marker file under storage/logs
     * holds the date of the last run. The marker is claimed BEFORE deleting,
     * so concurrent requests on the same morning skip rather than all run it.
     * Best-effort like record(): a failure is logged, never shown.
     *
     * @return int rows deleted (0 when skipped)
     */
    public static function pruneOld(): int
    {
        if (self::$prunedThisRequest) {
            return 0;
        }
        self::$prunedThisRequest = true;

        $marker = dirname(__DIR__, 2) . '/storage/logs/activity-prune.txt';
        $today  = date('Y-m-d') . ' ' . self::RETENTION; // a retention change re-runs at once
        if (is_file($marker) && trim((string) @file_get_contents($marker)) === $today) {
            return 0;
        }
        @file_put_contents($marker, $today, LOCK_EX);

        try {
            return Database::query(
                'DELETE FROM activity_log WHERE created_at < (NOW() - INTERVAL ' . self::RETENTION . ')'
            )->rowCount();
        } catch (\Throwable $e) {
            ErrorHandler::log($e);
            return 0;
        }
    }

    public static function record(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        string $description = '',
        ?array $actor = null,
        ?int $schoolId = null
    ): void {
        if (!self::ENABLED) {
            return;
        }
        try {
            $user     = $actor ?? Auth::user();
            $schoolId = $schoolId ?? ($actor !== null
                ? (isset($actor['school_id']) ? (int) $actor['school_id'] : null)
                : Auth::schoolId());
            // The super admin has no school; keep their rows school-less
            // rather than inheriting whatever school they happen to view.
            if (($user['role'] ?? null) === 'admin') {
                $schoolId = null;
            }

            $base = [
                $schoolId,
                $user['id'] ?? null,
                $user['name'] ?? null,
                $user['role'] ?? null,
                $action,
                $entityType,
                $entityId,
                mb_substr($description, 0, 255),
                self::clientIp(),
            ];

            try {
                Database::query(
                    'INSERT INTO activity_log
                        (school_id, user_id, user_name, role, action, entity_type, entity_id,
                         description, ip_address, request_method, request_uri, user_agent)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    array_merge($base, [
                        isset($_SERVER['REQUEST_METHOD']) ? substr((string) $_SERVER['REQUEST_METHOD'], 0, 8) : null,
                        isset($_SERVER['REQUEST_URI'])
                            ? mb_substr((string) strtok((string) $_SERVER['REQUEST_URI'], '?'), 0, 255)
                            : null,
                        isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
                    ])
                );
            } catch (\PDOException $e) {
                // Migration for the extra columns hasn't run yet: keep logging
                // the original fields rather than losing the entry.
                Database::query(
                    'INSERT INTO activity_log
                        (school_id, user_id, user_name, role, action, entity_type, entity_id, description, ip_address)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    $base
                );
            }
        } catch (\Throwable $e) {
            ErrorHandler::log($e);
        }

        self::pruneOld();
    }

    private static function clientIp(): ?string
    {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }
}
