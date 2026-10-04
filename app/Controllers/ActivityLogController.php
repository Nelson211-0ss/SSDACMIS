<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;

class ActivityLogController extends Controller
{
    private const PAGE_SIZE = 50;

    /** Recognised action types — used to populate the filter dropdown. */
    private const ACTIONS = ['create', 'update', 'delete', 'export', 'login', 'login_failed', 'logout', 'denied'];

    public function index(): string
    {
        $isSuper    = Auth::role() === 'admin';
        // Only the global super admin ever sees across schools. Anyone else
        // is pinned to their own school — and an account that somehow has no
        // school sees nothing rather than everything.
        $schoolId   = $isSuper ? null : (Auth::schoolId() ?? 0);
        $q          = trim((string) $this->input('q', ''));
        $page       = max(1, (int) $this->input('page', 1));
        $filterSchool = $isSuper ? (int) $this->input('school_id', 0) : 0;
        $action     = trim((string) $this->input('action', ''));
        $entityType = trim((string) $this->input('entity_type', ''));
        $from       = trim((string) $this->input('from', ''));
        $to         = trim((string) $this->input('to', ''));

        $where  = [];
        $params = [];

        if ($schoolId !== null) {
            $where[]  = 'a.school_id = ?';
            $params[] = $schoolId;
        } elseif ($filterSchool > 0) {
            $where[]  = 'a.school_id = ?';
            $params[] = $filterSchool;
        }
        if ($q !== '') {
            $where[]  = '(a.user_name LIKE ? OR a.description LIKE ? OR a.ip_address LIKE ?)';
            $like     = '%' . $q . '%';
            array_push($params, $like, $like, $like);
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
             LIMIT " . self::PAGE_SIZE . ' OFFSET ' . (($page - 1) * self::PAGE_SIZE),
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

        $schools = $isSuper
            ? Database::query('SELECT id, name FROM schools ORDER BY name')->fetchAll()
            : [];

        return $this->view('activity-log/index', [
            'logs'         => $logs,
            'total'        => $total,
            'page'         => $page,
            'pages'        => max(1, (int) ceil($total / self::PAGE_SIZE)),
            'q'            => $q,
            'schools'      => $schools,
            'filterSchool' => $filterSchool,
            'action'       => $action,
            'entityType'   => $entityType,
            'from'         => $from,
            'to'           => $to,
            'actionTypes'  => self::ACTIONS,
            'entityTypes'  => $entityTypes,
            'isSuperAdmin' => $isSuper,
        ]);
    }
}
