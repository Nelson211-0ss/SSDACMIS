<?php
use App\Core\Router;
use App\Core\Auth;
use App\Core\Permission;

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

/* ------------------------------------------------------------------
 * Permission gates.
 *
 * Each one runs the role guard first (so an unauthenticated user still
 * gets bounced to /login and portal scoping still applies), then checks
 * the capability in App\Core\Permission. The built-in defaults mirror the
 * role guards exactly, so nothing changes until an admin edits the matrix
 * at /users/permissions or overrides a single account.
 * ------------------------------------------------------------------ */
$canViewStudents    = Permission::gate('students.view',   ['admin', 'school_admin', 'staff']);
$canManageStudents  = Permission::gate('students.manage', ['admin', 'school_admin']);
$canImportStudents  = Permission::gate('students.import', ['admin', 'school_admin']);
$canManageStaff     = Permission::gate('staff.manage',    ['admin', 'school_admin']);
$canManageHods      = Permission::gate('accounts.hod',    ['admin', 'school_admin']);
$canManageBursars   = Permission::gate('accounts.bursar', ['admin', 'school_admin']);
$canManageParents   = Permission::gate('accounts.parent', ['admin', 'school_admin']);
$canViewClasses     = Permission::gate('classes.view',    ['admin', 'school_admin', 'staff']);
$canManageClasses   = Permission::gate('classes.manage',  ['admin', 'school_admin']);
$canViewSubjects    = Permission::gate('subjects.view',   ['admin', 'school_admin', 'staff']);
$canManageSubjects  = Permission::gate('subjects.manage', ['admin', 'school_admin']);
$canManageTeaching  = Permission::gate('teaching.manage', ['admin', 'school_admin']);
$canTakeAttendance  = Permission::gate('attendance.manage', ['admin', 'school_admin', 'staff']);
$canEnterMarks      = Permission::gate('marks.enter',     ['admin', 'school_admin', 'staff', 'hod']);
$canViewResults     = Permission::gate('results.view',    ['admin', 'school_admin', 'staff', 'hod']);
$canViewReports     = Permission::gate('reports.view',    ['admin', 'school_admin', 'staff', 'hod']);
$canViewAnalytics   = Permission::gate('analytics.view',  ['admin', 'school_admin', 'staff', 'hod']);
$canPostNotices     = Permission::gate('announcements.post', ['admin', 'school_admin', 'staff', 'hod']);
$canManageSettings  = Permission::gate('settings.manage', ['admin']);
$canViewActivity    = Permission::gate('activity.view',   ['admin', 'school_admin']);
$canManageSchools   = Permission::gate('schools.manage',  ['admin']);
/** User accounts & the permission matrix — super admin and school admin. */
$canManageUsers     = Permission::gate('users.manage',    ['admin', 'school_admin']);

$router->get('/dashboard', 'DashboardController@index', [$auth]);

// HOD landing page (auto-redirected from /dashboard for HODs)
$router->get('/hod',          'HodController@dashboard', [$staffAdminOrHod]);
$router->get('/hod/overview', 'HodController@overview', [$staffAdminOrHod]);
$router->get('/hod/students', 'HodController@students',  [$staffAdminOrHod]);

