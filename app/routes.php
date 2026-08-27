<?php
use App\Core\Router;
use App\Core\Auth;

$router = new Router();

// Public
$router->get('/',           'LandingController@index');
$router->get('/login',      'AuthController@showLogin');
$router->post('/login',     'AuthController@login');
$router->get('/hod/login',     'AuthController@showHodLogin');
$router->post('/hod/login',    'AuthController@hodLogin');
$router->get('/bursar/login',  'AuthController@showBursarLogin');
$router->post('/bursar/login', 'AuthController@bursarLogin');
$router->get('/parent/login',  'AuthController@showParentLogin');
$router->post('/parent/login', 'AuthController@parentLogin');
$router->get('/logout',        'AuthController@logout');
// HOD/Bursar/Parent-portal logouts sit under their own URL prefixes on
// purpose: they share the URL prefix with the rest of their portal so
// portal detection lights up correctly and only that portal's session slot
// is cleared (admin in another tab is safe).
$router->get('/hod/logout',    'AuthController@logout');
$router->get('/bursar/logout', 'AuthController@logout');
$router->get('/parent/logout', 'AuthController@logout');

// Authenticated area
$auth = fn() => Auth::require();
$adminOnly = fn() => Auth::require(['admin']);
/** Super admin only (global operations like school management). */
$superAdminOnly = fn() => Auth::require(['admin']);
/** School-level admin operations (admin or school_admin). */
$schoolAdminOrAdmin = fn() => Auth::require(['admin', 'school_admin']);
$staffOrAdmin = fn() => Auth::require(['admin', 'school_admin', 'staff']);
/** HOD shared account + staff HODs + admin — for /hod, /marks, class reports. */
$staffAdminOrHod = fn() => Auth::require(['admin', 'school_admin', 'staff', 'hod']);
/** Bursar Fees Management portal — only role=bursar allowed. */
$bursarOnly = fn() => Auth::require(['bursar']);
/** Parent portal — only role=parent allowed. */
$parentOnly = fn() => Auth::require(['parent']);

$router->get('/dashboard', 'DashboardController@index', [$auth]);

// HOD landing page (auto-redirected from /dashboard for HODs)
$router->get('/hod',          'HodController@dashboard', [$staffAdminOrHod]);
$router->get('/hod/overview', 'HodController@overview', [$staffAdminOrHod]);
$router->get('/hod/students', 'HodController@students',  [$staffAdminOrHod]);

// Students
$router->get('/students',              'StudentController@index',  [$staffOrAdmin]);
$router->get('/students/print',        'StudentController@printRoster', [$schoolAdminOrAdmin]);
$router->get('/students/admission-letters',           'StudentController@admissionLetters', [$schoolAdminOrAdmin]);
$router->get('/students/{id}/admission-letter',       'StudentController@admissionLetter',  [$schoolAdminOrAdmin]);
// ID cards — bulk-by-class registered here (before /students/{id}); the
// single-student route sits with the other /students/{id}/... routes below.
$router->get('/students/id-cards',     'IdCardController@bulk',    [$schoolAdminOrAdmin]);
$router->get('/students/{id}/id-card', 'IdCardController@show',    [$schoolAdminOrAdmin]);
$router->get('/students/create',       'StudentController@create', [$schoolAdminOrAdmin]);
$router->get('/students/table-rows',   'StudentController@tableRows', [$staffOrAdmin]);
// Registered before POST /students/{id} so "clear-all" is never treated as an id.
$router->get('/students/clear-all',    'StudentController@clearAllForm',    [$schoolAdminOrAdmin]);
$router->post('/students/clear-all',  'StudentController@clearAllExecute', [$schoolAdminOrAdmin]);
// Delete every student in one class (optionally one stream of it) — same
// static-before-{id} ordering requirement as clear-all above.
$router->get('/students/delete-by-class',  'StudentController@deleteByClassForm',    [$schoolAdminOrAdmin]);
$router->post('/students/delete-by-class', 'StudentController@deleteByClassExecute', [$schoolAdminOrAdmin]);
// Bulk CSV import — one whole class at a time. Admission/records editing is
// school-admin territory; staff and HODs enter marks and view students, but
// do not create, import, or modify student records.
$router->get('/students/import',            'StudentController@importForm',     [$schoolAdminOrAdmin]);
$router->get('/students/import/template',   'StudentController@importTemplate', [$schoolAdminOrAdmin]);
$router->post('/students/import',           'StudentController@importStore',    [$schoolAdminOrAdmin]);
$router->post('/students',             'StudentController@store',  [$schoolAdminOrAdmin]);
// Registered after all the static /students/... GET routes above so it
// doesn't swallow them (Router matches GET routes in insertion order).
$router->get('/students/{id}',         'StudentController@show',   [$staffOrAdmin]);
$router->get('/students/{id}/edit',    'StudentController@edit',   [$schoolAdminOrAdmin]);
$router->post('/students/{id}',        'StudentController@update', [$schoolAdminOrAdmin]);
$router->post('/students/{id}/delete', 'StudentController@destroy',[$schoolAdminOrAdmin]);
$router->post('/students/{id}/photo/delete', 'StudentController@deletePhoto', [$schoolAdminOrAdmin]);

