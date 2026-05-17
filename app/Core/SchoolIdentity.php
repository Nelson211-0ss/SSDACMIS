<?php
namespace App\Core;

use Throwable;

/**
 * Resolves the school's public-facing identity details for the current
 * request — name, address, phone, email, logo, motto, and headteacher.
 *
 * Priority rules:
 *   1. When the session belongs to a school-scoped user (school_admin,
 *      staff, hod, bursar, student), the `schools` table row for their
 *      school_id is checked first for name / address / phone / email.
 *   2. The `settings` table is always used for motto, logo, headteacher
 *      details, and as a fallback for any contact field that is blank in
 *      `schools`.
 *   3. The super-admin (`role = 'admin'`, school_id = NULL) uses only
 *      the `settings` table, as before.
 *
 * Cached per request so multiple calls within one page load cost nothing.
 */
final class SchoolIdentity
{
    /** @var array<string,string>|null */
    private static ?array $cache = null;

    /**
     * Return the full resolved identity bag.
     *
     * Keys always present:
     *   school_name, school_motto, school_address, school_phone,
     *   school_email, school_code, school_logo (nullable string),
     *   school_headteacher_name, school_headteacher_title,
     *   school_headteacher_signature (nullable string)
     *
     * @return array<string, string|null>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $s = Settings::all();

        // Defaults from the global settings table
        $bag = [
            'school_name'                  => $s['school_name']  ?: (string) App::config('app.name'),
            'school_motto'                 => $s['school_motto'] ?? '',
            'school_address'               => $s['school_address'] ?? '',
            'school_phone'                 => $s['school_phone']   ?? '',
            'school_email'                 => $s['school_email']   ?? '',
            'school_code'                  => '',
            'school_logo'                  => Settings::logoUrl(),
            'school_headteacher_name'      => trim((string) ($s['school_headteacher_name']  ?? '')),
            'school_headteacher_title'     => trim((string) ($s['school_headteacher_title'] ?? 'Head Teacher')) ?: 'Head Teacher',
            'school_headteacher_signature' => Settings::headteacherSignatureUrl(),
        ];

        // Overlay per-school details when the user belongs to a specific school.
        $schoolId = Auth::schoolId();
        if ($schoolId !== null) {
            try {
                $row = Database::query(
                    'SELECT name, code, email, phone, address,
                            motto, logo, headteacher_name, headteacher_title,
                            headteacher_signature
                     FROM schools WHERE id = ? LIMIT 1',
                    [$schoolId]
                )->fetch();

                if ($row) {
                    // Name: prefer the schools table when it has a real value.
                    if (!empty(trim((string) $row['name']))) {
                        $bag['school_name'] = trim((string) $row['name']);
                    }
                    $bag['school_code'] = trim((string) ($row['code'] ?? ''));

                    // Contact: use schools table if non-empty, else keep settings.
                    foreach (['address', 'phone', 'email'] as $field) {
                        $val = trim((string) ($row[$field] ?? ''));
                        if ($val !== '') {
                            $bag['school_' . $field] = $val;
                        }
                    }

                    // Motto, logo, headteacher — prefer per-school columns when present.
                    $motto = trim((string) ($row['motto'] ?? ''));
                    if ($motto !== '') $bag['school_motto'] = $motto;

                    // Logo: resolve from schools.logo, falling back to settings logo.
                    $logoRel = trim((string) ($row['logo'] ?? ''));
                    if ($logoRel !== '') {
                        $logoRel = ltrim($logoRel, '/');
                        if (str_starts_with($logoRel, 'uploads/')) {
                            $absPath = dirname(__DIR__, 2) . '/public/' . $logoRel;
                            if (is_file($absPath)) {
                                $mtime = @filemtime($absPath) ?: time();
                                $bag['school_logo'] = $logoRel . '?v=' . $mtime;
                            }
                        }
                    }

                    // Head teacher name and title.
                    $ht = trim((string) ($row['headteacher_name'] ?? ''));
                    if ($ht !== '') $bag['school_headteacher_name'] = $ht;
                    $htTitle = trim((string) ($row['headteacher_title'] ?? ''));
                    if ($htTitle !== '') $bag['school_headteacher_title'] = $htTitle;

                    // Headteacher signature.
                    $sigRel = trim((string) ($row['headteacher_signature'] ?? ''));
                    if ($sigRel !== '') {
                        $sigRel = ltrim($sigRel, '/');
                        if (str_starts_with($sigRel, 'uploads/')) {
                            $absPath = dirname(__DIR__, 2) . '/public/' . $sigRel;
                            if (is_file($absPath)) {
                                $mtime = @filemtime($absPath) ?: time();
                                $bag['school_headteacher_signature'] = $sigRel . '?v=' . $mtime;
                            }
                        }
                    }
                }
            } catch (Throwable $e) {
                // DB unavailable or missing column — keep settings fallback.
            }
        }

        return self::$cache = $bag;
    }

    public static function get(string $key): ?string
    {
        return self::all()[$key] ?? null;
    }

    /** Flush the per-request cache (useful after settings or school updates). */
    public static function flush(): void
    {
        self::$cache = null;
    }

    /* ------------------------------------------------------------------ */
    /* Convenience short-hands for the most commonly used fields           */
    /* ------------------------------------------------------------------ */

    public static function name(): string
    {
        return (string) (self::all()['school_name'] ?? '');
    }

    public static function motto(): string
    {
        return (string) (self::all()['school_motto'] ?? '');
    }

    public static function address(): string
    {
        return (string) (self::all()['school_address'] ?? '');
    }

    public static function phone(): string
    {
        return (string) (self::all()['school_phone'] ?? '');
    }

    public static function email(): string
    {
        return (string) (self::all()['school_email'] ?? '');
    }

    /** Public-relative logo URL (e.g. "uploads/logo-123.png?v=…") or null. */
    public static function logoUrl(): ?string
    {
        return self::all()['school_logo'];
    }

    public static function headteacherName(): string
    {
        return (string) (self::all()['school_headteacher_name'] ?? '');
    }

    public static function headteacherTitle(): string
    {
        return (string) (self::all()['school_headteacher_title'] ?? 'Head Teacher');
    }

    /** Public-relative signature URL or null. */
    public static function headteacherSignatureUrl(): ?string
    {
        return self::all()['school_headteacher_signature'];
    }
}
