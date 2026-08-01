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
        $isAdmin    = $role === 'admin';
        $isAdminish = in_array($role, ['admin', 'school_admin', 'staff'], true);

        // Scoping: school_admin and all non-admin roles see only their school.
        $schoolId = Auth::schoolId();
        $sf  = $schoolId !== null ? ' AND school_id = ?' : '';
        $sp  = $schoolId !== null ? [$schoolId] : [];

        // ---------- Top-line counts ----------
        $studentsTotal   = (int) (Database::query("SELECT COUNT(*) c FROM students  WHERE 1=1{$sf}", $sp)->fetch()['c'] ?? 0);
        $staffTotal      = (int) (Database::query("SELECT COUNT(*) c FROM staff     WHERE 1=1{$sf}", $sp)->fetch()['c'] ?? 0);
        $classesTotal    = (int) (Database::query("SELECT COUNT(*) c FROM classes   WHERE 1=1{$sf}", $sp)->fetch()['c'] ?? 0);
        $subjectsTotal   = (int) (Database::query("SELECT COUNT(*) c FROM subjects  WHERE 1=1{$sf}", $sp)->fetch()['c'] ?? 0);
        $subjectsOffered = (int) (Database::query("SELECT COUNT(*) c FROM subjects  WHERE is_offered=1{$sf}", $sp)->fetch()['c'] ?? 0);

        $stats = [
            'students' => $studentsTotal,
            'staff'    => $staffTotal,
            'classes'  => $classesTotal,
            'subjects' => $subjectsTotal,
        ];

        // ---------- Month-over-month deltas (admin/staff only) ----------
        $deltas = [
            'students_this_month' => 0,
            'students_last_month' => 0,
            'staff_this_month'    => 0,
            'subjects_offered'    => $subjectsOffered,
        ];

        if ($isAdminish) {
            $deltas['students_this_month'] = (int) (Database::query(
                "SELECT COUNT(*) c FROM students
                 WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01'){$sf}",
                $sp
            )->fetch()['c'] ?? 0);
            $deltas['students_last_month'] = (int) (Database::query(
                "SELECT COUNT(*) c FROM students
                 WHERE created_at >= DATE_FORMAT(NOW() - INTERVAL 1 MONTH, '%Y-%m-01')
                   AND created_at <  DATE_FORMAT(NOW(), '%Y-%m-01'){$sf}",
                $sp
            )->fetch()['c'] ?? 0);
            $deltas['staff_this_month'] = (int) (Database::query(
                "SELECT COUNT(*) c FROM staff
                 WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01'){$sf}",
                $sp
            )->fetch()['c'] ?? 0);
        }

        // ---------- Enrollment charts: per-school (super admin) / per-class (school scope) ----------
        $schoolDistribution = [];
        $classDistribution  = [];
        if ($isAdminish) {
            if ($isAdmin) {
                $schoolDistribution = Database::query(
                    "SELECT s.id, s.name, s.code, COUNT(st.id) AS total
                     FROM schools s
                     LEFT JOIN students st ON st.school_id = s.id
                     GROUP BY s.id, s.name, s.code
                     ORDER BY s.name ASC"
                )->fetchAll();
            }
            $classDistribution = Database::query(
                "SELECT c.id, c.name, COALESCE(c.level, '') AS level, COUNT(s.id) AS total
                 FROM classes c
                 LEFT JOIN students s ON s.class_id = c.id" .
                ($schoolId !== null ? ' WHERE c.school_id = ?' : '') .
                " GROUP BY c.id, c.name, c.level ORDER BY c.name ASC",
                $sp
            )->fetchAll();
        }

        // ---------- Demographic breakdowns ----------
        $genderBreakdown  = ['male' => 0, 'female' => 0, 'other' => 0];
        $sectionBreakdown = ['day' => 0, 'boarding' => 0];
        $streamBreakdown  = ['none' => 0, 'science' => 0, 'arts' => 0];

        if ($isAdminish) {
            foreach (Database::query("SELECT gender,  COUNT(*) c FROM students WHERE 1=1{$sf} GROUP BY gender",  $sp)->fetchAll() as $r) {
                $genderBreakdown[$r['gender']] = (int) $r['c'];
            }
            foreach (Database::query("SELECT section, COUNT(*) c FROM students WHERE 1=1{$sf} GROUP BY section", $sp)->fetchAll() as $r) {
                $sectionBreakdown[$r['section']] = (int) $r['c'];
            }
            foreach (Database::query("SELECT stream,  COUNT(*) c FROM students WHERE 1=1{$sf} GROUP BY stream",  $sp)->fetchAll() as $r) {
                $streamBreakdown[$r['stream']] = (int) $r['c'];
            }
        }

        // ---------- Recently enrolled students ----------
        $recentStudents = [];
        if ($isAdminish) {
            $recentStudents = Database::query(
                "SELECT s.id, s.first_name, s.last_name, s.admission_no, s.gender,
                        s.section, s.created_at, c.name AS class_name,
                        sc.name AS school_name
                 FROM students s
                 LEFT JOIN classes c  ON c.id  = s.class_id
                 LEFT JOIN schools sc ON sc.id = s.school_id
                 WHERE 1=1" . ($schoolId !== null ? " AND s.school_id = ?" : "") . "
                 ORDER BY s.id DESC LIMIT 6",
                $sp
            )->fetchAll();
        }

        // ---------- Latest announcements ----------
        $announcements = Database::query(
            "SELECT a.title, a.body, a.created_at, COALESCE(u.name, 'System') AS author
             FROM announcements a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE 1=1" . ($schoolId !== null ? " AND a.school_id = ?" : "") . "
             ORDER BY a.created_at DESC LIMIT 5",
            $sp
        )->fetchAll();

        // ---------- Admin-only operational snapshot ----------
        $adminOps = [
            'hod_count'              => 0,
            'bursar_count'           => 0,
            'teaching_assignments'   => 0,
            'unassigned_students'    => 0,
            'attendance_today'       => ['present' => 0, 'absent' => 0, 'late' => 0, 'total' => 0],
        ];

        if ($isAdmin || $role === 'school_admin') {
            $adminOps['hod_count'] = (int) (Database::query(
                "SELECT COUNT(DISTINCT dh.staff_id) c FROM department_heads dh
                 JOIN staff s ON s.id = dh.staff_id WHERE 1=1" .
                ($schoolId !== null ? " AND s.school_id = ?" : ""),
                $sp
            )->fetch()['c'] ?? 0);
            $adminOps['bursar_count'] = (int) (Database::query(
                "SELECT COUNT(*) c FROM users WHERE role = 'bursar' AND status = 'active'{$sf}",
                $sp
            )->fetch()['c'] ?? 0);
            $adminOps['teaching_assignments'] = (int) (Database::query(
                "SELECT COUNT(*) c FROM teaching_assignments ta
                 JOIN staff s ON s.id = ta.staff_id WHERE 1=1" .
                ($schoolId !== null ? " AND s.school_id = ?" : ""),
                $sp
            )->fetch()['c'] ?? 0);
            $adminOps['unassigned_students'] = (int) (Database::query(
                "SELECT COUNT(*) c FROM students WHERE class_id IS NULL{$sf}",
                $sp
            )->fetch()['c'] ?? 0);

            foreach (Database::query(
                "SELECT status, COUNT(*) c FROM attendance
                 WHERE date = CURDATE(){$sf} GROUP BY status",
                $sp
            )->fetchAll() as $row) {
                $st  = (string) ($row['status'] ?? '');
                $cnt = (int) ($row['c'] ?? 0);
                if (isset($adminOps['attendance_today'][$st])) {
                    $adminOps['attendance_today'][$st] = $cnt;
                }
                $adminOps['attendance_today']['total'] += $cnt;
            }
        }

        // ---------- Student's own summary (attendance, grades, fees) ----------
        $studentSummary = null;
        if ($role === 'student') {
            $studentRow = Database::query(
                "SELECT s.id, c.name AS class_name
                 FROM students s
                 LEFT JOIN classes c ON c.id = s.class_id
                 WHERE s.user_id = ? LIMIT 1",
                [(int) (Auth::user()['id'] ?? 0)]
            )->fetch();

            if ($studentRow) {
                $studentId = (int) $studentRow['id'];

                $attRow = Database::query(
                    "SELECT SUM(status = 'present') AS present, COUNT(*) AS total
                     FROM attendance WHERE student_id = ?",
                    [$studentId]
                )->fetch();
                $attTotal   = (int) ($attRow['total'] ?? 0);
                $attRatePct = $attTotal > 0 ? (int) round(((int) $attRow['present']) / $attTotal * 100) : null;

                $gradeRow = Database::query(
                    "SELECT AVG(score) AS avg_score, COUNT(*) AS c FROM grades WHERE student_id = ?",
                    [$studentId]
                )->fetch();
                $avgScore = ((int) ($gradeRow['c'] ?? 0)) > 0 ? round((float) $gradeRow['avg_score'], 1) : null;

                $feeRow = Database::query(
                    "SELECT COALESCE(SUM(total_amount), 0) AS total, COALESCE(SUM(paid_amount), 0) AS paid
                     FROM student_fees WHERE student_id = ?",
                    [$studentId]
                )->fetch();

                $studentSummary = [
                    'class_name'      => $studentRow['class_name'] ?? null,
                    'attendance_rate' => $attRatePct,
                    'attendance_total' => $attTotal,
                    'avg_score'       => $avgScore,
                    'grades_count'    => (int) ($gradeRow['c'] ?? 0),
                    'fee_balance'     => (float) $feeRow['total'] - (float) $feeRow['paid'],
                ];
            }
        }

        $schoolProfile = null;
        if ($schoolId !== null) {
            $schoolProfile = Database::query(
                'SELECT id, name, code, email, phone, address, status,
                        motto, logo, headteacher_name, headteacher_title
                 FROM schools WHERE id = ? LIMIT 1',
                [$schoolId]
            )->fetch();
        }

        // ---- Super-admin platform overview (per-school breakdown) ----
        $schoolsOverview   = [];
        $platformTotals    = [];
        if ($isAdmin) {
            $schoolsOverview = Database::query(
                "SELECT
                    s.id, s.name, s.code, s.status, s.logo,
                    COUNT(DISTINCT st.id)  AS student_count,
                    COUNT(DISTINCT sf.id)  AS staff_count,
                    COUNT(DISTINCT dh.staff_id) AS hod_count,
                    COUNT(DISTINCT u_b.id) AS bursar_count,
                    COUNT(DISTINCT cl.id)  AS class_count,
                    SUM(CASE WHEN st.gender = 'male'   THEN 1 ELSE 0 END) AS male_count,
                    SUM(CASE WHEN st.gender = 'female' THEN 1 ELSE 0 END) AS female_count
                 FROM schools s
                 LEFT JOIN students           st  ON st.school_id  = s.id
                 LEFT JOIN staff              sf  ON sf.school_id  = s.id
                 LEFT JOIN classes            cl  ON cl.school_id  = s.id
                 LEFT JOIN staff              sf2 ON sf2.school_id = s.id
                 LEFT JOIN department_heads   dh  ON dh.staff_id   = sf2.id
                 LEFT JOIN users              u_b ON u_b.school_id = s.id
                                                 AND u_b.role = 'bursar'
                                                 AND u_b.status = 'active'
                 GROUP BY s.id
                 ORDER BY s.name ASC"
            )->fetchAll();

            // Build clean per-school aggregate (fix duplicate-join inflation).
            // The query above joins staff twice (sf + sf2) so school-count is doubled
            // for HODs. Re-fetch HODs separately to be safe.
            $hodBySchool = [];
            foreach (Database::query(
                "SELECT s.school_id, COUNT(DISTINCT dh.staff_id) c
                 FROM department_heads dh
                 JOIN staff s ON s.id = dh.staff_id
                 GROUP BY s.school_id"
            )->fetchAll() as $r) {
                $hodBySchool[(int) $r['school_id']] = (int) $r['c'];
            }

            foreach ($schoolsOverview as &$row) {
                $row['hod_count'] = $hodBySchool[(int) $row['id']] ?? 0;
            }
            unset($row);

            $platformTotals = [
                'schools'  => count($schoolsOverview),
                'active'   => count(array_filter($schoolsOverview, fn($r) => $r['status'] === 'active')),
                'students' => array_sum(array_column($schoolsOverview, 'student_count')),
                'staff'    => array_sum(array_column($schoolsOverview, 'staff_count')),
                'bursars'  => array_sum(array_column($schoolsOverview, 'bursar_count')),
                'hods'     => array_sum(array_column($schoolsOverview, 'hod_count')),
            ];
        }

        // ---------- Gender performance analysis ----------
        // School admin sees their own school's boys-vs-girls averages; the
        // super admin sees every school broken out separately — never
        // merged into one system-wide figure (schools aren't comparable
        // once averaged together). Both default to the most recently
        // synced (year, term) so the widget shows something meaningful
        // without forcing a period picker on the dashboard.
        $genderPerfPeriod   = null;
        $genderPerf         = null;
        $genderPerfBySchool = [];

        if ($role === 'school_admin' && $schoolId !== null) {
            $period = Database::query(
                "SELECT tsr.academic_year, tsr.term
                 FROM term_student_results tsr
                 JOIN students s ON s.id = tsr.student_id
                 WHERE s.school_id = ?
                 ORDER BY tsr.updated_at DESC LIMIT 1",
                [$schoolId]
            )->fetch();

            if ($period) {
                $genderPerfPeriod = ['year' => $period['academic_year'], 'term' => $period['term']];
                $rows = Database::query(
                    "SELECT s.gender, COUNT(*) AS n,
                            AVG(tsr.average_percentage) AS avg_pct,
                            SUM(CASE WHEN tsr.average_percentage >= 50 THEN 1 ELSE 0 END) AS passed
                     FROM term_student_results tsr
                     JOIN students s ON s.id = tsr.student_id
                     WHERE s.school_id = ? AND tsr.academic_year = ? AND tsr.term = ?
                           AND s.gender IN ('male', 'female')
                     GROUP BY s.gender",
                    [$schoolId, $genderPerfPeriod['year'], $genderPerfPeriod['term']]
                )->fetchAll();

                $genderPerf = ['male' => self::emptyGenderBucket(), 'female' => self::emptyGenderBucket()];
                foreach ($rows as $r) {
                    $genderPerf[$r['gender']] = self::genderBucketFromRow($r);
                }
            }
        } elseif ($isAdmin) {
            $period = Database::query(
                'SELECT academic_year, term FROM term_student_results ORDER BY updated_at DESC LIMIT 1'
            )->fetch();

            if ($period) {
                $genderPerfPeriod = ['year' => $period['academic_year'], 'term' => $period['term']];
                $rows = Database::query(
                    "SELECT sch.id AS school_id, sch.name AS school_name, s.gender,
                            COUNT(*) AS n,
                            AVG(tsr.average_percentage) AS avg_pct,
                            SUM(CASE WHEN tsr.average_percentage >= 50 THEN 1 ELSE 0 END) AS passed
                     FROM term_student_results tsr
                     JOIN students s ON s.id = tsr.student_id
                     JOIN schools sch ON sch.id = s.school_id
                     WHERE tsr.academic_year = ? AND tsr.term = ? AND s.gender IN ('male', 'female')
                     GROUP BY sch.id, sch.name, s.gender
                     ORDER BY sch.name",
                    [$genderPerfPeriod['year'], $genderPerfPeriod['term']]
                )->fetchAll();

                foreach ($rows as $r) {
                    $sid = (int) $r['school_id'];
                    $genderPerfBySchool[$sid] ??= [
                        'name'   => $r['school_name'],
                        'male'   => self::emptyGenderBucket(),
                        'female' => self::emptyGenderBucket(),
                    ];
                    $genderPerfBySchool[$sid][$r['gender']] = self::genderBucketFromRow($r);
                }
            }
        }

        return $this->view('dashboard/index', compact(
            'isAdmin',
            'stats',
            'deltas',
            'schoolDistribution',
            'classDistribution',
            'genderBreakdown',
            'sectionBreakdown',
            'streamBreakdown',
            'recentStudents',
            'announcements',
            'adminOps',
            'schoolProfile',
            'schoolsOverview',
            'platformTotals',
            'studentSummary',
            'genderPerfPeriod',
            'genderPerf',
            'genderPerfBySchool'
        ));
    }

    /** @return array{n:int,avg:?float,passed:int,passPct:?float} */
    private static function emptyGenderBucket(): array
    {
        return ['n' => 0, 'avg' => null, 'passed' => 0, 'passPct' => null];
    }

    /** @return array{n:int,avg:?float,passed:int,passPct:?float} */
    private static function genderBucketFromRow(array $r): array
    {
        $n = (int) $r['n'];
        return [
            'n'       => $n,
            'avg'     => $r['avg_pct'] !== null ? (float) $r['avg_pct'] : null,
            'passed'  => (int) $r['passed'],
            'passPct' => $n > 0 ? ((float) $r['passed'] / $n) * 100 : null,
        ];
    }
}
