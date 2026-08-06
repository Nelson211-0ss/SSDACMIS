<?php
namespace App\Controllers;

use App\Core\ActivityLog;
use App\Core\App;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;
use App\Core\SchoolIdentity;
use App\Core\Settings;
use App\Models\Student;
use App\Services\AcademicMarking;
use App\Services\FeesService;

class StudentController extends Controller
{
    /** Max passport photo size before save. 5 MB is generous for a JPEG. */
    private const PHOTO_MAX_BYTES = 5 * 1024 * 1024;

    /** Allowed passport photo types -> file extension. */
    private const PHOTO_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    public function index(): string
    {
        $search   = trim((string) $this->input('q', ''));
        $schoolId = Auth::schoolId();
        $students = Student::all($search, Student::LIST_LIMIT, $schoolId);
        $totalMatching = Student::countAll($search, $schoolId);
        $listLimit     = Student::LIST_LIMIT;
        $truncated     = $totalMatching > count($students);
        return $this->view('students/index', compact(
            'students', 'search', 'totalMatching', 'listLimit', 'truncated'
        ));
    }

    /**
     * HTML fragment for the students table body — used by live search (AJAX).
     * GET /students/table-rows?q=
     */
    public function tableRows(): string
    {
        $search = trim((string) $this->input('q', ''));
        $schoolId = Auth::schoolId();
        $students = Student::all($search, Student::LIST_LIMIT, $schoolId);
        $totalMatching = Student::countAll($search, $schoolId);
        $listLimit     = Student::LIST_LIMIT;
        $truncated     = $totalMatching > count($students);
        $studentsEmptyMessage = empty($students)
            ? ($search !== '' ? 'No matching students.' : 'No students yet.')
            : '';

        header('Content-Type: text/html; charset=utf-8');
        return $this->view('students/_tbody', compact(
            'students', 'studentsEmptyMessage', 'totalMatching', 'listLimit', 'truncated'
        ));
    }

    /**
     * Printable student roster — whole school or one class, optionally
     * filtered by gender. School admins print their own school; the super
     * admin can pick any school added to the system.
     * GET /students/print?school_id=&class_id=&gender=
     */
    public function printRoster(): string
    {
        $ownSchoolId  = Auth::schoolId();              // null for super admin
        $isSuperAdmin = Auth::role() === 'admin' && $ownSchoolId === null;

        // Resolve which school's roster we're printing — including letterhead
        // details (logo/motto/address/headteacher) for the printed sheet.
        // For a super admin printing one specific school, these have to come
        // straight from that school's own row: SchoolIdentity always reflects
        // the SIGNED-IN user's own school (null/generic for a super admin),
        // never an arbitrary school someone else picked from the dropdown.
        $schools          = [];
        $selectedSchoolId = $ownSchoolId;
        $schoolName       = '';
        $letterhead       = [
            'logo' => null, 'motto' => '', 'address' => '',
            'headteacher_name' => '', 'headteacher_title' => '',
        ];

        if ($isSuperAdmin) {
            $schools = Database::query(
                "SELECT id, name FROM schools ORDER BY name"
            )->fetchAll();

            $reqSchool = (int) $this->input('school_id', 0);
            if ($reqSchool > 0) {
                $row = Database::query(
                    "SELECT id, name, logo, motto, address, headteacher_name, headteacher_title
                     FROM schools WHERE id = ? LIMIT 1",
                    [$reqSchool]
                )->fetch();
                if (!$row) {
                    Flash::set('danger', 'That school does not exist.');
                    $this->redirect('/students/print');
                    return '';
                }
                $selectedSchoolId = (int) $row['id'];
                $schoolName       = (string) $row['name'];
                $logoRel = trim((string) ($row['logo'] ?? ''));
                if ($logoRel !== '' && str_starts_with(ltrim($logoRel, '/'), 'uploads/')) {
                    $abs = dirname(__DIR__, 2) . '/public/' . ltrim($logoRel, '/');
                    if (is_file($abs)) {
                        $letterhead['logo'] = ltrim($logoRel, '/');
                    }
                }
                $letterhead['motto']              = (string) ($row['motto'] ?? '');
                $letterhead['address']            = (string) ($row['address'] ?? '');
                $letterhead['headteacher_name']    = (string) ($row['headteacher_name'] ?? '');
                $letterhead['headteacher_title']   = (string) ($row['headteacher_title'] ?? '');
            } else {
                $selectedSchoolId = null;
                $schoolName       = 'All schools';
            }
        } else {
            $schoolName = SchoolIdentity::name();
            $letterhead = [
                'logo'              => SchoolIdentity::logoUrl(),
                'motto'             => SchoolIdentity::motto(),
                'address'           => SchoolIdentity::address(),
                'headteacher_name'  => SchoolIdentity::headteacherName(),
                'headteacher_title' => SchoolIdentity::headteacherTitle(),
            ];
        }

        $classId = (int) $this->input('class_id', 0);

        $gender = strtolower(trim((string) $this->input('gender', '')));
        if (!in_array($gender, ['male', 'female', 'other'], true)) {
            $gender = '';
        }

        // Class dropdown — scoped to the effective school when one is set.
        $classSql    = "SELECT id, name, level FROM classes WHERE 1=1";
        $classParams = [];
        if ($selectedSchoolId !== null) {
            $classSql      .= " AND school_id = ?";
            $classParams[]  = $selectedSchoolId;
        }
        $classSql .= " ORDER BY level, name";
        $classes = Database::query($classSql, $classParams)->fetchAll();

        $filterClass = null;
        if ($classId > 0) {
            $filterClass = Database::query('SELECT id, name, level FROM classes WHERE id = ?', [$classId])->fetch();
            if (!$filterClass) {
                Flash::set('danger', 'That class does not exist.');
                $this->redirect('/students/print');
                return '';
            }
        }

        // c.id (not just c.name) is selected so the view can reliably detect
        // a class change when grouping rows under a class header — two
        // different schools can otherwise have identically-named classes
        // (e.g. both have a "Form 1A"), which a name-only comparison would
        // wrongly merge into one group in the "all schools" view.
        $sql = 'SELECT s.id, s.admission_no, s.first_name, s.last_name, s.gender, s.dob, s.section, s.stream,
                       s.guardian_name, s.guardian_phone, s.created_at,
                       c.id AS class_id, c.name AS class_name, c.level AS class_level';
        if ($selectedSchoolId === null) {
            $sql .= ', sch.name AS school_name';
        }
        $sql .= ' FROM students s
                LEFT JOIN classes c ON c.id = s.class_id';
        if ($selectedSchoolId === null) {
            $sql .= ' LEFT JOIN schools sch ON sch.id = s.school_id';
        }
        $sql .= ' WHERE 1=1';
        $params = [];
        if ($selectedSchoolId !== null) {
            $sql .= ' AND s.school_id = ?';
            $params[] = $selectedSchoolId;
        }
        if ($classId > 0) {
            $sql .= ' AND s.class_id = ?';
            $params[] = $classId;
        }
        if ($gender !== '') {
            $sql .= ' AND s.gender = ?';
            $params[] = $gender;
        }
        // Grouped by school first when spanning every school (super admin,
        // no school picked), so the printed roster reads as one section per
        // school rather than classes from different schools interleaving.
        $sql .= $selectedSchoolId === null
            ? ' ORDER BY sch.name, c.level, c.name, s.first_name, s.last_name'
            : ' ORDER BY c.level, c.name, s.first_name, s.last_name';

        $students = Database::query($sql, $params)->fetchAll();

        if ($schoolName === '') {
            $schoolName = Settings::get('school_name') ?: App::config('app.name');
        }

        return $this->view('students/print_roster', [
            'students'         => $students,
            'classes'          => $classes,
            'classId'          => $classId,
            'gender'           => $gender,
            'filterClass'      => $filterClass,
            'schools'          => $schools,
            'selectedSchoolId' => $selectedSchoolId,
            'isSuperAdmin'     => $isSuperAdmin,
            'schoolName'       => $schoolName,
            'letterhead'       => $letterhead,
            'printedAt'        => date('d M Y H:i'),
        ]);
    }

