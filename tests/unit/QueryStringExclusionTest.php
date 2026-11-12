<?php

namespace lameco\blitz\tests\unit;

use lameco\blitz\helpers\QueryStringExclusion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QueryStringExclusionTest extends TestCase
{
    public function testParamListIsTrimmedAndEmptyEntriesDropped(): void
    {
        self::assertSame(['q', 'page'], QueryStringExclusion::parseParamList(' q , ,page,'));
    }

    public function testArrayNotationIsReducedToTheKeyPhpParsesItInto(): void
    {
        // PHP parses ?filters[]=1 into the key "filters", so a list entry "filters[]" must match that key.
        self::assertSame(['filters', 'slideout'], QueryStringExclusion::parseParamList('filters[],slideout'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function presentParamProvider(): iterable
    {
        yield 'truthy value' => [['q' => 'garage']];
        yield 'zero' => [['q' => '0']];
        yield 'empty value' => [['q' => '']];
        yield 'array' => [['q' => ['0']]];
        yield 'empty array' => [['q' => []]];
    }

    /**
     * @param array<string, mixed> $queryParams
     */
    #[DataProvider('presentParamProvider')]
    public function testGlobalParamExcludesWhenPresentWhateverItsValue(array $queryParams): void
    {
        self::assertTrue(QueryStringExclusion::isExcluded($queryParams, 'q,page', [], null, null));
    }

    public function testUnlistedParamsDoNotExclude(): void
    {
        self::assertFalse(QueryStringExclusion::isExcluded(['utm_source' => 'x', 'gclid' => 'y'], 'q,page', [], 'pages', 'page'));
    }

    public function testArrayNotationParamMatchesParsedArrayParam(): void
    {
        self::assertTrue(QueryStringExclusion::isExcluded(['filters' => ['12']], 'filters[]', [], null, null));
    }

    public function testSectionParamExcludesOnlyOnItsSectionAndEntryType(): void
    {
        $rows = [['section' => 'news:newsOverview', 'queryStringParams' => 'category, page']];

        self::assertTrue(QueryStringExclusion::isExcluded(['category' => '0'], '', $rows, 'news', 'newsOverview'));
        self::assertFalse(QueryStringExclusion::isExcluded(['category' => '0'], '', $rows, 'news', 'article'));
        self::assertFalse(QueryStringExclusion::isExcluded(['category' => '0'], '', $rows, 'blog', 'newsOverview'));
    }

    public function testSectionRowsDoNotApplyToEntriesWithoutASection(): void
    {
        $rows = [['section' => ':', 'queryStringParams' => 'category']];

        self::assertFalse(QueryStringExclusion::isExcluded(['category' => '1'], '', $rows, null, null));
    }

    public function testIncompleteRowsAreIgnored(): void
    {
        $rows = [['section' => 'news:newsOverview'], ['queryStringParams' => 'category'], []];

        self::assertFalse(QueryStringExclusion::isExcluded(['category' => '1'], '', $rows, 'news', 'newsOverview'));
    }
}