// Students
$router->get('/students',              'StudentController@index',  [$canViewStudents]);
$router->get('/students/print',        'StudentController@printRoster', [$canViewStudents]);
$router->get('/students/admission-letters',           'StudentController@admissionLetters', [$canManageStudents]);
$router->get('/students/{id}/admission-letter',       'StudentController@admissionLetter',  [$canManageStudents]);
// ID cards — bulk-by-class registered here (before /students/{id}); the
// single-student route sits with the other /students/{id}/... routes below.
$router->get('/students/id-cards',     'IdCardController@bulk',    [$schoolAdminOrAdmin]);
$router->get('/students/{id}/id-card', 'IdCardController@show',    [$schoolAdminOrAdmin]);
$router->get('/students/create',       'StudentController@create', [$canManageStudents]);
$router->get('/students/table-rows',   'StudentController@tableRows', [$canViewStudents]);
// Registered before POST /students/{id} so "clear-all" is never treated as an id.
$router->get('/students/clear-all',    'StudentController@clearAllForm',    [$canImportStudents]);
$router->post('/students/clear-all',  'StudentController@clearAllExecute', [$canImportStudents]);
// Delete every student in one class (optionally one stream of it) — same
// static-before-{id} ordering requirement as clear-all above.
$router->get('/students/delete-by-class',  'StudentController@deleteByClassForm',    [$canImportStudents]);
$router->post('/students/delete-by-class', 'StudentController@deleteByClassExecute', [$canImportStudents]);
// Bulk CSV import — one whole class at a time. Admission/records editing is
// school-admin territory; staff and HODs enter marks and view students, but
// do not create, import, or modify student records.
$router->get('/students/import',            'StudentController@importForm',     [$canImportStudents]);
$router->get('/students/import/template',   'StudentController@importTemplate', [$canImportStudents]);
$router->post('/students/import',           'StudentController@importStore',    [$canImportStudents]);
$router->post('/students',             'StudentController@store',  [$canManageStudents]);
// Registered after all the static /students/... GET routes above so it
// doesn't swallow them (Router matches GET routes in insertion order).
$router->get('/students/{id}',         'StudentController@show',   [$canViewStudents]);
$router->get('/students/{id}/edit',    'StudentController@edit',   [$canManageStudents]);
$router->post('/students/{id}',        'StudentController@update', [$canManageStudents]);
$router->post('/students/{id}/delete', 'StudentController@destroy',[$canManageStudents]);
$router->post('/students/{id}/photo/delete', 'StudentController@deletePhoto', [$canManageStudents]);

// Staff
$router->get('/staff',              'StaffController@index',  [$canManageStaff]);
$router->get('/staff/create',       'StaffController@create', [$canManageStaff]);
$router->post('/staff',             'StaffController@store',  [$canManageStaff]);
$router->get('/staff/{id}/edit',    'StaffController@edit',   [$canManageStaff]);
$router->post('/staff/{id}',        'StaffController@update', [$canManageStaff]);
$router->post('/staff/{id}/delete', 'StaffController@destroy',[$canManageStaff]);

// HOD accounts (admin creates Heads of Department who sign in at /login)
$router->get('/hods',              'HodAccountController@index',   [$canManageHods]);
$router->get('/hods/create',       'HodAccountController@create',  [$canManageHods]);
$router->post('/hods',             'HodAccountController@store',   [$canManageHods]);
$router->get('/hods/{id}/edit',    'HodAccountController@edit',    [$canManageHods]);
$router->post('/hods/{id}',        'HodAccountController@update',  [$canManageHods]);
$router->post('/hods/{id}/delete', 'HodAccountController@destroy', [$canManageHods]);

// Classes
$router->get('/classes',                'ClassController@index',     [$canViewClasses]);
$router->post('/classes',               'ClassController@store',     [$canManageClasses]);
$router->post('/classes/{id}/rename',   'ClassController@rename',    [$canManageClasses]);
$router->post('/classes/{id}/teacher',  'ClassController@setTeacher',[$canManageClasses]);
$router->post('/classes/{id}/prefix',   'ClassController@setPrefix', [$canManageClasses]);
$router->post('/classes/{id}/delete',   'ClassController@destroy',   [$canManageClasses]);

// Subjects
$router->get('/subjects',              'SubjectController@index',         [$canViewSubjects]);
$router->post('/subjects',             'SubjectController@store',         [$canManageSubjects]);
$router->post('/subjects/offered',     'SubjectController@updateOffered', [$canManageSubjects]);
$router->post('/subjects/{id}/delete', 'SubjectController@destroy',       [$canManageSubjects]);

// Attendance
$router->get('/attendance',  'AttendanceController@index', [$canTakeAttendance]);
$router->post('/attendance', 'AttendanceController@store', [$canTakeAttendance]);

