<?php

namespace lameco\blitz\tests\unit;

use enupal\translate\jobs\SyncTranslationsWithDb;
use lameco\blitz\helpers\TranslationSync;
use PHPUnit\Framework\TestCase;

final class TranslationSyncTest extends TestCase
{
    public function testEnupalSyncJobRequiresACacheRefresh(): void
    {
        self::assertTrue(TranslationSync::requiresCacheRefresh(new SyncTranslationsWithDb()));
    }

    public function testOtherJobsDoNotRequireACacheRefresh(): void
    {
        self::assertFalse(TranslationSync::requiresCacheRefresh(new \stdClass()));
    }
}
