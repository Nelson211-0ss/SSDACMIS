<?php
namespace App\Core;

/**
 * Audit trail: who did what, when. Writes are best-effort — a logging
 * failure must never break the user-facing action it's describing.
 */
class ActivityLog
{
    public static function record(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        string $description = ''
    ): void {
        try {
            $user = Auth::user();
            Database::query(
                'INSERT INTO activity_log
                    (school_id, user_id, user_name, role, action, entity_type, entity_id, description, ip_address)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    Auth::schoolId(),
                    $user['id'] ?? null,
                    $user['name'] ?? null,
                    $user['role'] ?? null,
                    $action,
                    $entityType,
                    $entityId,
                    $description,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                ]
            );
        } catch (\Throwable $e) {
            ErrorHandler::log($e);
        }
    }
}
