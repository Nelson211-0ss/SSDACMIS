<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;

/**
 * Super-admin (role=admin) management of schools and their admin accounts.
 *
 *   GET  /schools              -> list all schools
 *   GET  /schools/create       -> new school form
 *   POST /schools              -> create school
 *   GET  /schools/{id}         -> school detail + school_admin list
 *   GET  /schools/{id}/edit    -> edit school form
 *   POST /schools/{id}         -> update school
 */
class SchoolController extends Controller
{
    private const UPLOAD_MIMES = [
        'image/png'  => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    private const UPLOAD_MAX_BYTES = 1_500_000;

    public function index(): string
    {
        $schools = Database::query(
            "SELECT s.*,
                    COUNT(DISTINCT u.id)  AS admin_count,
                    COUNT(DISTINCT st.id) AS student_count,
                    COUNT(DISTINCT sf.id) AS staff_count
             FROM schools s
             LEFT JOIN users u   ON u.school_id = s.id AND u.role = 'school_admin'
             LEFT JOIN students st ON st.school_id = s.id
             LEFT JOIN staff sf    ON sf.school_id = s.id
             GROUP BY s.id
             ORDER BY s.created_at DESC"
        )->fetchAll();

        return $this->view('schools/index', compact('schools'));
    }

    public function create(): string
    {
        $pdo = Database::connection();
        $next = (int) $pdo->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools'")->fetchColumn();
        $suggestedCode = 'SCH' . str_pad($next ?: time(), 4, '0', STR_PAD_LEFT);
        return $this->view('schools/form', ['school' => null, 'suggestedCode' => $suggestedCode]);
    }

    public function store(): string
    {
        $this->validateCsrf();
        $d = $this->payload();

            if ($d['name'] === '') {
                Flash::set('danger', 'School name is required.');
                $this->redirect('/schools/create');
                return '';
            }

            // Always generate the school's code server-side; do not trust user input.
            $pdo = Database::connection();
            $next = (int) $pdo->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools'")->fetchColumn();
            $code = 'SCH' . str_pad($next ?: time(), 4, '0', STR_PAD_LEFT);
            $code = strtoupper($code);

            // If the code collides (rare), retry with a timestamp suffix once.
            try {
                Database::query(
                    "INSERT INTO schools (name, code, email, phone, address, motto,
                                          headteacher_name, headteacher_title, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $d['name'], $code, $d['email'], $d['phone'],
                        $d['address'], $d['motto'],
                        $d['headteacher_name'], $d['headteacher_title'], $d['status'],
                    ]
                );
            } catch (\Throwable $e) {
                // Try a fallback code if unique constraint on code triggered.
                $fallback = $code . '-' . substr((string) time(), -4);
                Database::query(
                    "INSERT INTO schools (name, code, email, phone, address, motto,
                                          headteacher_name, headteacher_title, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $d['name'], strtoupper($fallback), $d['email'], $d['phone'],
                        $d['address'], $d['motto'],
                        $d['headteacher_name'], $d['headteacher_title'], $d['status'],
                    ]
                );
            }

        $newId = (int) Database::connection()->lastInsertId();

        // Handle logo upload after we have an ID.
        if (!empty($_FILES['logo']['name'])) {
            $err = $this->saveImage($_FILES['logo'], $newId, 'logo');
            if ($err !== null) {
                Flash::set('danger', $err);
                $this->redirect('/schools/' . $newId . '/edit');
                return '';
            }
        }

        if (!empty($_FILES['headteacher_signature']['name'])) {
            $err = $this->saveImage($_FILES['headteacher_signature'], $newId, 'sig');
            if ($err !== null) {
                Flash::set('danger', $err);
                $this->redirect('/schools/' . $newId . '/edit');
                return '';
            }
        }

