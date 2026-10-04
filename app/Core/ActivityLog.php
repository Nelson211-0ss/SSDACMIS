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
    public static function record(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        string $description = '',
        ?array $actor = null,
        ?int $schoolId = null
    ): void {
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
    }

    private static function clientIp(): ?string
    {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }
}
