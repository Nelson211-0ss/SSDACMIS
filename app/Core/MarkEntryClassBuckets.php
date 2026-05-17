<?php
namespace App\Core;

/**
 * HOD-style mark-entry UIs stack one card per {@see classes} row under Form 1–4.
 * Schools sometimes keep a redundant "shell" class whose {@see name} equals the
 * form {@see level} (e.g. "Form 3") alongside real streams ("Form 3A"). Hide the
 * shell when at least one named stream exists so each form shows once.
 */
final class MarkEntryClassBuckets
{
    /**
     * @param array<string, list<array{name?:mixed,level?:mixed,...}>> $byForm
     * @return array<string, list<array{name?:mixed,level?:mixed,...}>>
     */
    public static function dropRedundantShellClasses(array $byForm): array
    {
        foreach ($byForm as &$bucket) {
            $hasNamedStream = false;
            foreach ($bucket as $row) {
                $nm = trim((string) ($row['name'] ?? ''));
                $lv = trim((string) ($row['level'] ?? ''));
                if ($nm !== '' && $lv !== '' && strcasecmp($nm, $lv) !== 0) {
                    $hasNamedStream = true;
                    break;
                }
            }
            if (!$hasNamedStream) {
                continue;
            }
            $bucket = array_values(array_filter(
                $bucket,
                static function (array $row): bool {
                    $nm = trim((string) ($row['name'] ?? ''));
                    $lv = trim((string) ($row['level'] ?? ''));
                    return $nm === '' || $lv === '' || strcasecmp($nm, $lv) !== 0;
                }
            ));
        }
        unset($bucket);

        return $byForm;
    }
}
