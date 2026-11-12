<?php

namespace lameco\blitz\helpers;

use putyourlightson\blitz\models\CacheOptionsModel;

/**
 * Blitz cache tags for static translations, as Blitz's maintainer recommends for content Blitz cannot
 * track itself (putyourlightson/craft-blitz#649): each cached page is tagged with the translations it
 * rendered, and a changed translation refreshes only the pages tagged with it.
 */
final class TranslationTags
{
    private const PREFIX = 'lameco-t:';

    /**
     * Messages can contain spaces and commas, which Blitz tags cannot, so the tag is a hash.
     */
    public static function tag(string $language, string $category, string $message): string
    {
        return self::PREFIX . md5($language . "\0" . $category . "\0" . $message);
    }

    public static function addTo(CacheOptionsModel $options, string $tag): void
    {
        $tags = is_array($options->tags)
            ? $options->tags
            : array_filter(array_map('trim', explode(',', $options->tags)), fn(string $tag) => $tag !== '');

        if (!in_array($tag, $tags, true)) {
            $tags[] = $tag;
            $options->tags = array_values($tags);
        }
    }

    /**
     * Enupal Translate ignores empty translations, so an empty value counts as no translation.
     *
     * @param array<array-key, mixed> $old
     * @param array<array-key, mixed> $new
     * @return string[]
     */
    public static function changedMessages(array $old, array $new): array
    {
        $old = self::withoutEmpty($old);
        $new = self::withoutEmpty($new);
        $changed = [];

        foreach ($old + $new as $message => $value) {
            if (($old[$message] ?? null) !== ($new[$message] ?? null)) {
                $changed[] = (string)$message;
            }
        }

        return $changed;
    }

    /**
     * Yii looks up a message for `nl-NL` in `translations/nl-NL/` and falls back to `translations/nl/`,
     * so a change in a language-only file also concerns pages rendered in that language's regions.
     *
     * @param array<string, string[]> $changedMessagesByLocale Locale of the changed file => its changed messages
     * @param string[] $languages The languages pages are rendered in
     * @return string[]
     */
    public static function forChanges(array $changedMessagesByLocale, array $languages, string $category): array
    {
        $tags = [];

        foreach ($changedMessagesByLocale as $locale => $messages) {
            foreach ($languages as $language) {
                if ($language !== $locale && !str_starts_with($language, $locale . '-')) {
                    continue;
                }

                foreach ($messages as $message) {
                    $tags[] = self::tag($language, $category, $message);
                }
            }
        }

        return array_values(array_unique($tags));
    }

    /**
     * @param array<array-key, mixed> $translations
     * @return array<array-key, string>
     */
    private static function withoutEmpty(array $translations): array
    {
        $result = [];

        foreach ($translations as $message => $value) {
            if (is_scalar($value) && trim((string)$value) !== '') {
                $result[$message] = (string)$value;
            }
        }

        return $result;
    }
}