    public function create(): string
    {
        $isAdmin  = Auth::role() === 'admin';
        $schoolId = Auth::schoolId();
        $prefillClassId = (int) $this->input('class_id', 0);

        if ($isAdmin) {
            // Super admin: load every class across all schools for JS filtering.
            $classes = Database::query(
                "SELECT id, name, level, admission_prefix, school_id FROM classes ORDER BY name"
            )->fetchAll();
            $schools = Database::query(
                "SELECT id, name FROM schools WHERE status='active' ORDER BY name"
            )->fetchAll();
        } else {
            $ssf     = $schoolId !== null ? ' WHERE school_id = ?' : '';
            $ssp     = $schoolId !== null ? [$schoolId] : [];
            $classes = Database::query(
                "SELECT id, name, level, admission_prefix, school_id FROM classes{$ssf} ORDER BY name",
                $ssp
            )->fetchAll();
            $schools = [];
        }

        return $this->view('students/form', [
            'student'        => null,
            'classes'        => $classes,
            'schools'        => $schools,
            'isAdmin'        => $isAdmin,
            'partial'        => $this->isAjax(),
            'prefillClassId' => $prefillClassId,
        ]);
    }

    /**
     * Printable admission letter for ONE student. Available to the global
     * super admin and to school administrators (scoped to their own school).
     * The view is the same template used for the bulk print — we just feed
     * it a single row.
     *
     * GET /students/{id}/admission-letter
     */
    public function admissionLetter(string $id): string
    {
        $schoolId = Auth::schoolId();
        $sql = "SELECT s.*, c.name AS class_name, c.level
                FROM students s
                LEFT JOIN classes c ON c.id = s.class_id
                WHERE s.id = ?";
        $params = [(int) $id];
        // School-scoped users can only print their own school's letters; the
        // global super admin (schoolId === null) may print any student's.
        if ($schoolId !== null) {
            $sql .= ' AND s.school_id = ?';
            $params[] = $schoolId;
        }
        $sql .= ' LIMIT 1';

        $row = Database::query($sql, $params)->fetch();

        if (!$row) {
            http_response_code(404);
            return $this->view('errors/404');
        }
        return $this->view('students/admission_letter', ['student' => $row]);
    }

    /**
     * Printable admission letters for EVERY admitted student. The global
     * super admin gets all schools; a school administrator is automatically
     * scoped to their own school via Auth::schoolId().
     * Optional ?class_id= narrows the print job to a single class so a
     * head teacher can print one whole class at a time without flooding
     * the queue.
     *
     * GET /students/admission-letters
     */
    public function admissionLetters(): string
    {
        $classId  = (int) $this->input('class_id', 0);
        $schoolId = Auth::schoolId();
        $sql = "SELECT s.*, c.name AS class_name, c.level
                FROM students s
                LEFT JOIN classes c ON c.id = s.class_id
                WHERE 1=1";
        $params = [];
        if ($schoolId !== null) {
            $sql .= ' AND s.school_id = ?';
            $params[] = $schoolId;
        }
        if ($classId > 0) {
            $sql .= ' AND s.class_id = ?';
            $params[] = $classId;
        }
        $sql .= ' ORDER BY c.level, c.name, s.last_name, s.first_name';

        $students = Database::query($sql, $params)->fetchAll();
        return $this->view('students/admission_letter', ['students' => $students]);
    }

