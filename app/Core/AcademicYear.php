<?php
namespace App\Core;

/**
 * Academic years are plain calendar years ("2025", "2026", ...) — the school
 * year and the calendar year are the same thing here.
 *
 * The system originally stored spanning years ("2025/2026"), picked by a
 * September cutover: marks entered Jan–Aug went in as "Y-1/Y" and marks
 * entered Sep–Dec as "Y/Y+1". Both of those describe work done in calendar
 * year Y, which is why legacyToCalendarYear() resolves a spanning value to
 * the year it was actually recorded in rather than blindly taking one side.
 *
 * database/migrate.php converts stored values with that rule;
 * normalize() accepts either form on read.
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
        if (preg_match('~^(\d{4})/(\d{4})$~', $raw, $m)) {
            return self::legacyToCalendarYear($m[1], $m[2]);
        }
        if (preg_match('~^(\d{4})~', $raw, $m)) {
            return $m[1];
        }
        return self::current();
    }

    /**
     * The calendar year a legacy "A/B" academic year actually refers to.
     *
     * The old default rolled over in September, so at any moment only one of
     * the two spanning years was in use:
     *
     *   Jan–Aug 2026 wrote "2025/2026"  -> that work happened in 2026 (B)
     *   Sep–Dec 2026 wrote "2026/2027"  -> that work happened in 2026 (A)
     *
     * So B is the answer whenever B has already started; otherwise the span
     * reaches into the future and A is the year being worked in. Both of the
     * examples above therefore resolve to 2026, which is what the school
     * means by "the 2026 school year".
     *
     * @param int|string $a leading year of the span
     * @param int|string $b trailing year of the span
     */
    public static function legacyToCalendarYear($a, $b): string
    {
        $a = (int) $a;
        $b = (int) $b;
        $now = (int) self::current();

        return (string) ($b <= $now ? $b : $a);
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
