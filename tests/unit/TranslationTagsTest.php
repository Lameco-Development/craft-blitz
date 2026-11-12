<?php

namespace lameco\blitz\tests\unit;

use lameco\blitz\helpers\TranslationTags;
use PHPUnit\Framework\TestCase;
use putyourlightson\blitz\models\CacheOptionsModel;

final class TranslationTagsTest extends TestCase
{
    public function testTagIsStableAndFitsBlitzTagRules(): void
    {
        $tag = TranslationTags::tag('nl-NL', 'site', 'Share this article');

        self::assertSame($tag, TranslationTags::tag('nl-NL', 'site', 'Share this article'));
        self::assertDoesNotMatchRegularExpression('/[\s,]/', $tag);
        self::assertLessThanOrEqual(255, strlen($tag));
    }

    public function testTagDiffersPerLanguageCategoryAndMessage(): void
    {
        $tags = [
            TranslationTags::tag('nl-NL', 'site', 'a'),
            TranslationTags::tag('da-DK', 'site', 'a'),
            TranslationTags::tag('nl-NL', 'consent', 'a'),
            TranslationTags::tag('nl-NL', 'site', 'b'),
        ];

        self::assertCount(4, array_unique($tags));
    }

    public function testAddingATagToCacheOptionsKeepsItOnce(): void
    {
        $options = new CacheOptionsModel();
        $options->tags = 'home';

        TranslationTags::addTo($options, 'lameco-t:1');
        TranslationTags::addTo($options, 'lameco-t:1');

        self::assertSame(['home', 'lameco-t:1'], $options->tags);
    }

    public function testAddingToEmptyCacheOptionsAddsNoEmptyTag(): void
    {
        $options = new CacheOptionsModel();

        TranslationTags::addTo($options, 'lameco-t:1');

        self::assertSame(['lameco-t:1'], $options->tags);
    }

    public function testChangedMessagesAreNewChangedOrRemovedTranslations(): void
    {
        $old = ['same' => 'Zelfde', 'changed' => 'Oud', 'removed' => 'Weg', 'blank' => ''];
        $new = ['same' => 'Zelfde', 'changed' => 'Nieuw', 'added' => 'Erbij', 'blank' => '  '];

        $changed = TranslationTags::changedMessages($old, $new);
        sort($changed);

        self::assertSame(['added', 'changed', 'removed'], $changed);
    }

    public function testEmptyTranslationCountsAsNoTranslation(): void
    {
        // Enupal drops empty values, so a key going from empty to missing changes nothing on the page.
        self::assertSame([], TranslationTags::changedMessages(['a' => ''], []));
        self::assertSame(['a'], TranslationTags::changedMessages(['a' => ''], ['a' => 'Tekst']));
    }

    public function testChangeInARegionalFileTagsOnlyThatLanguage(): void
    {
        $tags = TranslationTags::forChanges(['nl-NL' => ['a']], ['nl-NL', 'nl-BE', 'da-DK'], 'site');

        self::assertSame([TranslationTags::tag('nl-NL', 'site', 'a')], $tags);
    }

    public function testChangeInALanguageFileAlsoTagsTheRegionsThatFallBackToIt(): void
    {
        // Yii falls back from nl-NL to translations/nl/ for messages nl-NL lacks.
        $tags = TranslationTags::forChanges(['nl' => ['a']], ['nl-NL', 'nl-BE', 'da-DK', 'nl'], 'site');
        sort($tags);

        $expected = [
            TranslationTags::tag('nl', 'site', 'a'),
            TranslationTags::tag('nl-BE', 'site', 'a'),
            TranslationTags::tag('nl-NL', 'site', 'a'),
        ];
        sort($expected);

        self::assertSame($expected, $tags);
    }
}
