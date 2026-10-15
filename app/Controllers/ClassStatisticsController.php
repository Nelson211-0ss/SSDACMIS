<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;

/**
 * Enrolment statistics per class: how many boys and girls each class has
 * and what share of the class each makes up.
 *
 *   GET /class-statistics
 *
 * School admin only, pinned to their own school.
 */
class ClassStatisticsController extends Controller
{
    public function index(): string
    {
        // An account with no school sees nothing rather than everything.
        $schoolId = Auth::schoolId() ?? 0;

        $rows = Database::query(
            "SELECT c.id, c.name, COALESCE(c.level, '') AS level,
                    COUNT(s.id) AS total,
                    COALESCE(SUM(CASE WHEN s.gender = 'male'   THEN 1 ELSE 0 END), 0) AS boys,
                    COALESCE(SUM(CASE WHEN s.gender = 'female' THEN 1 ELSE 0 END), 0) AS girls,
                    COALESCE(SUM(CASE WHEN s.gender = 'other'  THEN 1 ELSE 0 END), 0) AS other
             FROM classes c
             LEFT JOIN students s ON s.class_id = c.id
             WHERE c.school_id = ?
             GROUP BY c.id, c.name, c.level
             ORDER BY c.level, c.name",
            [$schoolId]
        )->fetchAll();

        // Students not placed in any class still count towards the school total.
        $unassigned = Database::query(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN gender = 'male'   THEN 1 ELSE 0 END), 0) AS boys,
                    COALESCE(SUM(CASE WHEN gender = 'female' THEN 1 ELSE 0 END), 0) AS girls,
                    COALESCE(SUM(CASE WHEN gender = 'other'  THEN 1 ELSE 0 END), 0) AS other
             FROM students
             WHERE school_id = ? AND class_id IS NULL",
            [$schoolId]
        )->fetch() ?: ['total' => 0, 'boys' => 0, 'girls' => 0, 'other' => 0];

        $classes = array_map([$this, 'withPercentages'], $rows);
        $unassigned = $this->withPercentages($unassigned + ['name' => 'No class', 'level' => '']);

        $totals = ['name' => 'Whole school', 'level' => '', 'total' => 0, 'boys' => 0, 'girls' => 0, 'other' => 0];
        foreach (array_merge($classes, [$unassigned]) as $c) {
            foreach (['total', 'boys', 'girls', 'other'] as $k) {
                $totals[$k] += $c[$k];
            }
        }

        return $this->view('class-statistics/index', [
            'classes'    => $classes,
            'unassigned' => $unassigned,
            'totals'     => $this->withPercentages($totals),
            'hasOther'   => $totals['other'] > 0,
        ]);
    }

    /** Cast the counts and add each gender's share of the row total. */
    private function withPercentages(array $row): array
    {
        $total = (int) $row['total'];
        foreach (['boys', 'girls', 'other'] as $k) {
            $row[$k] = (int) $row[$k];
            $row[$k . '_pct'] = $total > 0 ? ($row[$k] / $total) * 100 : null;
        }
        $row['total'] = $total;

        return $row;
    }
}
