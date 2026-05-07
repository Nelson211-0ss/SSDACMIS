<?php
use App\Core\Router;
use App\Core\Auth;

$router = new Router();

// Public
$router->get('/', function () {
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    header('Location: ' . $base . '/login', true, 302);
    exit;
});
$router->get('/login',      'AuthController@showLogin');
$router->post('/login',     'AuthController@login');
$router->get('/hod/login',     'AuthController@showHodLogin');
$router->post('/hod/login',    'AuthController@hodLogin');
$router->get('/bursar/login',  'AuthController@showBursarLogin');
$router->post('/bursar/login', 'AuthController@bursarLogin');
$router->get('/logout',        'AuthController@logout');
// HOD/Bursar-portal logouts sit under their own URL prefixes on purpose:
// they share the URL prefix with the rest of their portal so portal
// detection lights up correctly and only that portal's session slot is
// cleared (admin in another tab is safe).
$router->get('/hod/logout',    'AuthController@logout');
$router->get('/bursar/logout', 'AuthController@logout');

// Authenticated area
$auth = fn() => Auth::require();
$adminOnly = fn() => Auth::require(['admin']);
$staffOrAdmin = fn() => Auth::require(['admin', 'staff']);
/** HOD shared account + staff HODs + admin — for /hod, /marks, class reports. */
$staffAdminOrHod = fn() => Auth::require(['admin', 'staff', 'hod']);
/** Bursar Fees Management portal — only role=bursar allowed. */
$bursarOnly = fn() => Auth::require(['bursar']);

$router->get('/dashboard', 'DashboardController@index', [$auth]);

// HOD landing page (auto-redirected from /dashboard for HODs)
$router->get('/hod',          'HodController@dashboard', [$staffAdminOrHod]);
$router->get('/hod/overview', 'HodController@overview', [$staffAdminOrHod]);
$router->get('/hod/students', 'HodController@students',  [$staffAdminOrHod]);

// Students
$router->get('/students',              'StudentController@index',  [$staffOrAdmin]);
$router->get('/students/print',        'StudentController@printRoster', [$adminOnly]);
$router->get('/students/admission-letters',           'StudentController@admissionLetters', [$adminOnly]);
$router->get('/students/{id}/admission-letter',       'StudentController@admissionLetter',  [$adminOnly]);
$router->get('/students/create',       'StudentController@create', [$staffOrAdmin]);
$router->get('/students/table-rows',   'StudentController@tableRows', [$staffOrAdmin]);
// Registered before POST /students/{id} so "clear-all" is never treated as an id.
$router->get('/students/clear-all',    'StudentController@clearAllForm',    [$adminOnly]);
$router->post('/students/clear-all',  'StudentController@clearAllExecute', [$adminOnly]);
$router->post('/students',             'StudentController@store',  [$staffOrAdmin]);
$router->get('/students/{id}/edit',    'StudentController@edit',   [$staffOrAdmin]);
$router->post('/students/{id}',        'StudentController@update', [$staffOrAdmin]);
$router->post('/students/{id}/delete', 'StudentController@destroy',[$adminOnly]);
$router->post('/students/{id}/photo/delete', 'StudentController@deletePhoto', [$staffOrAdmin]);

// Staff
$router->get('/staff',              'StaffController@index',  [$adminOnly]);
$router->get('/staff/create',       'StaffController@create', [$adminOnly]);
$router->post('/staff',             'StaffController@store',  [$adminOnly]);
$router->get('/staff/{id}/edit',    'StaffController@edit',   [$adminOnly]);
$router->post('/staff/{id}',        'StaffController@update', [$adminOnly]);
$router->post('/staff/{id}/delete', 'StaffController@destroy',[$adminOnly]);

// HOD accounts (admin creates Heads of Department who can sign in at /hod/login)
$router->get('/hods',              'HodAccountController@index',   [$adminOnly]);
$router->get('/hods/create',       'HodAccountController@create',  [$adminOnly]);
$router->post('/hods',             'HodAccountController@store',   [$adminOnly]);
$router->get('/hods/{id}/edit',    'HodAccountController@edit',    [$adminOnly]);
$router->post('/hods/{id}',        'HodAccountController@update',  [$adminOnly]);
$router->post('/hods/{id}/delete', 'HodAccountController@destroy', [$adminOnly]);