    /**
     * Full student profile: identity + finance + academic results +
     * attendance in one tabbed page.
     * GET /students/{id}
     */
    public function show(string $id): string
    {
        $studentId = (int) $id;
        $schoolId  = Auth::schoolId();

        $sql = "SELECT s.*, c.name AS class_name, c.level,
                       t.first_name AS teacher_first, t.last_name AS teacher_last
                FROM students s
                LEFT JOIN classes c ON c.id = s.class_id
                LEFT JOIN staff t   ON t.id = c.class_teacher_id
                WHERE s.id = ?";
        $params = [$studentId];
        // Same scoping as admissionLetter(): school-scoped users can only
        // view their own school's students; the super admin can view any.
        if ($schoolId !== null) {
            $sql .= ' AND s.school_id = ?';
            $params[] = $schoolId;
        }
        $sql .= ' LIMIT 1';

        $student = Database::query($sql, $params)->fetch();
        if (!$student) {
            http_response_code(404);
            return $this->view('errors/404');
        }
        $classId = (int) ($student['class_id'] ?? 0);

        /* ---------------------------- Finance ---------------------------- */
        ['year' => $feeYear, 'term' => $feeTerm] = FeesService::activePeriod();
        FeesService::syncAllStudents($feeYear);
        FeesService::ensureStudentFee($studentId, $feeYear, $feeTerm);

        $termBills = Database::query(
            "SELECT id, term, total_amount, paid_amount, status
             FROM student_fees
             WHERE student_id = ? AND academic_year = ?
             ORDER BY term",
            [$studentId, $feeYear]
        )->fetchAll();

        $activeBill = ['total_amount' => 0, 'paid_amount' => 0, 'status' => 'not_paid'];
        foreach ($termBills as $tb) {
            if ((string) $tb['term'] === $feeTerm) { $activeBill = $tb; break; }
        }

        $payments = Database::query(
            "SELECT p.id, p.amount, p.payment_date, p.receipt_no, p.notes,
                    sf.term, sf.academic_year, u.name AS bursar_name
             FROM payments p
             LEFT JOIN student_fees sf ON sf.id = p.student_fee_id
             LEFT JOIN users u ON u.id = p.recorded_by
             WHERE p.student_id = ?
             ORDER BY p.payment_date DESC, p.id DESC",
            [$studentId]
        )->fetchAll();

        /* ---------------------------- Results ---------------------------- */
        $resultsYear = (string) ($this->input('year') ?: self::defaultAcademicYear());
        $resultsTerm = (string) ($this->input('term') ?: 'Term 1');
        if (!in_array($resultsTerm, ['Term 1', 'Term 2', 'Term 3'], true)) {
            $resultsTerm = 'Term 1';
        }
        // Mid-term (marks out of 30) and end-of-term (mid + end, out of 100)
        // are separate result sets; end-of-term is the default.
        $resultsStage = AcademicMarking::normalizeStage((string) $this->input('stage', ''));
        $sheet    = AcademicMarking::buildScoreSheet($studentId, $resultsYear, $resultsTerm, $resultsStage);
        $position = $classId > 0
            ? AcademicMarking::classPositionRow($studentId, $classId, $resultsYear, $resultsTerm, $resultsStage)
            : ['position' => null, 'cohort' => 0];

        /* --------------------------- Attendance --------------------------- */
        $attRows = Database::query(
            "SELECT status, COUNT(*) AS n FROM attendance WHERE student_id = ? GROUP BY status",
            [$studentId]
        )->fetchAll();
        $attCounts = ['present' => 0, 'absent' => 0, 'late' => 0];
        foreach ($attRows as $row) {
            $status = (string) $row['status'];
            if (isset($attCounts[$status])) {
                $attCounts[$status] = (int) $row['n'];
            }
        }
        $attTotal = array_sum($attCounts);
        $attRate  = $attTotal > 0 ? (int) round($attCounts['present'] / $attTotal * 100) : null;

        return $this->view('students/show', [
            'student'     => $student,
            'classId'     => $classId,
            'feeYear'     => $feeYear,
            'feeTerm'     => $feeTerm,
            'termBills'   => $termBills,
            'activeBill'  => $activeBill,
            'payments'    => $payments,
            'resultsYear'  => $resultsYear,
            'resultsTerm'  => $resultsTerm,
            'resultsStage' => $resultsStage,
            'stages'       => AcademicMarking::stages(),
            'sheet'       => $sheet,
            'position'    => $position,
            'attCounts'   => $attCounts,
            'attTotal'    => $attTotal,
            'attRate'     => $attRate,
        ]);
    }

    /** Same "academic year rolls over in September" rule ReportController uses. */
    private static function defaultAcademicYear(): string
    {
        return (date('n') >= 9)
            ? date('Y') . '/' . (date('Y') + 1)
            : (date('Y') - 1) . '/' . date('Y');
    }

    public function store(): string
    {
        $this->validateCsrf();
        $data = $this->payload();
        // Carries the chosen class back to the create form on any error below,
        // so re-entering a class after a mistake is never required.
        $backToCreate = '/students/create' . ((int) ($data['class_id'] ?? 0) > 0
            ? '?class_id=' . (int) $data['class_id']
            : '');

        if (!$this->validateStudentCoreFields($data, $backToCreate)) {
            return '';
        }

        $data['stream'] = $this->resolveStream((int) $data['class_id'], (string) $data['stream']);
        if ($data['stream'] === false) {
            Flash::set('danger', 'Form 3 and Form 4 students must be assigned to either the Science or Arts stream.');
            $this->redirect($backToCreate);
            return '';
        }

        $generated = Student::nextAdmissionNo((int) $data['class_id']);
        if (!$generated) {
            Flash::set('danger', 'The selected class has no admission prefix configured. Set one on the Classes page first.');
            $this->redirect($backToCreate);
            return '';
        }
        $data['admission_no'] = $generated;
        // Super admin picks the school via the form; all others are scoped to their own school.
        if (Auth::role() === 'admin') {
            $data['school_id'] = (int) $this->input('school_id', 1) ?: 1;
        } else {
            $data['school_id'] = Auth::schoolId() ?? 1;
        }

        $studentId = Student::create($data);
        ActivityLog::record('create', 'student', $studentId, "Admitted student {$generated}");

        // Optional passport photo. Failing the photo step does NOT roll back
        // the student — admission must still succeed. Show the bursar/admin
        // a clear flash about the photo issue so they can re-upload from the
        // edit screen.
        $photoErr = $this->savePassportPhoto($studentId);
        if ($photoErr !== null) {
            Flash::set(
                'danger',
                "Student admitted ($generated), but the passport photo did NOT save: $photoErr "
                . "Open the student's edit page and upload the photo again."
            );
        } else {
            Flash::set('success', "Student added. Admission no: {$generated}");
        }

        // "Save & add another" keeps the same class selected instead of
        // sending the admin back to the list to pick it again for every
        // student in the class.
        if ($this->input('save_mode') === 'another') {
            $this->redirect('/students/create?class_id=' . (int) $data['class_id']);
            return '';
        }

        $this->redirect('/students');
        return '';
    }

    /** Hard cap on rows processed from one uploaded CSV file. */
    private const IMPORT_MAX_ROWS = 1000;

