<?php

namespace lameco\blitz\helpers;

/**
 * Converts section-exclusion rows from the 5.0.x format, which stored a section ID, to the
 * "sectionHandle:entryTypeHandle" format the plugin matches on. A 5.0.x row applied to every entry
 * type of its section, so it becomes one row per entry type.
 */
final class SectionRowMigration
{
    /**
     * @param array<array-key, mixed> $rows
     * @param callable(int): (array{handle: string, entryTypes: string[]}|null) $resolveSection
     * @return array{rows: list<array<array-key, mixed>>, unresolved: list<string>}
     */
    public static function migrate(array $rows, callable $resolveSection): array
    {
        $migrated = [];
        $unresolved = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $section = (string)($row['section'] ?? '');

            if (!ctype_digit($section)) {
                $migrated[] = $row;
                continue;
            }

            $resolved = $resolveSection((int)$section);

            if ($resolved === null) {
                $unresolved[] = $section;
                continue;
            }

            foreach ($resolved['entryTypes'] as $entryTypeHandle) {
                $migrated[] = ['section' => $resolved['handle'] . ':' . $entryTypeHandle] + $row;
            }
        }

        return ['rows' => $migrated, 'unresolved' => $unresolved];
    }
}