// Staff
$router->get('/staff',              'StaffController@index',  [$schoolAdminOrAdmin]);
$router->get('/staff/create',       'StaffController@create', [$schoolAdminOrAdmin]);
$router->post('/staff',             'StaffController@store',  [$schoolAdminOrAdmin]);
$router->get('/staff/{id}/edit',    'StaffController@edit',   [$schoolAdminOrAdmin]);
$router->post('/staff/{id}',        'StaffController@update', [$schoolAdminOrAdmin]);
$router->post('/staff/{id}/delete', 'StaffController@destroy',[$schoolAdminOrAdmin]);

// HOD accounts (admin creates Heads of Department who sign in at /login)
$router->get('/hods',              'HodAccountController@index',   [$schoolAdminOrAdmin]);
$router->get('/hods/create',       'HodAccountController@create',  [$schoolAdminOrAdmin]);
$router->post('/hods',             'HodAccountController@store',   [$schoolAdminOrAdmin]);
$router->get('/hods/{id}/edit',    'HodAccountController@edit',    [$schoolAdminOrAdmin]);
$router->post('/hods/{id}',        'HodAccountController@update',  [$schoolAdminOrAdmin]);
$router->post('/hods/{id}/delete', 'HodAccountController@destroy', [$schoolAdminOrAdmin]);

// Classes
$router->get('/classes',                'ClassController@index',     [$staffOrAdmin]);
$router->post('/classes',               'ClassController@store',     [$schoolAdminOrAdmin]);
$router->post('/classes/{id}/rename',   'ClassController@rename',    [$schoolAdminOrAdmin]);
$router->post('/classes/{id}/teacher',  'ClassController@setTeacher',[$schoolAdminOrAdmin]);
$router->post('/classes/{id}/prefix',   'ClassController@setPrefix', [$schoolAdminOrAdmin]);
$router->post('/classes/{id}/delete',   'ClassController@destroy',   [$schoolAdminOrAdmin]);

// Subjects
$router->get('/subjects',              'SubjectController@index',         [$staffOrAdmin]);
$router->post('/subjects',             'SubjectController@store',         [$schoolAdminOrAdmin]);
$router->post('/subjects/offered',     'SubjectController@updateOffered', [$schoolAdminOrAdmin]);
$router->post('/subjects/{id}/delete', 'SubjectController@destroy',       [$schoolAdminOrAdmin]);

// Attendance
$router->get('/attendance',  'AttendanceController@index', [$staffOrAdmin]);
$router->post('/attendance', 'AttendanceController@store', [$staffOrAdmin]);

// Grades (legacy single-mark editor - kept for power users). Restricted to
// roles that actually maintain marks so non-staff sessions cannot enumerate
// the full student roster through this view.
$router->get('/grades',  'GradeController@index', [$staffAdminOrHod]);
$router->post('/grades', 'GradeController@store', [$staffOrAdmin]);

// Teaching assignments (admin: who teaches what; who heads which department)
$router->get('/teaching',                'TeachingController@index',       [$schoolAdminOrAdmin]);
$router->post('/teaching',               'TeachingController@store',       [$schoolAdminOrAdmin]);
$router->post('/teaching/{id}/delete',   'TeachingController@destroy',     [$schoolAdminOrAdmin]);
$router->post('/teaching/heads',         'TeachingController@storeHead',   [$schoolAdminOrAdmin]);
$router->post('/teaching/heads/delete',  'TeachingController@destroyHead', [$schoolAdminOrAdmin]);