// Classes
$router->get('/classes',                'ClassController@index',     [$staffOrAdmin]);
$router->post('/classes',               'ClassController@store',     [$adminOnly]);
$router->post('/classes/{id}/teacher',  'ClassController@setTeacher',[$adminOnly]);
$router->post('/classes/{id}/prefix',   'ClassController@setPrefix', [$adminOnly]);
$router->post('/classes/{id}/delete',   'ClassController@destroy',   [$adminOnly]);

// Subjects
$router->get('/subjects',              'SubjectController@index',         [$staffOrAdmin]);
$router->post('/subjects',             'SubjectController@store',         [$adminOnly]);
$router->post('/subjects/offered',     'SubjectController@updateOffered', [$adminOnly]);
$router->post('/subjects/{id}/delete', 'SubjectController@destroy',       [$adminOnly]);

// Attendance
$router->get('/attendance',  'AttendanceController@index', [$staffOrAdmin]);
$router->post('/attendance', 'AttendanceController@store', [$staffOrAdmin]);

// Grades (legacy single-mark editor - kept for power users). Restricted to
// roles that actually maintain marks so non-staff sessions cannot enumerate
// the full student roster through this view.
$router->get('/grades',  'GradeController@index', [$staffAdminOrHod]);
$router->post('/grades', 'GradeController@store', [$staffOrAdmin]);

// Teaching assignments (admin: who teaches what; who heads which department)
$router->get('/teaching',                'TeachingController@index',       [$adminOnly]);
$router->post('/teaching',               'TeachingController@store',       [$adminOnly]);
$router->post('/teaching/{id}/delete',   'TeachingController@destroy',     [$adminOnly]);
$router->post('/teaching/heads',         'TeachingController@storeHead',   [$adminOnly]);
$router->post('/teaching/heads/delete',  'TeachingController@destroyHead', [$adminOnly]);

// Marks
//   Per-subject (teacher with a teaching_assignments row):
$router->get('/marks',             'MarksController@index',           [$staffAdminOrHod]);
$router->get('/marks/entry',       'MarksController@entry',           [$staffAdminOrHod]);
$router->post('/marks',            'MarksController@store',           [$staffAdminOrHod]);
//   Department-wide (HOD matrix entry — every subject in a department for a class):
$router->get('/marks/department',  'MarksController@departmentEntry', [$staffAdminOrHod]);
$router->post('/marks/department', 'MarksController@departmentStore', [$staffAdminOrHod]);
//   HOD-portal aliases — same controllers, but on URLs that fall inside the
//   /hod/* prefix so HOD sessions stay isolated from the main school portal.
$router->get('/hod/marks',             'MarksController@index',           [$staffAdminOrHod]);
$router->get('/hod/marks/entry',       'MarksController@entry',           [$staffAdminOrHod]);
$router->post('/hod/marks',            'MarksController@store',           [$staffAdminOrHod]);
$router->get('/hod/marks/department',  'MarksController@departmentEntry', [$staffAdminOrHod]);
$router->post('/hod/marks/department', 'MarksController@departmentStore', [$staffAdminOrHod]);

// Results (computed averages & positions — Mid ×/30 + End ×/70)
$router->get('/results',              'ResultsController@index',      [$staffAdminOrHod]);
$router->get('/results/class/{id}', 'ResultsController@classView',   [$staffAdminOrHod]);
$router->get('/hod/results',              'ResultsController@index',      [$staffAdminOrHod]);
$router->get('/hod/results/class/{id}', 'ResultsController@classView',   [$staffAdminOrHod]);

