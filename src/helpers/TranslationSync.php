<?php

namespace lameco\blitz\helpers;

/**
 * Recognises the queue job after which Enupal Translate's static translations have changed.
 *
 * Blitz only tracks elements, so a translation edited in Enupal Translate left the cached pages and
 * craft.blitz.includeCached() fragments showing the old text. Enupal fires no event when it saves;
 * every save, CSV import, provider translation and console sync instead queues
 * SyncTranslationsWithDb, which also writes the translation files, in Enupal 4 and 5 alike.
 */
final class TranslationSync
{
    // Referenced by name: Enupal Translate is optional, not a dependency of this plugin.
    private const SYNC_JOB_CLASS = 'enupal\translate\jobs\SyncTranslationsWithDb';

    public static function requiresCacheRefresh(object $job): bool
    {
        return is_a($job, self::SYNC_JOB_CLASS);
    }
}