    /** GET /students/import — pick a class, upload a CSV of students for it. */
    public function importForm(): string
    {
        $isAdmin  = Auth::role() === 'admin';
        $schoolId = Auth::schoolId();
        $prefillClassId = (int) $this->input('class_id', 0);

        if ($isAdmin) {
            $classes = Database::query(
                "SELECT id, name, level, admission_prefix, school_id FROM classes ORDER BY name"
            )->fetchAll();
            $schools = Database::query(
                "SELECT id, name FROM schools WHERE status='active' ORDER BY name"
            )->fetchAll();
        } else {
            $ssf     = $schoolId !== null ? ' WHERE school_id = ?' : '';
            $ssp     = $schoolId !== null ? [$schoolId] : [];
            $classes = Database::query(
                "SELECT id, name, level, admission_prefix, school_id FROM classes{$ssf} ORDER BY name",
                $ssp
            )->fetchAll();
            $schools = [];
        }

        return $this->view('students/import', [
            'classes'        => $classes,
            'schools'        => $schools,
            'isAdmin'        => $isAdmin,
            'prefillClassId' => $prefillClassId,
        ]);
    }

    /** GET /students/import/template — blank CSV with the expected columns. */
    public function importTemplate(): string
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="students-import-template.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $out = fopen('php://output', 'w');
        // BOM so Excel opens the file (and re-saved uploads) as UTF-8.
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            'first_name', 'last_name', 'gender', 'dob', 'section',
            'guardian_name', 'guardian_phone', 'address',
        ], ',', '"', '');
        fputcsv($out, [
            'JOHN', 'DOE', 'male', '2010-05-14', 'day',
            'JANE DOE', '0700000000', 'KAMPALA',
        ], ',', '"', '');
        fclose($out);
        return '';
    }

    /**
     * POST /students/import — parse the uploaded CSV and admit every valid
     * row into the chosen class. Bad rows are skipped (not fatal) so one
     * typo doesn't block the rest of the file; the results page lists both
     * what was admitted and what was skipped, with reasons.
     */
    public function importStore(): string
    {
        $this->validateCsrf();

        $isAdmin      = Auth::role() === 'admin';
        $classId      = (int) $this->input('class_id', 0);
        $backToImport = '/students/import' . ($classId > 0 ? '?class_id=' . $classId : '');

        if ($classId <= 0) {
            Flash::set('danger', 'Choose the class these students belong to.');
            $this->redirect($backToImport);
            return '';
        }

        $classRow = Database::query(
            "SELECT id, school_id, level FROM classes WHERE id = ?",
            [$classId]
        )->fetch();
        if (!$classRow) {
            Flash::set('danger', 'That class does not exist.');
            $this->redirect('/students/import');
            return '';
        }

        // Form 3/Form 4 classes are Science or Arts — asked once for the
        // whole file rather than per row, since one CSV always admits into
        // a single class (and in practice a single stream).
        $classLevel   = trim((string) ($classRow['level'] ?? ''));
        $isUpperLevel = ($classLevel === 'Form 3' || $classLevel === 'Form 4');
        $batchStream  = strtolower(trim((string) $this->input('stream', '')));
        if ($isUpperLevel && !in_array($batchStream, ['science', 'arts'], true)) {
            Flash::set('danger', 'Select whether this class is Science or Arts before importing — required for Form 3 and Form 4.');
            $this->redirect($backToImport);
            return '';
        }
        $resolvedStream = $isUpperLevel ? $batchStream : 'none';

        // Same school resolution as store(): super admin picks the school
        // via the form (or inherits the class's own school), everyone else
        // is scoped to their own school and can't import into another one.
        if ($isAdmin) {
            $schoolId = (int) $this->input('school_id', 0) ?: (int) $classRow['school_id'];
        } else {
            $schoolId = Auth::schoolId() ?? 1;
            if ((int) $classRow['school_id'] !== $schoolId) {
                Flash::set('danger', 'That class does not belong to your school.');
                $this->redirect('/students/import');
                return '';
            }
        }

        $file = $_FILES['csv_file'] ?? null;
        $err  = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if (!is_array($file) || $err === UPLOAD_ERR_NO_FILE) {
            Flash::set('danger', 'Choose a CSV file to upload.');
            $this->redirect($backToImport);
            return '';
        }
        if ($err !== UPLOAD_ERR_OK) {
            Flash::set('danger', self::uploadErrorMessage($err));
            $this->redirect($backToImport);
            return '';
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            Flash::set('danger', 'Suspicious upload rejected.');
            $this->redirect($backToImport);
            return '';
        }
        if ((int) $file['size'] <= 0) {
            Flash::set('danger', 'The uploaded file is empty.');
            $this->redirect($backToImport);
            return '';
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            Flash::set('danger', 'Could not read the uploaded file.');
            $this->redirect($backToImport);
            return '';
        }

        // Strip a UTF-8 BOM if Excel added one, then read the header row.
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        $header = fgetcsv($handle);
        if ($header === false || $header === [null]) {
            fclose($handle);
            Flash::set('danger', 'The file has no rows. Use the template as a starting point.');
            $this->redirect($backToImport);
            return '';
        }
        $header = array_map(static fn ($h) => strtolower(trim((string) $h)), $header);
        foreach (['first_name', 'last_name'] as $col) {
            if (!in_array($col, $header, true)) {
                fclose($handle);
                Flash::set('danger', "The CSV is missing a required column: {$col}.");
                $this->redirect($backToImport);
                return '';
            }
        }

        $imported = [];
        $errors   = [];
        $rowNum   = 1; // header occupies row 1
        $capped   = false;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            $blank = count(array_filter($row, static fn ($v) => trim((string) $v) !== '')) === 0;
            if ($blank) {
                continue;
            }
            if (count($imported) + count($errors) >= self::IMPORT_MAX_ROWS) {
                $capped = true;
                break;
            }

            $assoc = [];
            foreach ($header as $i => $col) {
                $assoc[$col] = trim((string) ($row[$i] ?? ''));
            }

            $firstName = mb_strtoupper($assoc['first_name'] ?? '', 'UTF-8');
            $lastName  = mb_strtoupper($assoc['last_name'] ?? '', 'UTF-8');
            $name      = trim($firstName . ' ' . $lastName);
            $reason    = null;

            if ($firstName === '' || $lastName === '') {
                $reason = 'First name and last name are required.';
            }

            $gender = strtolower($assoc['gender'] ?? '') ?: 'male';
            if (!in_array($gender, ['male', 'female', 'other'], true)) $gender = 'male';

            $section = strtolower($assoc['section'] ?? '') ?: 'day';
            if (!in_array($section, ['day', 'boarding'], true)) $section = 'day';

            $dob = trim($assoc['dob'] ?? '');
            if ($reason === null && $dob !== '' && !$this->isValidStudentDob($dob)) {
                $reason = 'Invalid date of birth (use YYYY-MM-DD, not in the future).';
            }

            if ($reason !== null) {
                $errors[] = ['row' => $rowNum, 'name' => $name, 'reason' => $reason];
                continue;
            }

            $admissionNo = Student::nextAdmissionNo($classId);
            if (!$admissionNo) {
                $errors[] = [
                    'row' => $rowNum, 'name' => $name,
                    'reason' => 'The class has no admission prefix configured.',
                ];
                continue;
            }

            $studentId = Student::create([
                'school_id'      => $schoolId ?: 1,
                'admission_no'   => $admissionNo,
                'first_name'     => $firstName,
                'last_name'      => $lastName,
                'gender'         => $gender,
                'dob'            => $dob !== '' ? $dob : null,
                'class_id'       => $classId,
                'section'        => $section,
                'stream'         => $resolvedStream,
                'guardian_name'  => mb_strtoupper($assoc['guardian_name'] ?? '', 'UTF-8'),
                'guardian_phone' => $assoc['guardian_phone'] ?? '',
                'address'        => mb_strtoupper($assoc['address'] ?? '', 'UTF-8'),
            ]);
            ActivityLog::record('create', 'student', $studentId, "Admitted student {$admissionNo} via CSV import");
            $imported[] = ['row' => $rowNum, 'name' => $name, 'admission_no' => $admissionNo];
        }
        fclose($handle);

        if ($capped) {
            $errors[] = [
                'row' => null, 'name' => null,
                'reason' => 'Stopped after ' . self::IMPORT_MAX_ROWS . ' rows — split larger files into batches.',
            ];
        }

        if (empty($imported) && empty($errors)) {
            Flash::set('warning', 'The file had no data rows to import.');
            $this->redirect($backToImport);
            return '';
        }

        return $this->view('students/import_result', [
            'imported' => $imported,
            'errors'   => $errors,
            'classId'  => $classId,
        ]);
    }

    public function edit(string $id): string
    {
        $student = Student::find((int) $id);
        if (!$student) { http_response_code(404); return $this->view('errors/404'); }

        $isAdmin  = Auth::role() === 'admin';
        $schoolId = Auth::schoolId() ?? ($isAdmin ? (int)($student['school_id'] ?? 0) ?: null : null);
        $ssf = $schoolId !== null ? ' WHERE school_id = ?' : '';
        $ssp = $schoolId !== null ? [$schoolId] : [];

        // Super admin edits: load classes for the student's school.
        if ($isAdmin) {
            $sStudentSchool = (int)($student['school_id'] ?? 0);
            $classes = Database::query(
                "SELECT id, name, level, admission_prefix, school_id FROM classes WHERE school_id = ? ORDER BY name",
                [$sStudentSchool ?: 0]
            )->fetchAll();
        } else {
            $classes = Database::query(
                "SELECT id, name, level, admission_prefix, school_id FROM classes{$ssf} ORDER BY name",
                $ssp
            )->fetchAll();
        }

        $schools = $isAdmin
            ? Database::query("SELECT id, name FROM schools WHERE status='active' ORDER BY name")->fetchAll()
            : [];

        return $this->view('students/form', compact('student', 'classes', 'schools', 'isAdmin'));
    }

    public function update(string $id): string
    {
        $this->validateCsrf();
        $data = $this->payload();
        $existing = Student::find((int) $id);
        if (!$existing) { http_response_code(404); return $this->view('errors/404'); }

        if ($data['admission_no'] === '') {
            $data['admission_no'] = $existing['admission_no'];
        }
        if (!in_array($data['section'], ['day', 'boarding'], true)) {
            $data['section'] = $existing['section'] ?? 'day';
        }

        if (!$this->validateStudentCoreFields($data, '/students/' . (int) $id . '/edit')) {
            return '';
        }

        $stream = $this->resolveStream((int) $data['class_id'], (string) $data['stream']);
        if ($stream === false) {
            Flash::set('danger', 'Form 3 and Form 4 students must be assigned to either the Science or Arts stream.');
            $this->redirect('/students/' . (int) $id . '/edit');
            return '';
        }
        $data['stream'] = $stream;

        Student::update((int) $id, $data);
        ActivityLog::record('update', 'student', (int) $id, "Updated student {$data['admission_no']} ({$data['first_name']} {$data['last_name']})");

        $photoErr = $this->savePassportPhoto((int) $id);
        if ($photoErr !== null) {
            Flash::set(
                'danger',
                "Student details saved, but the passport photo did NOT upload: $photoErr "
                . "Try a smaller image and use the photo section on this page to re-upload."
            );
        } else {
            Flash::set('success', 'Student updated.');
        }

        $this->redirect('/students');
        return '';
    }

    /**
     * Remove a student's passport photo (file + DB column). Posted from the
     * edit form's "Remove photo" button.
     */
    public function deletePhoto(string $id): string
    {
        $this->validateCsrf();

        $existing = Student::find((int) $id);
        if (!$existing) {
            http_response_code(404);
            return $this->view('errors/404');
        }

        if (!empty($existing['photo_path'])) {
            $this->deletePhotoFile((string) $existing['photo_path']);
            Student::clearPhoto((int) $id);
            ActivityLog::record('update', 'student', (int) $id, "Removed passport photo for student #{$id}");
            Flash::set('success', 'Passport photo removed.');
        }

        $this->redirect('/students/' . (int) $id . '/edit');
        return '';
    }

    /**
     * Names, class, section — required for create and update. Date of birth
     * is optional, but if one is entered it must be a real, non-future date.
     */
    private function validateStudentCoreFields(array &$data, string $redirect): bool
    {
        if ($data['first_name'] === '' || $data['last_name'] === '') {
            Flash::set('danger', 'First name and last name are required.');
            $this->redirect($redirect);
            return false;
        }
        if (!$data['class_id']) {
            Flash::set('danger', 'Please choose the class — admission number is generated from it.');
            $this->redirect($redirect);
            return false;
        }
        if (!in_array($data['section'], ['day', 'boarding'], true)) {
            Flash::set('danger', 'Please assign the student to either Day or Boarding section.');
            $this->redirect($redirect);
            return false;
        }
        $dob = trim((string) ($data['dob'] ?? ''));
        if ($dob !== '' && !$this->isValidStudentDob($dob)) {
            Flash::set('danger', 'Enter a valid date of birth (not in the future).');
            $this->redirect($redirect);
            return false;
        }
        $data['dob'] = $dob !== '' ? $dob : null;

        return true;
    }

    private function isValidStudentDob(string $ymd): bool
    {
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $ymd);
        if (!$dt || $dt->format('Y-m-d') !== $ymd) {
            return false;
        }

        return $dt <= new \DateTimeImmutable('today');
    }

    /**
     * Validate the stream against the class's level.
     *  - Form 3 / Form 4 classes  -> require 'science' or 'arts' (returns false otherwise).
     *  - Form 1 / Form 2 (or any other level) -> always 'none'.
     */
    private function resolveStream(int $classId, string $submitted): string|false
    {
        $row = Database::query("SELECT level FROM classes WHERE id = ?", [$classId])->fetch();
        $level = trim((string) ($row['level'] ?? ''));
        $isUpper = ($level === 'Form 3' || $level === 'Form 4');
        if (!$isUpper) {
            return 'none';
        }
        return in_array($submitted, ['science', 'arts'], true) ? $submitted : false;
    }

    public function destroy(string $id): string
    {
        $this->validateCsrf();
        $existing = Student::find((int) $id);

        // School admins (and staff) are scoped to their own school: refuse to
        // delete a learner that belongs to another school even if the id is
        // guessed. The super admin (schoolId === null) may delete anywhere.
        $schoolId = Auth::schoolId();
        if (!$existing || ($schoolId !== null && (int) ($existing['school_id'] ?? 0) !== $schoolId)) {
            Flash::set('danger', 'That student does not exist or is not in your school.');
            $this->redirect('/students');
            return '';
        }

        if (!empty($existing['photo_path'])) {
            $this->deletePhotoFile((string) $existing['photo_path']);
        }
        ActivityLog::record('delete', 'student', (int) $id, "Deleted student {$existing['admission_no']} ({$existing['first_name']} {$existing['last_name']})");
        Student::delete((int) $id);
        Flash::set('success', 'Student removed.');
        $this->redirect('/students');
        return '';
    }

    /** GET /students/clear-all — confirmation page (super admin or school admin). */
    public function clearAllForm(): string
    {
        $studentCount = Student::countAll('', Auth::schoolId());
        return $this->view('students/clear_all', [
            'studentCount'  => $studentCount,
            'isSuperAdmin'  => Auth::schoolId() === null,
        ]);
    }

    /**
     * POST /students/clear-all — wipe all students + student logins + related data.
     * Requires typing DELETE ALL STUDENTS as a deliberate confirmation.
     */
    public function clearAllExecute(): string
    {
        $this->validateCsrf();

        $expected = 'DELETE ALL STUDENTS';
        $typed    = mb_strtoupper(trim((string) $this->input('confirm_phrase', '')), 'UTF-8');

        if (!hash_equals($expected, $typed)) {
            Flash::set(
                'danger',
                'Confirmation phrase did not match. Type DELETE ALL STUDENTS exactly (capital letters).'
            );
            $this->redirect('/students/clear-all');
            return '';
        }

        // Scope every count and the purge itself to the acting user's school.
        // School admins clear only their own learners; the super admin
        // (schoolId === null) clears every school.
        $schoolId = Auth::schoolId();

        $studentCountBefore = Student::countAll('', $schoolId);
        if ($studentCountBefore === 0) {
            Flash::set('warning', 'There are no students to remove.');
            $this->redirect('/students/clear-all');
            return '';
        }

        $result = Student::purgeAll($schoolId);
        foreach ($result['photo_paths'] as $rel) {
            $this->deletePhotoFile((string) $rel);
        }

        $nStudents = $result['student_rows'];
        $nUsers    = $result['user_rows_deleted'];

        ActivityLog::record('delete', 'student', null, "Cleared all students ({$nStudents} removed, {$nUsers} logins removed)");

        Flash::set(
            'success',
            "All students removed ($nStudents records). Related marks, attendance, fees, and results "
            . "were cleared. Student login accounts removed: {$nUsers}."
        );
        $this->redirect('/students');
        return '';
    }

    /**
     * GET /students/delete-by-class — pick a class (and, for Form 3/Form 4,
     * optionally narrow to one stream) and preview how many students match
     * before deleting them.
     */
    public function deleteByClassForm(): string
    {
        $isAdmin  = Auth::role() === 'admin';
        $schoolId = Auth::schoolId();

        $classId = (int) $this->input('class_id', 0);
        $stream  = (string) $this->input('stream', 'all');
        if (!in_array($stream, ['all', 'science', 'arts'], true)) $stream = 'all';

        if ($isAdmin) {
            $selectedSchoolId = (int) $this->input('school_id', 0) ?: null;
            $sf = $selectedSchoolId !== null ? ' WHERE school_id = ?' : '';
            $sp = $selectedSchoolId !== null ? [$selectedSchoolId] : [];
            $classes = Database::query(
                "SELECT id, name, level, school_id FROM classes{$sf} ORDER BY name",
                $sp
            )->fetchAll();
            $schools = Database::query(
                "SELECT id, name FROM schools WHERE status='active' ORDER BY name"
            )->fetchAll();
        } else {
            $selectedSchoolId = $schoolId;
            $ssf     = $schoolId !== null ? ' WHERE school_id = ?' : '';
            $ssp     = $schoolId !== null ? [$schoolId] : [];
            $classes = Database::query(
                "SELECT id, name, level, school_id FROM classes{$ssf} ORDER BY name",
                $ssp
            )->fetchAll();
            $schools = [];
        }

        $selectedClass  = null;
        $isUpperLevel   = false;
        $effectiveStream = 'all';
        $studentCount   = 0;

        if ($classId > 0) {
            // Look the class up directly (not just in the filtered $classes
            // list above) so a school admin's own class is always found even
            // if a stray ?school_id= narrowed $classes to a different set.
            $classRow = Database::query(
                "SELECT id, name, level, school_id FROM classes WHERE id = ?",
                [$classId]
            )->fetch();
            if ($classRow && ($schoolId === null || (int) $classRow['school_id'] === $schoolId)) {
                $selectedClass   = $classRow;
                $level           = trim((string) ($classRow['level'] ?? ''));
                $isUpperLevel    = ($level === 'Form 3' || $level === 'Form 4');
                $effectiveStream = $isUpperLevel ? $stream : 'all';
                $studentCount    = Student::countByClass($classId, $schoolId, $effectiveStream);
            }
        }

        return $this->view('students/delete_by_class', [
            'classes'          => $classes,
            'schools'          => $schools,
            'isAdmin'          => $isAdmin,
            'selectedSchoolId' => $selectedSchoolId,
            'selectedClass'    => $selectedClass,
            'stream'           => $stream,
            'isUpperLevel'     => $isUpperLevel,
            'effectiveStream'  => $effectiveStream,
            'studentCount'     => $studentCount,
        ]);
    }

    /**
     * POST /students/delete-by-class — wipe every student in one class (or
     * one stream of it). Requires typing the class's own name as a
     * deliberate confirmation, since — unlike "clear all" — the wrong pick
     * here is a wrong CLASS, not just a wrong moment, so the phrase needs to
     * prove the admin has the right one open.
     */
    public function deleteByClassExecute(): string
    {
        $this->validateCsrf();

        $classId = (int) $this->input('class_id', 0);
        $stream  = (string) $this->input('stream', 'all');
        if (!in_array($stream, ['all', 'science', 'arts'], true)) $stream = 'all';
        $backTo = '/students/delete-by-class?class_id=' . $classId . ($stream !== 'all' ? '&stream=' . $stream : '');

        if ($classId <= 0) {
            Flash::set('danger', 'Choose a class first.');
            $this->redirect('/students/delete-by-class');
            return '';
        }

        $schoolId = Auth::schoolId();
        $classRow = Database::query(
            "SELECT id, name, level, school_id FROM classes WHERE id = ?",
            [$classId]
        )->fetch();
        if (!$classRow || ($schoolId !== null && (int) $classRow['school_id'] !== $schoolId)) {
            Flash::set('danger', 'That class does not exist or is not in your school.');
            $this->redirect('/students/delete-by-class');
            return '';
        }

        $level           = trim((string) ($classRow['level'] ?? ''));
        $isUpperLevel    = ($level === 'Form 3' || $level === 'Form 4');
        $effectiveStream = $isUpperLevel ? $stream : 'all';

        $expected = mb_strtoupper(trim((string) $classRow['name']), 'UTF-8');
        $typed    = mb_strtoupper(trim((string) $this->input('confirm_name', '')), 'UTF-8');
        if (!hash_equals($expected, $typed)) {
            Flash::set('danger', "Type the class name exactly to confirm: {$classRow['name']}.");
            $this->redirect($backTo);
            return '';
        }

        $studentCount = Student::countByClass($classId, $schoolId, $effectiveStream);
        if ($studentCount === 0) {
            Flash::set('warning', 'There are no matching students to remove.');
            $this->redirect('/students/delete-by-class');
            return '';
        }

        $result = Student::purgeByClass($classId, $schoolId, $effectiveStream);
        foreach ($result['photo_paths'] as $rel) {
            $this->deletePhotoFile((string) $rel);
        }

        $nStudents   = $result['student_rows'];
        $nUsers      = $result['user_rows_deleted'];
        $streamLabel = $effectiveStream === 'all' ? '' : ' (' . ucfirst($effectiveStream) . ')';

        ActivityLog::record(
            'delete', 'student', null,
            "Deleted all students in class {$classRow['name']}{$streamLabel} ({$nStudents} removed, {$nUsers} logins removed)"
        );
        Flash::set(
            'success',
            "Deleted {$nStudents} student(s) from {$classRow['name']}{$streamLabel}. Related marks, attendance, "
            . "fees, and results were cleared. Student login accounts removed: {$nUsers}."
        );
        $this->redirect('/students');
        return '';
    }

    private function payload(): array
    {
        // Names & addresses: always UPPERCASE in DB.
        // ENUM columns (gender, section, stream) stay lowercase — MySQL ENUM definition —
        // values normalised here so forms may POST any case safely.
        $upper = static fn (string $v): string => mb_strtoupper(trim($v), 'UTF-8');

        $gender = strtolower(trim((string) $this->input('gender', 'male')));
        if (!in_array($gender, ['male', 'female', 'other'], true)) {
            $gender = 'male';
        }
        $section = strtolower(trim((string) $this->input('section', 'day')));
        if (!in_array($section, ['day', 'boarding'], true)) {
            $section = 'day';
        }
        $stream = strtolower(trim((string) $this->input('stream', 'none')));
        if (!in_array($stream, ['none', 'science', 'arts'], true)) {
            $stream = 'none';
        }

        return [
            'admission_no'   => trim((string) $this->input('admission_no')),
            'first_name'     => $upper((string) $this->input('first_name')),
            'last_name'      => $upper((string) $this->input('last_name')),
            'gender'         => $gender,
            'dob'            => $this->input('dob'),
            'class_id'       => $this->input('class_id') ?: null,
            'section'        => $section,
            'stream'         => $stream,
            'guardian_name'  => $upper((string) $this->input('guardian_name')),
            'guardian_phone' => trim((string) $this->input('guardian_phone')),
            'address'        => $upper((string) $this->input('address')),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Passport photo handling                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Persist the optional passport photo for a student. Accepts EITHER a
     * regular uploaded file (input name `photo_file`) OR a webcam-captured
     * data URL (input name `photo_data`, "data:image/...;base64,..."). The
     * file form takes precedence.
     *
     * Returns null on success (or when no photo was supplied), or a
     * user-facing error string on failure.
     */
    private function savePassportPhoto(int $studentId): ?string
    {
        // post_max_size exceeded — PHP silently drops $_POST/$_FILES. Detect
        // this so the bursar isn't left wondering why nothing got saved.
        $contentLen = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        $postMax    = self::iniBytes((string) ini_get('post_max_size'));
        if ($contentLen > 0 && $postMax > 0 && $contentLen > $postMax && empty($_FILES) && empty($_POST)) {
            return 'The photo was larger than the server\'s POST limit (' . ini_get('post_max_size')
                . '). Choose a smaller picture or raise post_max_size in php.ini.';
        }

        $file       = $_FILES['photo_file'] ?? null;
        $dataUrl    = (string) $this->input('photo_data', '');
        $hasFile    = is_array($file) && isset($file['error']) && (int) $file['error'] !== UPLOAD_ERR_NO_FILE;

        if (!$hasFile && $dataUrl === '') {
            return null; // optional — nothing to save
        }

        $existing = Student::find($studentId);
        $previousPath = $existing['photo_path'] ?? null;

        if ($hasFile) {
            $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($err !== UPLOAD_ERR_OK) {
                return self::uploadErrorMessage($err);
            }
            if (!is_uploaded_file($file['tmp_name'])) {
                return 'Suspicious upload rejected.';
            }
            if ((int) $file['size'] <= 0) {
                return 'The uploaded photo is empty.';
            }
            if ((int) $file['size'] > self::PHOTO_MAX_BYTES) {
                return 'Passport photo is too large. Max ' . (self::PHOTO_MAX_BYTES / 1024 / 1024) . ' MB.';
            }
            return $this->saveValidatedPhotoFile($studentId, $file['tmp_name'], $previousPath, /*moveUploaded*/ true);
        }

        // Webcam-captured: data URL "data:image/png;base64,...."
        if (!preg_match('#^data:(image/(?:png|jpeg|webp));base64,([A-Za-z0-9+/=\s]+)$#', $dataUrl, $m)) {
            return 'Captured photo data is malformed.';
        }
        $bin = base64_decode(preg_replace('/\s+/', '', $m[2]) ?? '', true);
        if ($bin === false || $bin === '') {
            return 'Captured photo could not be decoded.';
        }
        if (strlen($bin) > self::PHOTO_MAX_BYTES) {
            return 'Captured photo is too large. Try a lower-resolution snapshot.';
        }

        $tmp = tempnam(sys_get_temp_dir(), 'snap_');
        if ($tmp === false || file_put_contents($tmp, $bin) === false) {
            return 'Could not stage the captured photo for saving.';
        }
        return $this->saveValidatedPhotoFile($studentId, $tmp, $previousPath, /*moveUploaded*/ false);
    }

    /**
     * Inspect the (already-staged) image file, move it under
     * public/uploads/students/, update the DB, and remove the previous
     * photo (if any).
     *
     * @param string      $sourcePath    tmp_name OR a path produced by tempnam()
     * @param string|null $previousPath  current photo_path on the student row
     * @param bool        $moveUploaded  true for $_FILES uploads; false for our own tmp file
     */
    private function saveValidatedPhotoFile(
        int $studentId,
        string $sourcePath,
        ?string $previousPath,
        bool $moveUploaded
    ): ?string {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($sourcePath) ?: '';
        if (!isset(self::PHOTO_MIMES[$mime])) {
            @unlink($sourcePath);
            return 'Unsupported image type. Use JPG, PNG, or WebP.';
        }

        $info = @getimagesize($sourcePath);
        if ($info === false || empty($info['mime']) || $info['mime'] !== $mime) {
            @unlink($sourcePath);
            return 'The file does not look like a valid image.';
        }

        $ext = self::PHOTO_MIMES[$mime];
        $dir = dirname(__DIR__, 2) . '/public/uploads/students';

        // Make sure the folder exists. PHP becomes the owner of any directory
        // it creates, so a freshly-created folder is always writable by us.
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!is_dir($dir)) {
            @unlink($sourcePath);
            return 'Upload folder could not be created at public/uploads/students. '
                 . 'Create the folder on the server and make it writable by the web server.';
        }

        // Best-effort widen of permissions. chmod only succeeds when PHP owns
        // the folder; on shared/VPS hosts where PHP runs as a different user it
        // quietly fails — that's fine, we still try the write below because
        // is_writable() can give false negatives under group/ACL setups.
        if (!is_writable($dir)) {
            @chmod($dir, 0775);
            if (!is_writable($dir)) {
                @chmod($dir, 0777);
            }
        }

        $name = $studentId . '-' . time() . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
        $dest = $dir . '/' . $name;

        // Attempt the write directly rather than gating on is_writable(): if the
        // folder is genuinely writable (even when is_writable() reports false)
        // this succeeds; if it truly is not, the move fails and we report it.
        $ok = $moveUploaded
            ? @move_uploaded_file($sourcePath, $dest)
            : @rename($sourcePath, $dest);

        if (!$ok) {
            // rename()/move can fail across filesystem boundaries; copy fallback.
            if (@copy($sourcePath, $dest)) {
                @unlink($sourcePath);
                $ok = true;
            }
        }
        if (!$ok) {
            @unlink($sourcePath);
            return 'The upload folder is not writable by the web server. On the '
                 . 'server, make public/uploads/students writable — e.g. run '
                 . '"chmod -R 775 public/uploads/students" and, if it persists, set '
                 . 'its owner to the web-server user (e.g. '
                 . '"chown -R www-data:www-data public/uploads/students").';
        }
        @chmod($dest, 0644);

        // Successfully saved — point the row at the new file and drop the
        // old one (if any).
        Student::setPhoto($studentId, 'uploads/students/' . $name);
        if ($previousPath) {
            $this->deletePhotoFile($previousPath);
        }

        return null;
    }

    /**
     * Delete a student's photo file from disk. Path-traversal hardened:
     * we only ever delete files inside public/uploads/students.
     */
    private function deletePhotoFile(string $relativePath): void
    {
        if ($relativePath === '') return;
        $studentsDir = realpath(dirname(__DIR__, 2) . '/public/uploads/students');
        if ($studentsDir === false) return;
        $abs = realpath(dirname(__DIR__, 2) . '/public/' . ltrim($relativePath, '/'));
        if ($abs === false) return;
        if (!str_starts_with($abs, $studentsDir . DIRECTORY_SEPARATOR)) return;
        if (is_file($abs)) {
            @unlink($abs);
        }
    }

    /** Convert "8M" / "512K" / "1G" style ini values to bytes. */
    private static function iniBytes(string $val): int
    {
        $val = trim($val);
        if ($val === '') return 0;
        $unit = strtolower($val[strlen($val) - 1]);
        $num  = (int) $val;
        return match ($unit) {
            'g'     => $num * 1024 * 1024 * 1024,
            'm'     => $num * 1024 * 1024,
            'k'     => $num * 1024,
            default => $num,
        };
    }

    /** PHP UPLOAD_ERR_* -> friendly message. */
    private static function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE,
            UPLOAD_ERR_FORM_SIZE => 'The photo is larger than the server allows.',
            UPLOAD_ERR_PARTIAL   => 'The photo upload was interrupted. Try again.',
            UPLOAD_ERR_NO_FILE   => 'No photo was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR=> 'Server is missing a temp folder for uploads.',
            UPLOAD_ERR_CANT_WRITE=> 'The server could not save the photo.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension blocked the upload.',
            default              => 'Photo upload failed (error ' . $code . ').',
        };
    }
}
