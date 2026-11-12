<?php

namespace lameco\blitz\helpers;

use Craft;
use craft\db\Query;

/**
 * Reads which site translations Enupal Translate is about to sync.
 *
 * Enupal writes a save to the translation file and then queues SyncTranslationsWithDb, which copies the
 * file into its database tables. Until that job runs, the tables still hold the previous translations,
 * so comparing them with the files gives exactly the changed messages.
 */
final class EnupalTranslationChanges
{
    /**
     * @return array<string, string[]> Locale => changed messages
     */
    public static function pending(): array
    {
        $previous = [];

        $rows = (new Query())
            ->select(['sourceMessage.message', 'message.translation', 'message.language'])
            ->from('{{%enupaltranslate_sourcemessage}} sourceMessage')
            ->innerJoin('{{%enupaltranslate_message}} message', '[[sourceMessage.id]] = [[message.id]]')
            ->where(['sourceMessage.category' => 'site'])
            ->all();

        foreach ($rows as $row) {
            $previous[$row['language']][$row['message']] = $row['translation'];
        }

        $changes = [];
        $translationsPath = Craft::$app->getPath()->getSiteTranslationsPath();

        // The same locales and file Enupal syncs: services\Translate::getSitePath().
        foreach (Craft::$app->getI18n()->getSiteLocaleIds() as $locale) {
            $file = $translationsPath . DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . 'site.php';
            $current = is_file($file) ? include $file : [];

            $changed = TranslationTags::changedMessages($previous[$locale] ?? [], is_array($current) ? $current : []);

            if ($changed !== []) {
                $changes[$locale] = $changed;
            }
        }

        return $changes;
    }
}