// Grades (legacy single-mark editor - kept for power users). Restricted to
// roles that actually maintain marks so non-staff sessions cannot enumerate
// the full student roster through this view.
$router->get('/grades',  'GradeController@index', [$staffAdminOrHod]);
$router->post('/grades', 'GradeController@store', [$staffOrAdmin]);

// Teaching assignments (admin: who teaches what; who heads which department)
$router->get('/teaching',                'TeachingController@index',       [$canManageTeaching]);
$router->post('/teaching',               'TeachingController@store',       [$canManageTeaching]);
$router->post('/teaching/{id}/delete',   'TeachingController@destroy',     [$canManageTeaching]);
$router->post('/teaching/heads',         'TeachingController@storeHead',   [$canManageTeaching]);
$router->post('/teaching/heads/delete',  'TeachingController@destroyHead', [$canManageTeaching]);

// Marks
//   Per-subject (teacher with a teaching_assignments row):
$router->get('/marks',             'MarksController@index',           [$canEnterMarks]);
$router->get('/marks/entry',       'MarksController@entry',           [$canEnterMarks]);
$router->post('/marks',            'MarksController@store',           [$canEnterMarks]);
//   Department-wide (HOD matrix entry — every subject in a department for a class):
$router->get('/marks/department',  'MarksController@departmentEntry', [$canEnterMarks]);
$router->post('/marks/department', 'MarksController@departmentStore', [$canEnterMarks]);
//   Autosave: persists one cell at a time as a teacher types, independent of
//   the full-sheet "Save marks" submit.
$router->post('/marks/autosave-cell', 'MarksController@autosaveCell', [$canEnterMarks]);
//   HOD-portal aliases — same controllers, but on URLs that fall inside the
//   /hod/* prefix so HOD sessions stay isolated from the main school portal.
$router->get('/hod/marks',             'MarksController@index',           [$canEnterMarks]);
$router->get('/hod/marks/entry',       'MarksController@entry',           [$canEnterMarks]);
$router->post('/hod/marks',            'MarksController@store',           [$canEnterMarks]);
$router->get('/hod/marks/department',  'MarksController@departmentEntry', [$canEnterMarks]);
$router->post('/hod/marks/department', 'MarksController@departmentStore', [$canEnterMarks]);
$router->post('/hod/marks/autosave-cell', 'MarksController@autosaveCell', [$canEnterMarks]);

// Results (computed averages & positions — Mid ×/30 + End ×/70)
$router->get('/results',              'ResultsController@index',      [$canViewResults]);
$router->get('/results/class/{id}', 'ResultsController@classView',   [$canViewResults]);
$router->get('/results/gender',     'ResultsController@genderPerformance', [$canViewResults]);
$router->get('/hod/results',              'ResultsController@index',      [$canViewResults]);
$router->get('/hod/results/class/{id}', 'ResultsController@classView',   [$canViewResults]);
$router->get('/hod/results/gender', 'ResultsController@genderPerformance', [$canViewResults]);

// Reports (printable mid-term & end-term report cards)
$router->get('/reports',                'ReportController@index',       [$auth]);
// Registered before /reports/student/{id} etc. so the static path wins.
$router->get('/reports/booklet',        'ReportController@booklet',     [$canViewReports]);
$router->get('/reports/student/{id}',   'ReportController@student',     [$auth]);
$router->get('/reports/class/{id}/booklet', 'ReportController@classBooklet', [$canViewReports]);
$router->get('/reports/class/{id}',     'ReportController@classReport', [$canViewReports]);
//   HOD-portal aliases (same handlers, /hod/* URLs).
$router->get('/hod/reports',                    'ReportController@index',       [$canViewReports]);
$router->get('/hod/reports/booklet',            'ReportController@booklet',     [$canViewReports]);
$router->get('/hod/reports/student/{id}',       'ReportController@student',     [$canViewReports]);
$router->get('/hod/reports/class/{id}/booklet', 'ReportController@classBooklet',[$canViewReports]);
$router->get('/hod/reports/class/{id}',         'ReportController@classReport', [$canViewReports]);