// Marks
//   Per-subject (teacher with a teaching_assignments row):
$router->get('/marks',             'MarksController@index',           [$staffAdminOrHod]);
$router->get('/marks/entry',       'MarksController@entry',           [$staffAdminOrHod]);
$router->post('/marks',            'MarksController@store',           [$staffAdminOrHod]);
//   Department-wide (HOD matrix entry — every subject in a department for a class):
$router->get('/marks/department',  'MarksController@departmentEntry', [$staffAdminOrHod]);
$router->post('/marks/department', 'MarksController@departmentStore', [$staffAdminOrHod]);
//   Autosave: persists one cell at a time as a teacher types, independent of
//   the full-sheet "Save marks" submit.
$router->post('/marks/autosave-cell', 'MarksController@autosaveCell', [$staffAdminOrHod]);
//   HOD-portal aliases — same controllers, but on URLs that fall inside the
//   /hod/* prefix so HOD sessions stay isolated from the main school portal.
$router->get('/hod/marks',             'MarksController@index',           [$staffAdminOrHod]);
$router->get('/hod/marks/entry',       'MarksController@entry',           [$staffAdminOrHod]);
$router->post('/hod/marks',            'MarksController@store',           [$staffAdminOrHod]);
$router->get('/hod/marks/department',  'MarksController@departmentEntry', [$staffAdminOrHod]);
$router->post('/hod/marks/department', 'MarksController@departmentStore', [$staffAdminOrHod]);
$router->post('/hod/marks/autosave-cell', 'MarksController@autosaveCell', [$staffAdminOrHod]);

// Results (computed averages & positions — Mid ×/30 + End ×/70)
$router->get('/results',              'ResultsController@index',      [$staffAdminOrHod]);
$router->get('/results/class/{id}', 'ResultsController@classView',   [$staffAdminOrHod]);
$router->get('/results/gender',     'ResultsController@genderPerformance', [$staffAdminOrHod]);
$router->get('/hod/results',              'ResultsController@index',      [$staffAdminOrHod]);
$router->get('/hod/results/class/{id}', 'ResultsController@classView',   [$staffAdminOrHod]);
$router->get('/hod/results/gender', 'ResultsController@genderPerformance', [$staffAdminOrHod]);

// Reports (printable mid-term & end-term report cards)
$router->get('/reports',                'ReportController@index',       [$auth]);
// Registered before /reports/student/{id} etc. so the static path wins.
$router->get('/reports/booklet',        'ReportController@booklet',     [$staffAdminOrHod]);
$router->get('/reports/student/{id}',   'ReportController@student',     [$auth]);
$router->get('/reports/class/{id}/booklet', 'ReportController@classBooklet', [$staffAdminOrHod]);
$router->get('/reports/class/{id}',     'ReportController@classReport', [$staffAdminOrHod]);
//   HOD-portal aliases (same handlers, /hod/* URLs).
$router->get('/hod/reports',                    'ReportController@index',       [$staffAdminOrHod]);
$router->get('/hod/reports/booklet',            'ReportController@booklet',     [$staffAdminOrHod]);
$router->get('/hod/reports/student/{id}',       'ReportController@student',     [$staffAdminOrHod]);
$router->get('/hod/reports/class/{id}/booklet', 'ReportController@classBooklet',[$staffAdminOrHod]);
$router->get('/hod/reports/class/{id}',         'ReportController@classReport', [$staffAdminOrHod]);

// Fees (legacy student self-view of their own balance — kept for /dashboard
// "My fees" link). The full Fees Management Module lives under /bursar/*.
$router->get('/fees',  'FeeController@index', [$auth]);

// Bursar accounts (admin creates Bursars who sign in at /login).
$router->get('/bursars',              'BursarAccountController@index',   [$schoolAdminOrAdmin]);
$router->get('/bursars/create',       'BursarAccountController@create',  [$schoolAdminOrAdmin]);
$router->post('/bursars',             'BursarAccountController@store',   [$schoolAdminOrAdmin]);
$router->get('/bursars/{id}/edit',    'BursarAccountController@edit',    [$schoolAdminOrAdmin]);
$router->post('/bursars/{id}',        'BursarAccountController@update',  [$schoolAdminOrAdmin]);
$router->post('/bursars/{id}/delete', 'BursarAccountController@destroy', [$schoolAdminOrAdmin]);

