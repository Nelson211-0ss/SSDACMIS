<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;

class ActivityLogController extends Controller
{
    private const LIST_LIMIT = 200;

    /** Recognised action types — used to populate the filter dropdown. */
    private const ACTIONS = ['create', 'update', 'delete', 'login', 'logout'];

    public function index(): string
    {
        $schoolId   = Auth::schoolId(); // null = super admin sees every school
        $action     = trim((string) $this->input('action', ''));
        $entityType = trim((string) $this->input('entity_type', ''));
        $from       = trim((string) $this->input('from', ''));
        $to         = trim((string) $this->input('to', ''));

        $where  = [];
        $params = [];

        if ($schoolId !== null) {
            $where[]  = 'a.school_id = ?';
            $params[] = $schoolId;
        }
        if ($action !== '' && in_array($action, self::ACTIONS, true)) {
            $where[]  = 'a.action = ?';
            $params[] = $action;
        } else {
            $action = '';
        }
        if ($entityType !== '') {
            $where[]  = 'a.entity_type = ?';
            $params[] = $entityType;
        }
        if ($from !== '') {
            $where[]  = 'a.created_at >= ?';
            $params[] = $from . ' 00:00:00';
        }
        if ($to !== '') {
            $where[]  = 'a.created_at <= ?';
            $params[] = $to . ' 23:59:59';
        }

        $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

        $logs = Database::query(
            "SELECT a.*, sc.name AS school_name
             FROM activity_log a
             LEFT JOIN schools sc ON sc.id = a.school_id
             {$whereSql}
             ORDER BY a.created_at DESC
             LIMIT " . self::LIST_LIMIT,
            $params
        )->fetchAll();

        $total = (int) Database::query(
            "SELECT COUNT(*) FROM activity_log a {$whereSql}",
            $params
        )->fetchColumn();

        $entityTypes = Database::query(
            "SELECT DISTINCT entity_type FROM activity_log
             WHERE entity_type IS NOT NULL" . ($schoolId !== null ? ' AND school_id = ?' : '') . "
             ORDER BY entity_type",
            $schoolId !== null ? [$schoolId] : []
        )->fetchAll(\PDO::FETCH_COLUMN);

        return $this->view('activity-log/index', [
            'logs'         => $logs,
            'total'        => $total,
            'truncated'    => $total > count($logs),
            'listLimit'    => self::LIST_LIMIT,
            'action'       => $action,
            'entityType'   => $entityType,
            'from'         => $from,
            'to'           => $to,
            'actionTypes'  => self::ACTIONS,
            'entityTypes'  => $entityTypes,
            'isSuperAdmin' => $schoolId === null,
        ]);
    }
}
