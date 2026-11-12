<?php

namespace lameco\blitz\i18n;

use craft\i18n\PhpMessageSource;
use lameco\blitz\helpers\TranslationTags;
use putyourlightson\blitz\Blitz;

/**
 * Craft's site translation source, which also tags the page Blitz is caching with every message it
 * looks up. Recording at the source catches every |t filter and Craft::t() call, including messages
 * built at runtime, without changing templates.
 */
class TaggingPhpMessageSource extends PhpMessageSource
{
    public function translate($category, $message, $language)
    {
        // Tags only end up in the cache when Blitz saves this request's output; on any other request
        // they are discarded with the cache options.
        if (isset(Blitz::$plugin)) {
            TranslationTags::addTo(
                Blitz::$plugin->generateCache->options,
                TranslationTags::tag((string)$language, (string)$category, (string)$message),
            );
        }

        return parent::translate($category, $message, $language);
    }
}
