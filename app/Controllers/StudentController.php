<?php
namespace App\Controllers;

use App\Core\App;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Settings;
use App\Models\Student;

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
        $students = Student::all($search);
        $totalMatching = Student::countAll($search);
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
        $students = Student::all($search);
        $totalMatching = Student::countAll($search);
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
     * Printable student roster — whole school or one class. Administrators only.
     * GET /students/print?class_id=   (omit or 0 = whole school)
     */
    public function printRoster(): string
    {
        $classId = (int) $this->input('class_id', 0);

        $classes = Database::query(
            'SELECT id, name, level FROM classes ORDER BY level, name'
        )->fetchAll();

        $filterClass = null;
        if ($classId > 0) {
            $filterClass = Database::query('SELECT id, name, level FROM classes WHERE id = ?', [$classId])->fetch();
            if (!$filterClass) {
                Flash::set('danger', 'That class does not exist.');
                $this->redirect('/students/print');
                return '';
            }
        }

        $sql = 'SELECT s.id, s.admission_no, s.first_name, s.last_name, s.gender, s.dob, s.section, s.stream,
                       s.guardian_name, s.guardian_phone, s.created_at,
                       c.name AS class_name, c.level AS class_level
                FROM students s
                LEFT JOIN classes c ON c.id = s.class_id';
        $params = [];
        if ($classId > 0) {
            $sql .= ' WHERE s.class_id = ?';
            $params[] = $classId;
        }
        $sql .= ' ORDER BY c.level, c.name, s.first_name, s.last_name';

        $students = Database::query($sql, $params)->fetchAll();

        return $this->view('students/print_roster', [
            'students'    => $students,
            'classes'     => $classes,
            'classId'     => $classId,
            'filterClass' => $filterClass,
            'schoolName'  => Settings::get('school_name') ?: App::config('app.name'),
            'printedAt'   => date('d M Y H:i'),
        ]);
    }

    public function create(): string
    {
        $classes = Database::query(
            "SELECT id, name, level, admission_prefix FROM classes ORDER BY name"
        )->fetchAll();
        return $this->view('students/form', ['student' => null, 'classes' => $classes]);
    }

    /**
     * Printable admission letter for ONE student (admin only). The view
     * is the same template used for the bulk print — we just feed it a
     * single row.
     *
     * GET /students/{id}/admission-letter
     */
    public function admissionLetter(string $id): string
    {
        $row = Database::query(
            "SELECT s.*, c.name AS class_name, c.level
             FROM students s
             LEFT JOIN classes c ON c.id = s.class_id
             WHERE s.id = ?
             LIMIT 1",
            [(int) $id]
        )->fetch();

        if (!$row) {
            http_response_code(404);
            return $this->view('errors/404');
        }
        return $this->view('students/admission_letter', ['student' => $row]);
    }

    /**
     * Printable admission letters for EVERY admitted student (admin only).
     * Optional ?class_id= narrows the print job to a single class so a
     * head teacher can print one whole class at a time without flooding
     * the queue.
     *
     * GET /students/admission-letters
     */
    public function admissionLetters(): string
    {
        $classId = (int) $this->input('class_id', 0);
        $sql = "SELECT s.*, c.name AS class_name, c.level
                FROM students s
                LEFT JOIN classes c ON c.id = s.class_id";
        $params = [];
        if ($classId > 0) {
            $sql .= ' WHERE s.class_id = ?';
            $params[] = $classId;
        }
        $sql .= ' ORDER BY c.level, c.name, s.last_name, s.first_name';

        $students = Database::query($sql, $params)->fetchAll();
        return $this->view('students/admission_letter', ['students' => $students]);
    }

    public function store(): string
    {
        $this->validateCsrf();
        $data = $this->payload();

        if (!$this->validateStudentCoreFields($data, '/students/create')) {
            return '';
        }

        $data['stream'] = $this->resolveStream((int) $data['class_id'], (string) $data['stream']);
        if ($data['stream'] === false) {
            Flash::set('danger', 'Form 3 and Form 4 students must be assigned to either the Science or Arts stream.');
            $this->redirect('/students/create');
            return '';
        }

        $generated = Student::nextAdmissionNo((int) $data['class_id']);
        if (!$generated) {
            Flash::set('danger', 'The selected class has no admission prefix configured. Set one on the Classes page first.');
            $this->redirect('/students/create');
            return '';
        }
        $data['admission_no'] = $generated;

        $studentId = Student::create($data);

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

        $this->redirect('/students');
        return '';
    }

    public function edit(string $id): string
    {
        $student = Student::find((int) $id);
        if (!$student) { http_response_code(404); return $this->view('errors/404'); }
        $classes = Database::query(
            "SELECT id, name, level, admission_prefix FROM classes ORDER BY name"
        )->fetchAll();
        return $this->view('students/form', compact('student', 'classes'));
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
            Flash::set('success', 'Passport photo removed.');
        }

        $this->redirect('/students/' . (int) $id . '/edit');
        return '';
    }

    /**
     * Names, class, section, date of birth — required for create and update.
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
        if ($dob === '') {
            Flash::set('danger', 'Date of birth is required.');
            $this->redirect($redirect);
            return false;
        }
        if (!$this->isValidStudentDob($dob)) {
            Flash::set('danger', 'Enter a valid date of birth (not in the future).');
            $this->redirect($redirect);
            return false;
        }
        $data['dob'] = $dob;

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
        if ($existing && !empty($existing['photo_path'])) {
            $this->deletePhotoFile((string) $existing['photo_path']);
        }
        Student::delete((int) $id);
        Flash::set('success', 'Student removed.');
        $this->redirect('/students');
        return '';
    }

    /** GET /students/clear-all — confirmation page (admin only). */
    public function clearAllForm(): string
    {
        $studentCount = Student::countAll('');
        return $this->view('students/clear_all', ['studentCount' => $studentCount]);
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

        $studentCountBefore = Student::countAll('');
        if ($studentCountBefore === 0) {
            Flash::set('warning', 'There are no students to remove.');
            $this->redirect('/students/clear-all');
            return '';
        }

        $result = Student::purgeAll();
        foreach ($result['photo_paths'] as $rel) {
            $this->deletePhotoFile((string) $rel);
        }

        $nStudents = $result['student_rows'];
        $nUsers    = $result['user_rows_deleted'];

        Flash::set(
            'success',
            "All students removed ($nStudents records). Related marks, attendance, fees, and results "
            . "were cleared. Student login accounts removed: {$nUsers}."
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

        // 0777 so that PHP under any of XAMPP/Apache/CLI users can write here
        // (on macOS XAMPP runs as `daemon` while CLI runs as the logged-in
        // user — both must be able to drop a file in this folder).
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
                @unlink($sourcePath);
                return 'Upload folder could not be created at public/uploads/students.';
            }
            @chmod($dir, 0777);
        }
        if (!is_writable($dir)) {
            @chmod($dir, 0777);
            if (!is_writable($dir)) {
                @unlink($sourcePath);
                return 'Upload folder is not writable. Run: chmod 777 public/uploads/students';
            }
        }

        $name = $studentId . '-' . time() . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
        $dest = $dir . '/' . $name;

        $ok = $moveUploaded
            ? move_uploaded_file($sourcePath, $dest)
            : @rename($sourcePath, $dest);

        if (!$ok) {
            // rename() can fail across filesystem boundaries; copy + unlink fallback.
            if (!$moveUploaded && @copy($sourcePath, $dest)) {
                @unlink($sourcePath);
                $ok = true;
            }
        }
        if (!$ok) {
            @unlink($sourcePath);
            return 'Could not save the passport photo.';
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