// Fees (legacy student self-view of their own balance — kept for /dashboard
// "My fees" link). The full Fees Management Module lives under /bursar/*.
$router->get('/fees',  'FeeController@index', [$auth]);

// Bursar accounts (admin creates Bursars who sign in at /login).
$router->get('/bursars',              'BursarAccountController@index',   [$canManageBursars]);
$router->get('/bursars/create',       'BursarAccountController@create',  [$canManageBursars]);
$router->post('/bursars',             'BursarAccountController@store',   [$canManageBursars]);
$router->get('/bursars/{id}/edit',    'BursarAccountController@edit',    [$canManageBursars]);
$router->post('/bursars/{id}',        'BursarAccountController@update',  [$canManageBursars]);
$router->post('/bursars/{id}/delete', 'BursarAccountController@destroy', [$canManageBursars]);

// Parent accounts (admin creates Parents, linked to one or more students,
// who sign in at /login).
$router->get('/parents',              'ParentAccountController@index',   [$canManageParents]);
$router->get('/parents/create',       'ParentAccountController@create',  [$canManageParents]);
$router->post('/parents',             'ParentAccountController@store',   [$canManageParents]);
$router->get('/parents/{id}/edit',    'ParentAccountController@edit',    [$canManageParents]);
$router->post('/parents/{id}',        'ParentAccountController@update',  [$canManageParents]);
$router->post('/parents/{id}/delete', 'ParentAccountController@destroy', [$canManageParents]);

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
$router->post('/announcements', 'AnnouncementController@store', [$canPostNotices]);
//   HOD-portal aliases.
$router->get('/hod/announcements',  'AnnouncementController@index', [$staffAdminOrHod]);
$router->post('/hod/announcements', 'AnnouncementController@store', [$canPostNotices]);

// Settings (school identity + theme customization)
$router->get('/settings',  'SettingsController@index',  [$canManageSettings]);
$router->post('/settings', 'SettingsController@update', [$canManageSettings]);

$router->get('/activity-log', 'ActivityLogController@index', [$canViewActivity]);

// Schools (super-admin: multi-tenant school management)
$router->get('/schools',                     'SchoolController@index',         [$canManageSchools]);
$router->get('/schools/create',              'SchoolController@create',        [$canManageSchools]);
$router->post('/schools',                    'SchoolController@store',         [$canManageSchools]);
$router->get('/schools/{id}',                'SchoolController@show',          [$canManageSchools]);
$router->get('/schools/{id}/edit',           'SchoolController@edit',          [$canManageSchools]);
$router->post('/schools/{id}',               'SchoolController@update',        [$canManageSchools]);
// ID card theme — unlike the rest of /schools/*, a school_admin may manage
// their OWN school's theme here (ownership checked inside the controller).
$router->get('/schools/{id}/id-card-theme',  'IdCardController@themeForm',   [$schoolAdminOrAdmin]);
$router->post('/schools/{id}/id-card-theme', 'IdCardController@themeUpdate', [$schoolAdminOrAdmin]);
$router->post('/schools/{id}/admins',            'SchoolAdminController@store',       [$canManageSchools]);
$router->post('/school-admins/{id}/resend',      'SchoolAdminController@resend',      [$canManageSchools]);
$router->post('/school-admins/{id}/set-password','SchoolAdminController@setPassword', [$canManageSchools]);
$router->post('/school-admins/{id}/delete',      'SchoolAdminController@destroy',     [$canManageSchools]);
$router->post('/schools/{id}/delete',            'SchoolController@destroy',          [$canManageSchools]);

// Password management
$router->get('/forgot-password',   'PasswordController@forgotForm',    []);
$router->post('/forgot-password',  'PasswordController@forgotSubmit',  []);
$router->get('/reset-password',    'PasswordController@resetForm',     []);
$router->post('/reset-password',   'PasswordController@resetSubmit',   []);
$router->get('/account/password',  'PasswordController@changeForm',    [$auth]);
$router->post('/account/password', 'PasswordController@changeSubmit',  [$auth]);

return $router;
