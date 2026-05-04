<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;

class DashboardController extends Controller
{
    public function index(): string
    {
        // HOD portal accounts use /hod as home, not the system-wide dashboard
        // (enforced in Auth::enforceHodScope too, before this runs).
        if (Auth::usesHodPortalNav()) {
            $this->redirect('/hod');
            return '';
        }

        $role       = Auth::role() ?? 'guest';
        $isAdminish = in_array($role, ['admin', 'staff'], true);

        // ---------- Top-line counts ----------
        $studentsTotal = (int) (Database::query("SELECT COUNT(*) c FROM students")->fetch()['c'] ?? 0);
        $staffTotal    = (int) (Database::query("SELECT COUNT(*) c FROM staff")->fetch()['c'] ?? 0);
        $classesTotal  = (int) (Database::query("SELECT COUNT(*) c FROM classes")->fetch()['c'] ?? 0);
        $subjectsTotal = (int) (Database::query("SELECT COUNT(*) c FROM subjects")->fetch()['c'] ?? 0);
        $subjectsOffered = (int) (Database::query("SELECT COUNT(*) c FROM subjects WHERE is_offered=1")->fetch()['c'] ?? 0);

        $stats = [
            'students' => $studentsTotal,
            'staff'    => $staffTotal,
            'classes'  => $classesTotal,
            'subjects' => $subjectsTotal,
        ];

        // ---------- Month-over-month deltas (admin/staff only) ----------
        // "How many students were added this month vs last month?" — simple,
        // robust signal for the KPI card sparkline indicators.
        $deltas = [
            'students_this_month' => 0,
            'students_last_month' => 0,
            'staff_this_month'    => 0,
            'subjects_offered'    => $subjectsOffered,
        ];

        if ($isAdminish) {
            $deltas['students_this_month'] = (int) (Database::query(
                "SELECT COUNT(*) c FROM students
                 WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"
            )->fetch()['c'] ?? 0);
            $deltas['students_last_month'] = (int) (Database::query(
                "SELECT COUNT(*) c FROM students
                 WHERE created_at >= DATE_FORMAT(NOW() - INTERVAL 1 MONTH, '%Y-%m-01')
                   AND created_at <  DATE_FORMAT(NOW(), '%Y-%m-01')"
            )->fetch()['c'] ?? 0);
            $deltas['staff_this_month'] = (int) (Database::query(
                "SELECT COUNT(*) c FROM staff
                 WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"
            )->fetch()['c'] ?? 0);
        }

        // ---------- Class distribution (drives the enrollment chart) ----------
        $classDistribution = [];
        if ($isAdminish) {
            $classDistribution = Database::query(
                "SELECT c.id, c.name, COALESCE(c.level, '') AS level, COUNT(s.id) AS total
                 FROM classes c
                 LEFT JOIN students s ON s.class_id = c.id
                 GROUP BY c.id, c.name, c.level
                 ORDER BY c.name ASC"
            )->fetchAll();
        }

        // ---------- Demographic breakdowns ----------
        $genderBreakdown  = ['male' => 0, 'female' => 0, 'other' => 0];
        $sectionBreakdown = ['day' => 0, 'boarding' => 0];
        $streamBreakdown  = ['none' => 0, 'science' => 0, 'arts' => 0];

        if ($isAdminish) {
            foreach (Database::query("SELECT gender, COUNT(*) c FROM students GROUP BY gender")->fetchAll() as $r) {
                $genderBreakdown[$r['gender']] = (int) $r['c'];
            }
            foreach (Database::query("SELECT section, COUNT(*) c FROM students GROUP BY section")->fetchAll() as $r) {
                $sectionBreakdown[$r['section']] = (int) $r['c'];
            }
            foreach (Database::query("SELECT stream, COUNT(*) c FROM students GROUP BY stream")->fetchAll() as $r) {
                $streamBreakdown[$r['stream']] = (int) $r['c'];
            }
        }

        // ---------- Recently enrolled students ----------
        $recentStudents = [];
        if ($isAdminish) {
            $recentStudents = Database::query(
                "SELECT s.id, s.first_name, s.last_name, s.admission_no, s.gender,
                        s.section, s.created_at, c.name AS class_name
                 FROM students s
                 LEFT JOIN classes c ON c.id = s.class_id
                 ORDER BY s.id DESC
                 LIMIT 6"
            )->fetchAll();
        }

        // ---------- Latest announcements (with author) ----------
        $announcements = Database::query(
            "SELECT a.title, a.body, a.created_at, COALESCE(u.name, 'System') AS author
             FROM announcements a
             LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.created_at DESC
             LIMIT 5"
        )->fetchAll();

        return $this->view('dashboard/index', compact(
            'stats',
            'deltas',
            'classDistribution',
            'genderBreakdown',
            'sectionBreakdown',
            'streamBreakdown',
            'recentStudents',
            'announcements'
        ));
    }
}
