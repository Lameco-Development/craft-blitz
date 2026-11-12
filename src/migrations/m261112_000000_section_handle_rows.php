<?php

namespace lameco\blitz\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use lameco\blitz\helpers\SectionRowMigration;

/**
 * Converts 5.0.x section-exclusion rows, which stored a section ID, to "sectionHandle:entryTypeHandle"
 * rows. Without it, a site upgrading from 5.0.x keeps rows that never match, so its exclusions stop
 * working without any error.
 */
class m261112_000000_section_handle_rows extends Migration
{
    private const SETTINGS_PATH = 'plugins._craft-blitz.settings.sectionQueryStringParams';

    public function safeUp(): bool
    {
        $projectConfig = Craft::$app->getProjectConfig();

        // Environments that apply project config get the converted rows from the YAML instead.
        $schemaVersion = (string)$projectConfig->get('plugins._craft-blitz.schemaVersion', true);
        if (version_compare($schemaVersion, '2.0.0', '>=')) {
            return true;
        }

        $rows = $projectConfig->get(self::SETTINGS_PATH);
        if (!is_array($rows) || $rows === []) {
            return true;
        }

        $entries = Craft::$app->getEntries();
        $result = SectionRowMigration::migrate(
            ProjectConfigHelper::unpackAssociativeArrays($rows),
            static function(int $sectionId) use ($entries): ?array {
                $section = $entries->getSectionById($sectionId);

                return $section === null ? null : [
                    'handle' => $section->handle,
                    'entryTypes' => array_map(fn($entryType) => $entryType->handle, $section->getEntryTypes()),
                ];
            },
        );

        foreach ($result['unresolved'] as $sectionId) {
            Craft::warning("Dropped the cache exclusion row for section ID $sectionId: the section no longer exists.", __METHOD__);
        }

        $projectConfig->set(
            self::SETTINGS_PATH,
            ProjectConfigHelper::packAssociativeArrays($result['rows']),
            'Convert Laméco Blitz section exclusions from section IDs to section and entry type handles',
        );

        return true;
    }

    public function safeDown(): bool
    {
        echo "m261112_000000_section_handle_rows cannot be reverted.\n";

        return false;
    }
}