// Reports (printable mid-term & end-term report cards)
$router->get('/reports',                'ReportController@index',       [$auth]);
$router->get('/reports/student/{id}',   'ReportController@student',     [$auth]);
$router->get('/reports/class/{id}/booklet', 'ReportController@classBooklet', [$staffAdminOrHod]);
$router->get('/reports/class/{id}',     'ReportController@classReport', [$staffAdminOrHod]);
//   HOD-portal aliases (same handlers, /hod/* URLs).
$router->get('/hod/reports',                    'ReportController@index',       [$staffAdminOrHod]);
$router->get('/hod/reports/student/{id}',       'ReportController@student',     [$staffAdminOrHod]);
$router->get('/hod/reports/class/{id}/booklet', 'ReportController@classBooklet',[$staffAdminOrHod]);
$router->get('/hod/reports/class/{id}',         'ReportController@classReport', [$staffAdminOrHod]);

// Fees (legacy student self-view of their own balance — kept for /dashboard
// "My fees" link). The full Fees Management Module lives under /bursar/*.
$router->get('/fees',  'FeeController@index', [$auth]);

// Bursar accounts (admin creates Bursars who can sign in at /bursar/login).
$router->get('/bursars',              'BursarAccountController@index',   [$adminOnly]);
$router->get('/bursars/create',       'BursarAccountController@create',  [$adminOnly]);
$router->post('/bursars',             'BursarAccountController@store',   [$adminOnly]);
$router->get('/bursars/{id}/edit',    'BursarAccountController@edit',    [$adminOnly]);
$router->post('/bursars/{id}',        'BursarAccountController@update',  [$adminOnly]);
$router->post('/bursars/{id}/delete', 'BursarAccountController@destroy', [$adminOnly]);

// ============================================================
// Bursar / Fees Management portal — every route is bursar-only.
// All URLs sit under /bursar/* so the portal-aware Auth keeps
// the bursar session isolated from admin/HOD sessions in other
// tabs. Direct-URL access without a bursar login redirects to
// /bursar/login automatically.
// ============================================================
$router->get('/bursar',                       'BursarController@dashboard',       [$bursarOnly]);
$router->post('/bursar/period',               'BursarController@setPeriod',       [$bursarOnly]);
$router->get('/bursar/structure',             'BursarController@showStructure',   [$bursarOnly]);
$router->post('/bursar/structure',            'BursarController@saveStructure',   [$bursarOnly]);
$router->get('/bursar/students',              'BursarController@students',        [$bursarOnly]);
$router->get('/bursar/students/{id}',         'BursarController@studentDetail',   [$bursarOnly]);
$router->get('/bursar/payments',              'BursarController@payments',        [$bursarOnly]);
$router->post('/bursar/payments',             'BursarController@recordPayment',   [$bursarOnly]);
$router->get('/bursar/payments/{id}/receipt', 'BursarController@receipt',         [$bursarOnly]);
$router->get('/bursar/reports/paid',          'BursarController@reportPaid',      [$bursarOnly]);
$router->get('/bursar/reports/balances',      'BursarController@reportBalances',  [$bursarOnly]);
$router->get('/bursar/reports/print/{type}',  'BursarController@reportPrint',     [$bursarOnly]);
$router->get('/bursar/reports/export.csv',    'BursarController@exportCsv',       [$bursarOnly]);
// Examination permits — auto-issued only to fully paid students.
$router->get('/bursar/exam-permits',          'BursarController@examPermits',      [$bursarOnly]);
$router->get('/bursar/exam-permits/print',    'BursarController@examPermitsPrint', [$bursarOnly]);

// Announcements
$router->get('/announcements',  'AnnouncementController@index', [$auth]);
$router->post('/announcements', 'AnnouncementController@store', [$staffAdminOrHod]);
//   HOD-portal aliases.
$router->get('/hod/announcements',  'AnnouncementController@index', [$staffAdminOrHod]);
$router->post('/hod/announcements', 'AnnouncementController@store', [$staffAdminOrHod]);

// Settings (school identity + theme customization)
$router->get('/settings',  'SettingsController@index',  [$adminOnly]);
$router->post('/settings', 'SettingsController@update', [$adminOnly]);

return $router;
