<?php
namespace App\Core;

use PDO;
use Throwable;

/**
 * Application-wide key/value settings (school logo, theme, etc.) backed by
 * the `settings` table. Auto-creates the table if it doesn't exist so
 * existing installs work without a manual migration.
 */
class Settings
{
    /** @var array<string,string>|null Cached per-request snapshot. */
    private static ?array $cache = null;

    /** Resolved theme for this request; see activeTheme(). */
    private static ?array $themeCache = null;

    /**
     * After a successful ensure, skip repeating CREATE TABLE — DDL implicitly commits
     * and must not run mid-transaction (e.g. TermResultsService::syncClass).
     */
    private static bool $tableEnsured = false;

    /** Default values used when a key has never been set. */
    private const DEFAULTS = [
        'school_name'  => '',     // empty = fall back to App config
        'school_motto' => '',     // tag-line shown under the school name on
                                  // login and report card headers
        'school_logo'  => '',     // public-relative path, e.g. uploads/logo-123.png
        'theme_accent' => 'blue', // key from THEMES below
        /** Optional JSON array of {label,min,max}; empty = AcademicMarking defaults (SS South Sudan bands). */
        'grading_scale_json' => '',
        // Contact details that appear on official documents
        // (admission letters, exam permits, receipts, reports).
        'school_phone'           => '',
        'school_email'           => '',
        'school_address'         => '',
        // Headship — used to sign admission letters and exam permits.
        'school_headteacher_name'  => '',
        'school_headteacher_title' => 'Head Teacher',
        // Optional scanned signature image (PNG/JPG/WebP). When present
        // it's inlined above the head teacher signature line on
        // admission letters, exam permits and report cards. Falls back
        // to the cursive name styling when blank.
        'school_headteacher_signature' => '',
    ];

