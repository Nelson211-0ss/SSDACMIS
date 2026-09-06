<?php
namespace App\Core;

/**
 * Academic years are plain calendar years ("2025", "2026", ...).
 *
 * The system originally stored spanning years ("2025/2026"). Everything now
 * reads and writes the flat form; legacy values are normalised on read via
 * normalize() and converted in place by database/migrate.php, which rewrites
 * every "YYYY/YYYY" academic_year to its leading year.
 */
final class AcademicYear
{
    /** How many past / future years the pickers offer around the current one. */
    private const BACK    = 4;
    private const FORWARD = 2;

    /** The current academic year — the calendar year we are in. */
    public static function current(): string
    {
        return date('Y');
    }

    /**
     * Years offered by every year picker: a few back, a couple forward, most
     * recent first so the useful end of the list is at the top.
     *
     * @return list<string>
     */
    public static function options(): array
    {
        $now  = (int) self::current();
        $out = [];
        for ($y = $now + self::FORWARD; $y >= $now - self::BACK; $y--) {
            $out[] = (string) $y;
        }
        return $out;
    }

    /** True for a plain four-digit year inside the supported range. */
    public static function isValid(string $year): bool
    {
        return in_array(self::normalize($year), self::options(), true);
    }

    /**
     * Accepts either the flat form or a legacy "YYYY/YYYY" value and returns
     * the flat one. Anything unrecognisable falls back to the current year so
     * a hand-edited URL can never produce an empty period.
     */
    public static function normalize(?string $year): string
    {
        $raw = trim((string) $year);
        if (preg_match('~^(\d{4})~', $raw, $m)) {
            return $m[1];
        }
        return self::current();
    }

    /**
     * Resolve a request parameter into a usable academic year: normalised,
     * and clamped to the offered options when it falls outside them.
     */
    public static function resolve(?string $year): string
    {
        $norm = self::normalize($year);
        return self::isValid($norm) ? $norm : self::current();
    }
}