// Parent accounts (admin creates Parents, linked to one or more students,
// who sign in at /login).
$router->get('/parents',              'ParentAccountController@index',   [$schoolAdminOrAdmin]);
$router->get('/parents/create',       'ParentAccountController@create',  [$schoolAdminOrAdmin]);
$router->post('/parents',             'ParentAccountController@store',   [$schoolAdminOrAdmin]);
$router->get('/parents/{id}/edit',    'ParentAccountController@edit',    [$schoolAdminOrAdmin]);
$router->post('/parents/{id}',        'ParentAccountController@update',  [$schoolAdminOrAdmin]);
$router->post('/parents/{id}/delete', 'ParentAccountController@destroy', [$schoolAdminOrAdmin]);

// ============================================================
// Bursar / Fees Management portal — every route is bursar-only.
// All URLs sit under /bursar/* so the portal-aware Auth keeps
// the bursar session isolated from admin/HOD sessions in other
// tabs. Direct-URL access without a bursar login redirects to
// /login automatically.
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

// ============================================================
// Parent portal — every route is parent-only. All URLs sit under
// /parent/* so the portal-aware Auth keeps the parent session isolated
// from admin/HOD/bursar sessions in other tabs, same as the Bursar portal
// above.
// ============================================================
$router->get('/parent',                     'ParentController@dashboard',   [$parentOnly]);
// Reuses ReportController — a parent's report card is the same document
// admin/staff/HOD see, just scoped by canSeeStudent() to their own children.
$router->get('/parent/reports',             'ReportController@index',       [$parentOnly]);
$router->get('/parent/reports/student/{id}','ReportController@student',     [$parentOnly]);
$router->get('/parent/fees/{id}',           'ParentController@fees',        [$parentOnly]);
$router->get('/parent/attendance/{id}',     'ParentController@attendance',  [$parentOnly]);
$router->get('/parent/announcements',       'AnnouncementController@index', [$parentOnly]);

// Announcements
$router->get('/announcements',  'AnnouncementController@index', [$auth]);
$router->post('/announcements', 'AnnouncementController@store', [$staffAdminOrHod]);
//   HOD-portal aliases.
$router->get('/hod/announcements',  'AnnouncementController@index', [$staffAdminOrHod]);
$router->post('/hod/announcements', 'AnnouncementController@store', [$staffAdminOrHod]);

// Settings (school identity + theme customization)
$router->get('/settings',  'SettingsController@index',  [$adminOnly]);
$router->post('/settings', 'SettingsController@update', [$adminOnly]);

$router->get('/activity-log', 'ActivityLogController@index', [$schoolAdminOrAdmin]);

// Schools (super-admin: multi-tenant school management)
$router->get('/schools',                     'SchoolController@index',         [$adminOnly]);
$router->get('/schools/create',              'SchoolController@create',        [$adminOnly]);
$router->post('/schools',                    'SchoolController@store',         [$adminOnly]);
$router->get('/schools/{id}',                'SchoolController@show',          [$adminOnly]);
$router->get('/schools/{id}/edit',           'SchoolController@edit',          [$adminOnly]);
$router->post('/schools/{id}',               'SchoolController@update',        [$adminOnly]);
// ID card theme — unlike the rest of /schools/*, a school_admin may manage
// their OWN school's theme here (ownership checked inside the controller).
$router->get('/schools/{id}/id-card-theme',  'IdCardController@themeForm',   [$schoolAdminOrAdmin]);
$router->post('/schools/{id}/id-card-theme', 'IdCardController@themeUpdate', [$schoolAdminOrAdmin]);
$router->post('/schools/{id}/admins',            'SchoolAdminController@store',       [$adminOnly]);
$router->post('/school-admins/{id}/resend',      'SchoolAdminController@resend',      [$adminOnly]);
$router->post('/school-admins/{id}/set-password','SchoolAdminController@setPassword', [$adminOnly]);
$router->post('/school-admins/{id}/delete',      'SchoolAdminController@destroy',     [$adminOnly]);
$router->post('/schools/{id}/delete',            'SchoolController@destroy',          [$adminOnly]);

// Password management
$router->get('/forgot-password',   'PasswordController@forgotForm',    []);
$router->post('/forgot-password',  'PasswordController@forgotSubmit',  []);
$router->get('/reset-password',    'PasswordController@resetForm',     []);
$router->post('/reset-password',   'PasswordController@resetSubmit',   []);
$router->get('/account/password',  'PasswordController@changeForm',    [$auth]);
$router->post('/account/password', 'PasswordController@changeSubmit',  [$auth]);

return $router;