    /**
     * Curated accent palettes (buttons, links, active nav). The app sidebar
     * uses a fixed light surface in app.css; `sidebar_bg` is kept for legacy
     * data only and is not applied to the shell.
     */
    private const THEMES = [
        'blue' => [
            'label'        => 'Blue',
            'accent'       => '#2563eb',
            'accent_hover' => '#1d4ed8',
            'accent_soft'  => '#eff4ff',
            'accent_rgb'   => '37, 99, 235',
            'sidebar_bg'   => '#1e40af',
        ],
        'indigo' => [
            'label'        => 'Indigo',
            'accent'       => '#4f46e5',
            'accent_hover' => '#4338ca',
            'accent_soft'  => '#eef2ff',
            'accent_rgb'   => '79, 70, 229',
            'sidebar_bg'   => '#3730a3',
        ],
        'emerald' => [
            'label'        => 'Emerald',
            'accent'       => '#059669',
            'accent_hover' => '#047857',
            'accent_soft'  => '#ecfdf5',
            'accent_rgb'   => '5, 150, 105',
            'sidebar_bg'   => '#065f46',
        ],
        'teal' => [
            'label'        => 'Teal',
            'accent'       => '#0d9488',
            'accent_hover' => '#0f766e',
            'accent_soft'  => '#ecfeff',
            'accent_rgb'   => '13, 148, 136',
            'sidebar_bg'   => '#115e59',
        ],
        'rose' => [
            'label'        => 'Rose',
            'accent'       => '#e11d48',
            'accent_hover' => '#be123c',
            'accent_soft'  => '#fff1f3',
            'accent_rgb'   => '225, 29, 72',
            'sidebar_bg'   => '#9f1239',
        ],
        'amber' => [
            'label'        => 'Amber',
            'accent'       => '#d97706',
            'accent_hover' => '#b45309',
            'accent_soft'  => '#fff7ed',
            'accent_rgb'   => '217, 119, 6',
            'sidebar_bg'   => '#92400e',
        ],
        'slate' => [
            'label'        => 'Charcoal',
            'accent'       => '#475569',
            'accent_hover' => '#334155',
            'accent_soft'  => '#f1f5f9',
            'accent_rgb'   => '71, 85, 105',
            'sidebar_bg'   => '#1f2937',
        ],
        'violet' => [
            'label'        => 'Violet',
            'accent'       => '#7c3aed',
            'accent_hover' => '#6d28d9',
            'accent_soft'  => '#f5f3ff',
            'accent_rgb'   => '124, 58, 237',
            'sidebar_bg'   => '#5b21b6',
        ],
        'fuchsia' => [
            'label'        => 'Fuchsia',
            'accent'       => '#c026d3',
            'accent_hover' => '#a21caf',
            'accent_soft'  => '#fdf4ff',
            'accent_rgb'   => '192, 38, 211',
            'sidebar_bg'   => '#86198f',
        ],
        'sky' => [
            'label'        => 'Sky',
            'accent'       => '#0284c7',
            'accent_hover' => '#0369a1',
            'accent_soft'  => '#f0f9ff',
            'accent_rgb'   => '2, 132, 199',
            'sidebar_bg'   => '#075985',
        ],
        'cyan' => [
            'label'        => 'Cyan',
            'accent'       => '#0891b2',
            'accent_hover' => '#0e7490',
            'accent_soft'  => '#ecfeff',
            'accent_rgb'   => '8, 145, 178',
            'sidebar_bg'   => '#155e75',
        ],
        'lime' => [
            'label'        => 'Lime',
            'accent'       => '#65a30d',
            'accent_hover' => '#4d7c0f',
            'accent_soft'  => '#f7fee7',
            'accent_rgb'   => '101, 163, 13',
            'sidebar_bg'   => '#3f6212',
        ],
        /* orange-700, not the orange-600 this used to be: --accent is
           rendered as link text, and #ea580c is 3.56:1 on white — below
           the 4.5:1 body text needs. #c2410c is 5.18:1. */
        'orange' => [
            'label'        => 'Orange',
            'accent'       => '#c2410c',
            'accent_hover' => '#9a3412',
            'accent_soft'  => '#fff7ed',
            'accent_rgb'   => '194, 65, 12',
            'sidebar_bg'   => '#7c2d12',
        ],
        'ruby' => [
            'label'        => 'Ruby',
            'accent'       => '#dc2626',
            'accent_hover' => '#b91c1c',
            'accent_soft'  => '#fef2f2',
            'accent_rgb'   => '220, 38, 38',
            'sidebar_bg'   => '#991b1b',
        ],
        'midnight' => [
            'label'        => 'Midnight',
            'accent'       => '#1d4ed8',
            'accent_hover' => '#1e40af',
            'accent_soft'  => '#eff4ff',
            'accent_rgb'   => '29, 78, 216',
            'sidebar_bg'   => '#0f172a',
        ],
    ];