        Flash::set('success', "School \"{$d['name']}\" created.");
        $this->redirect('/schools/' . $newId);
        return '';
    }

    public function show(string $id): string
    {
        $school = Database::query("SELECT * FROM schools WHERE id = ? LIMIT 1", [(int) $id])->fetch();
        if (!$school) { http_response_code(404); return $this->view('errors/404'); }

        $admins = Database::query(
            "SELECT id, name, email, status, created_at
             FROM users WHERE school_id = ? AND role = 'school_admin'
             ORDER BY status DESC, name",
            [(int) $id]
        )->fetchAll();

        return $this->view('schools/show', compact('school', 'admins'));
    }

    public function edit(string $id): string
    {
        $school = Database::query("SELECT * FROM schools WHERE id = ? LIMIT 1", [(int) $id])->fetch();
        if (!$school) { http_response_code(404); return $this->view('errors/404'); }
        return $this->view('schools/form', compact('school'));
    }

    public function update(string $id): string
    {
        $this->validateCsrf();
        $school = Database::query("SELECT * FROM schools WHERE id = ? LIMIT 1", [(int) $id])->fetch();
        if (!$school) { http_response_code(404); return $this->view('errors/404'); }

        $d = $this->payload();

        if ($d['name'] === '') {
            Flash::set('danger', 'School name is required.');
            $this->redirect('/schools/' . (int) $id . '/edit');
            return '';
        }
        // Do NOT allow editing the school's code via the admin edit form.
        // Keep the existing code value from the DB to avoid accidental changes.
        $d['code'] = (string) ($school['code'] ?? '');

        // Handle logo removal / upload.
        $logo = (string) ($school['logo'] ?? '');
        if ($this->input('remove_logo') === '1') {
            $this->deleteUpload($logo);
            $logo = '';
        }
        if (!empty($_FILES['logo']['name'])) {
            $err = $this->saveImage($_FILES['logo'], (int) $id, 'logo');
            if ($err !== null) {
                Flash::set('danger', $err);
                $this->redirect('/schools/' . (int) $id . '/edit');
                return '';
            }
            // Re-fetch the updated path.
            $logo = (string) (Database::query("SELECT logo FROM schools WHERE id=? LIMIT 1", [(int) $id])->fetch()['logo'] ?? '');
        }

        // Handle signature removal / upload.
        $sig = (string) ($school['headteacher_signature'] ?? '');
        if ($this->input('remove_signature') === '1') {
            $this->deleteUpload($sig);
            $sig = '';
        }
        if (!empty($_FILES['headteacher_signature']['name'])) {
            $err = $this->saveImage($_FILES['headteacher_signature'], (int) $id, 'sig');
            if ($err !== null) {
                Flash::set('danger', $err);
                $this->redirect('/schools/' . (int) $id . '/edit');
                return '';
            }
            $sig = (string) (Database::query("SELECT headteacher_signature FROM schools WHERE id=? LIMIT 1", [(int) $id])->fetch()['headteacher_signature'] ?? '');
        }

        Database::query(
            "UPDATE schools
             SET name=?, email=?, phone=?, address=?, motto=?,
                 headteacher_name=?, headteacher_title=?,
                 logo=?, headteacher_signature=?, status=?
             WHERE id=?",
            [
                $d['name'], $d['email'], $d['phone'],
                $d['address'], $d['motto'],
                $d['headteacher_name'], $d['headteacher_title'],
                $logo, $sig, $d['status'], (int) $id,
            ]
        );

        Flash::set('success', 'School updated.');
        $this->redirect('/schools/' . (int) $id);
        return '';
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                              */
    /* ------------------------------------------------------------------ */

    private function payload(): array
    {
        $htTitle = trim((string) $this->input('headteacher_title', ''));
        if ($htTitle === '') $htTitle = 'Head Teacher';

        return [
            'name'              => trim((string) $this->input('name', '')),
            'code'              => trim((string) $this->input('code', '')),
            'email'             => trim((string) $this->input('email', '')),
            'phone'             => trim((string) $this->input('phone', '')),
            'address'           => trim((string) $this->input('address', '')),
            'motto'             => mb_substr(trim((string) $this->input('motto', '')), 0, 200),
            'headteacher_name'  => mb_substr(trim((string) $this->input('headteacher_name', '')), 0, 150),
            'headteacher_title' => mb_substr($htTitle, 0, 80),
            'status'            => in_array($this->input('status'), ['active', 'inactive'], true)
                                       ? (string) $this->input('status') : 'active',
        ];
    }

    /**
     * Validate and save an uploaded image (logo or signature) for a school.
     * Stores the file as uploads/school-{id}-{type}-{timestamp}.ext and
     * writes the path into the appropriate column. Returns null on success
     * or a user-facing error string on failure.
     */
    private function saveImage(array $file, int $schoolId, string $type): ?string
    {
        $errCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errCode !== UPLOAD_ERR_OK) {
            return 'Upload error (code ' . $errCode . '). Please try again.';
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return 'Suspicious upload rejected.';
        }
        if ((int) $file['size'] > self::UPLOAD_MAX_BYTES) {
            return 'File is too large (max 1.5 MB).';
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']) ?: '';
        if (!isset(self::UPLOAD_MIMES[$mime])) {
            return 'Unsupported file type. Use PNG, JPG, WebP or GIF.';
        }
        $info = @getimagesize($file['tmp_name']);
        if ($info === false || empty($info['mime']) || $info['mime'] !== $mime) {
            return 'The uploaded file does not appear to be a valid image.';
        }

        $dir = dirname(__DIR__, 2) . '/public/uploads';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            return 'Upload folder could not be created.';
        }
        @chmod($dir, 0775);
        if (!is_writable($dir)) {
            return 'Upload folder is not writable. Run: chmod 775 public/uploads';
        }

        $ext  = self::UPLOAD_MIMES[$mime];
        $name = 'school-' . $schoolId . '-' . $type . '-' . time() . '.' . $ext;
        $dest = $dir . '/' . $name;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return 'Could not save the uploaded file.';
        }
        @chmod($dest, 0644);

        $col = $type === 'logo' ? 'logo' : 'headteacher_signature';
        Database::query("UPDATE schools SET {$col} = ? WHERE id = ?", ['uploads/' . $name, $schoolId]);

        return null;
    }

    private function deleteUpload(string $rel): void
    {
        if ($rel === '') return;
        $rel = ltrim($rel, '/');
        if (!str_starts_with($rel, 'uploads/')) return;
        $uploadsDir = realpath(dirname(__DIR__, 2) . '/public/uploads');
        if ($uploadsDir === false) return;
        $abs = realpath(dirname(__DIR__, 2) . '/public/' . $rel);
        if ($abs === false) return;
        if (!str_starts_with($abs, $uploadsDir . DIRECTORY_SEPARATOR)) return;
        if (is_file($abs)) @unlink($abs);
    }
}
