<?php

namespace lameco\blitz\tests\unit;

use lameco\blitz\helpers\SectionRowMigration;
use PHPUnit\Framework\TestCase;

final class SectionRowMigrationTest extends TestCase
{
    /**
     * @return callable(int): ?array{handle: string, entryTypes: string[]}
     */
    private static function resolver(): callable
    {
        $sections = [
            26 => ['handle' => 'searchPage', 'entryTypes' => ['commonPage']],
            33 => ['handle' => 'news', 'entryTypes' => ['article', 'overview']],
        ];

        return static fn(int $id): ?array => $sections[$id] ?? null;
    }

    public function testSectionIdRowBecomesOneHandleRowPerEntryType(): void
    {
        $result = SectionRowMigration::migrate([
            ['section' => '26', 'queryStringParams' => 'query'],
            ['section' => '33', 'queryStringParams' => 'id,page'],
        ], self::resolver());

        self::assertSame([
            ['section' => 'searchPage:commonPage', 'queryStringParams' => 'query'],
            ['section' => 'news:article', 'queryStringParams' => 'id,page'],
            ['section' => 'news:overview', 'queryStringParams' => 'id,page'],
        ], $result['rows']);
        self::assertSame([], $result['unresolved']);
    }

    public function testHandleRowsAreKeptAsTheyAre(): void
    {
        $rows = [['section' => 'news:overview', 'queryStringParams' => 'page']];

        $result = SectionRowMigration::migrate($rows, self::resolver());

        self::assertSame($rows, $result['rows']);
    }

    public function testRowsForSectionsThatNoLongerExistAreReportedAndDropped(): void
    {
        $result = SectionRowMigration::migrate([
            ['section' => '99', 'queryStringParams' => 'query'],
            ['section' => '26', 'queryStringParams' => 'query'],
        ], self::resolver());

        self::assertSame([['section' => 'searchPage:commonPage', 'queryStringParams' => 'query']], $result['rows']);
        self::assertSame(['99'], $result['unresolved']);
    }

    public function testRowsAreReindexedAsAList(): void
    {
        // Project config can store the rows with explicit keys (fivoor: 0:, 1:, …).
        $result = SectionRowMigration::migrate([5 => ['section' => 'news:overview', 'queryStringParams' => 'page']], self::resolver());

        self::assertSame([0], array_keys($result['rows']));
    }
}