    /** Make sure the table exists. Safe to call repeatedly. */
    public static function ensureTable(): void
    {
        if (self::$tableEnsured) {
            return;
        }
        try {
            Database::connection()->exec(
                "CREATE TABLE IF NOT EXISTS settings (
                    `key`       VARCHAR(100) NOT NULL,
                    `value`     TEXT NULL,
                    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`key`)
                ) ENGINE=InnoDB"
            );
            self::$tableEnsured = true;
        } catch (Throwable $e) {
            // Swallow - read paths return defaults if the DB isn't reachable.
        }
    }

    /** Load (and cache) the full settings map for this request. */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $values = self::DEFAULTS;
        try {
            $rows = Database::query("SELECT `key`, `value` FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($rows as $k => $v) {
                $values[$k] = (string) $v;
            }
        } catch (Throwable $e) {
            // Table may not exist yet on a fresh install -- create it lazily.
            self::ensureTable();
        }

        return self::$cache = $values;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $all = self::all();
        if (array_key_exists($key, $all) && $all[$key] !== '') {
            return $all[$key];
        }
        return $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function set(string $key, ?string $value): void
    {
        self::ensureTable();
        Database::query(
            "INSERT INTO settings (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
            [$key, (string) $value]
        );
        self::$cache = null;
    }

    /** Wipe the per-request cache (called after writes). */
    public static function flush(): void
    {
        self::$cache = null;
        // The theme is derived from the settings bag, so it goes stale
        // with it — flushing one without the other would leave a page
        // rendering the colour that was just replaced.
        self::$themeCache = null;
    }

    /** All available theme presets, keyed by id. */
    public static function themes(): array
    {
        return self::THEMES;
    }

    /**
     * The active theme palette for the current request.
     *
     * A school that has chosen its own accent brands the application for
     * its own users; one that has not follows the global `theme_accent`
     * setting, which is what every install did before per-school themes
     * existed. Falls back to 'blue' if a stored key has since been
     * removed from the catalogue.
     *
     * Cached per request: this is read once per page by the layout, but
     * the per-school lookup is a query and callers should not have to
     * know that.
     */
    public static function activeTheme(): array
    {
        if (self::$themeCache !== null) {
            return self::$themeCache;
        }

        $key = '';

        $schoolId = Auth::schoolId();
        if ($schoolId !== null) {
            try {
                $row = Database::query(
                    'SELECT theme_accent FROM schools WHERE id = ? LIMIT 1',
                    [$schoolId]
                )->fetch();
                $key = trim((string) ($row['theme_accent'] ?? ''));
            } catch (Throwable $e) {
                // Column not migrated yet, or the DB is unavailable — fall
                // through to the global setting rather than failing a page
                // render over a colour.
                $key = '';
            }
        }

        if ($key === '' || !isset(self::THEMES[$key])) {
            $key = (string) self::get('theme_accent', 'blue');
        }

        return self::$themeCache = (self::THEMES[$key] ?? self::THEMES['blue']);
    }

    /**
     * The key of the theme activeTheme() resolved to, for preselecting the
     * picker. Matching on the palette rather than re-running the lookup
     * keeps the two from ever disagreeing about what is active.
     */
    public static function activeThemeKey(): string
    {
        $active = self::activeTheme();
        foreach (self::THEMES as $key => $theme) {
            if ($theme === $active) {
                return $key;
            }
        }
        return 'blue';
    }

    /** Drop the per-request theme cache (after a school or setting changes). */
    public static function flushTheme(): void
    {
        self::$themeCache = null;
    }

    /**
     * Returns the logo path (relative to /public) ONLY if the file actually
     * exists on disk. Prevents broken-image icons in the sidebar / report
     * cards when the DB still points to an old/deleted file. Appends a
     * filemtime cache-buster so a freshly-uploaded logo is never served
     * stale by the browser.
     *
     *   <img src="<?= $base ?>/<?= htmlspecialchars(Settings::logoUrl()) ?>">
     *
     * Returns null when no usable logo exists.
     */
    public static function logoUrl(): ?string
    {
        return self::resolveUploadUrl('school_logo');
    }

    /**
     * Returns the head teacher's scanned signature path (relative to
     * /public) only when the file actually exists on disk. Same safety
     * model as logoUrl(): the path must live under public/uploads/ and
     * we append a cache-buster for fresh uploads.
     */
    public static function headteacherSignatureUrl(): ?string
    {
        return self::resolveUploadUrl('school_headteacher_signature');
    }

    /**
     * Internal: resolve a settings key whose value is a public-relative
     * path under uploads/. Returns null when the setting is empty, the
     * path is outside uploads/, or the file is missing on disk.
     */
    private static function resolveUploadUrl(string $key): ?string
    {
        $rel = self::get($key);
        if (!$rel) return null;
        $rel = ltrim($rel, '/');
        // Only allow files inside public/uploads/ (defence-in-depth: a poisoned
        // setting can never make us serve arbitrary disk files).
        if (!str_starts_with($rel, 'uploads/')) return null;

        $absPath = dirname(__DIR__, 2) . '/public/' . $rel;
        if (!is_file($absPath)) return null;

        $mtime = @filemtime($absPath) ?: time();
        return $rel . '?v=' . $mtime;
    }
}
