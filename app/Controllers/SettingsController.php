<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\Settings;

class SettingsController extends Controller
{
    /**
     * Allowed image MIME types for the logo. SVG is intentionally excluded
     * because SVG files can embed `<script>` tags that browsers execute when
     * the file is opened directly (e.g. /uploads/logo.svg), giving an
     * attacker stored XSS even on a hardened uploads directory.
     */
    private const LOGO_MIMES = [
        'image/png'  => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    /** Hard cap for logo uploads. */
    private const LOGO_MAX_BYTES = 1_500_000; // 1.5 MB

    /**
     * Allowed image MIME types for the head teacher's signature. Same
     * security model as the logo (no SVG — avoids embedded scripts).
     */
    private const SIGNATURE_MIMES = self::LOGO_MIMES;

    /** Signatures are usually a few hundred KB at most; cap generously. */
    private const SIGNATURE_MAX_BYTES = 1_500_000; // 1.5 MB

    public function index(): string
    {
        return $this->view('settings/index', [
            'settings' => Settings::all(),
            'themes'   => Settings::themes(),
        ]);
    }

    public function update(): string
    {
        $this->validateCsrf();

        // School name (optional - empty falls back to app config).
        $name = trim((string) $this->input('school_name'));
        Settings::set('school_name', mb_substr($name, 0, 120));

        // School motto / tag-line (optional). Shown on the login page and at
        // the top of every report card under the school name.
        $motto = trim((string) $this->input('school_motto'));
        Settings::set('school_motto', mb_substr($motto, 0, 160));

        // Contact details — appear on the letterhead of every official
        // document (admission letters, exam permits, receipts).
        $phone   = trim((string) $this->input('school_phone'));
        $email   = trim((string) $this->input('school_email'));
        $address = trim((string) $this->input('school_address'));
        Settings::set('school_phone',   mb_substr($phone,   0, 60));
        Settings::set('school_email',   mb_substr($email,   0, 120));
        Settings::set('school_address', mb_substr($address, 0, 250));

        // Headship — signs admission letters and exam permits.
        $htName  = trim((string) $this->input('school_headteacher_name'));
        $htTitle = trim((string) $this->input('school_headteacher_title'));
        if ($htTitle === '') $htTitle = 'Head Teacher';
        Settings::set('school_headteacher_name',  mb_substr($htName,  0, 120));
        Settings::set('school_headteacher_title', mb_substr($htTitle, 0, 60));

        // Theme picker.
        $themeKey = (string) $this->input('theme_accent', 'blue');
        if (!array_key_exists($themeKey, Settings::themes())) {
            $themeKey = 'blue';
        }
        Settings::set('theme_accent', $themeKey);

        // Optional: clear current logo before doing anything else.
        if ($this->input('remove_logo') === '1') {
            $this->deleteCurrentLogo();
            Settings::set('school_logo', '');
        }

        // Handle logo upload (optional).
        if (!empty($_FILES['school_logo']['name'])) {
            $err = $this->saveLogo($_FILES['school_logo']);
            if ($err !== null) {
                Flash::set('danger', $err);
                $this->redirect('/settings');
                return '';
            }
        }

        // Optional: clear current signature first.
        if ($this->input('remove_signature') === '1') {
            $this->deleteCurrentUpload('school_headteacher_signature');
            Settings::set('school_headteacher_signature', '');
        }

        // Handle signature upload (optional).
        if (!empty($_FILES['school_headteacher_signature']['name'])) {
            $err = $this->saveSignature($_FILES['school_headteacher_signature']);
            if ($err !== null) {
                Flash::set('danger', $err);
                $this->redirect('/settings');
                return '';
            }
        }

        Flash::set('success', 'Settings saved.');
        $this->redirect('/settings');
        return '';
    }

    /**
     * Translate the most common PHP upload-error codes to a friendly hint.
     */
    private static function uploadErrorMessage(int $code): string
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'Logo is too large for the server (PHP upload_max_filesize / post_max_size).';
            case UPLOAD_ERR_PARTIAL:
                return 'Logo upload was interrupted. Please try again.';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was selected.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Server is missing a temp upload folder. Contact the administrator.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Server could not write the upload to disk.';
            case UPLOAD_ERR_EXTENSION:
                return 'A PHP extension stopped the upload.';
            default:
                return 'Logo upload failed (error code ' . $code . ').';
        }
    }

    /**
     * Persist an uploaded logo to public/uploads/. Returns null on success,
     * or a user-facing error string on failure.
     */
    private function saveLogo(array $file): ?string
    {
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            return self::uploadErrorMessage($err);
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return 'Suspicious upload rejected.';
        }
        if ((int) $file['size'] <= 0) {
            return 'The uploaded file is empty.';
        }
        if ((int) $file['size'] > self::LOGO_MAX_BYTES) {
            return 'Logo is too large. Max ' . (self::LOGO_MAX_BYTES / 1024 / 1024) . ' MB.';
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']) ?: '';
        if (!isset(self::LOGO_MIMES[$mime])) {
            return 'Unsupported file type. Use PNG, JPG, WebP or GIF.';
        }

        $info = @getimagesize($file['tmp_name']);
        if ($info === false || empty($info['mime']) || $info['mime'] !== $mime) {
            return 'The uploaded file does not look like a valid image.';
        }

        $ext = self::LOGO_MIMES[$mime];
        $dir = dirname(__DIR__, 2) . '/public/uploads';

        // Make sure the directory exists AND the web server can actually
        // write to it. This is the most common failure mode on shared
        // hosting / fresh XAMPP installs (folder owned by the developer at
        // mode 755, web server runs as a different user and silently fails
        // on move_uploaded_file). Surface a specific, actionable error.
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
                return 'Upload folder could not be created at public/uploads. Create it and grant the web server write permission.';
            }
        }
        if (!is_writable($dir)) {
            // Best-effort permission bump (works when the folder is owned
            // by the same user PHP runs as). If we can't fix it, return a
            // clear, copy-pasteable hint.
            @chmod($dir, 0775);
            if (!is_writable($dir)) {
                return 'Upload folder is not writable by the web server. On a terminal, run: chmod 775 public/uploads (and chown the folder to the web server user if needed).';
            }
        }

        $this->deleteCurrentLogo();

        $name = 'school-logo-' . time() . '.' . $ext;
        $dest = $dir . '/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $errMsg = error_get_last()['message'] ?? '';
            return 'Could not save the uploaded file' . ($errMsg !== '' ? ' (' . $errMsg . ')' : '') . '.';
        }
        @chmod($dest, 0644);

        Settings::set('school_logo', 'uploads/' . $name);
        return null;
    }

    private function deleteCurrentLogo(): void
    {
        $this->deleteCurrentUpload('school_logo');
    }

    /**
     * Persist an uploaded head teacher signature image. Same validation
     * model as the school logo (MIME, magic bytes, size, write perms),
     * stored alongside it under public/uploads/.
     */
    private function saveSignature(array $file): ?string
    {
        $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            return self::uploadErrorMessage($err);
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return 'Suspicious upload rejected.';
        }
        if ((int) $file['size'] <= 0) {
            return 'The uploaded signature is empty.';
        }
        if ((int) $file['size'] > self::SIGNATURE_MAX_BYTES) {
            return 'Signature is too large. Max ' . (self::SIGNATURE_MAX_BYTES / 1024 / 1024) . ' MB.';
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']) ?: '';
        if (!isset(self::SIGNATURE_MIMES[$mime])) {
            return 'Unsupported file type. Use PNG, JPG, WebP or GIF.';
        }
        $info = @getimagesize($file['tmp_name']);
        if ($info === false || empty($info['mime']) || $info['mime'] !== $mime) {
            return 'The uploaded file does not look like a valid image.';
        }

        $ext = self::SIGNATURE_MIMES[$mime];
        $dir = dirname(__DIR__, 2) . '/public/uploads';
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
                return 'Upload folder could not be created at public/uploads.';
            }
        }
        if (!is_writable($dir)) {
            @chmod($dir, 0775);
            if (!is_writable($dir)) {
                return 'Upload folder is not writable. Run: chmod 775 public/uploads';
            }
        }

        $this->deleteCurrentUpload('school_headteacher_signature');

        $name = 'headteacher-signature-' . time() . '.' . $ext;
        $dest = $dir . '/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return 'Could not save the signature image.';
        }
        @chmod($dest, 0644);

        Settings::set('school_headteacher_signature', 'uploads/' . $name);
        return null;
    }

    /**
     * Path-traversal-safe delete for a settings-driven upload. Used by
     * both the logo and the signature so neither can wipe a file that
     * sits outside public/uploads/.
     */
    private function deleteCurrentUpload(string $settingKey): void
    {
        $current = Settings::get($settingKey);
        if (!$current) return;
        $uploadsDir = realpath(dirname(__DIR__, 2) . '/public/uploads');
        if ($uploadsDir === false) return;
        $abs = realpath(dirname(__DIR__, 2) . '/public/' . ltrim($current, '/'));
        if ($abs === false) return;
        if (!str_starts_with($abs, $uploadsDir . DIRECTORY_SEPARATOR)) return;
        if (is_file($abs)) {
            @unlink($abs);
        }
    }
}
