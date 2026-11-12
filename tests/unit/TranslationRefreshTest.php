<?php

namespace lameco\blitz\tests\unit;

use enupal\translate\jobs\SyncTranslationsWithDb;
use lameco\blitz\helpers\TranslationRefresh;
use PHPUnit\Framework\TestCase;
use yii\base\Event;
use yii\queue\ExecEvent;
use yii\queue\Queue;

final class TranslationRefreshTest extends TestCase
{
    /** @var list<string[]> */
    private array $refreshedTags = [];

    private int $refreshedAll = 0;

    protected function tearDown(): void
    {
        Event::offAll();
    }

    /**
     * @param string[]|null $changedTags
     */
    private function refresh(?array $changedTags): TranslationRefresh
    {
        $refresh = new TranslationRefresh(
            fn(): ?array => $changedTags,
            function(array $tags): void {
                $this->refreshedTags[] = $tags;
            },
            function(): void {
                $this->refreshedAll++;
            },
        );
        $refresh->attach();

        return $refresh;
    }

    private static function runJob(ExecEvent $event, bool $fails = false): void
    {
        Event::trigger(Queue::class, Queue::EVENT_BEFORE_EXEC, $event);
        Event::trigger(Queue::class, $fails ? Queue::EVENT_AFTER_ERROR : Queue::EVENT_AFTER_EXEC, $event);
    }

    private static function event(object $job, string $id = '7'): ExecEvent
    {
        return new ExecEvent(['job' => $job, 'id' => $id]);
    }

    public function testChangedTagsAreRefreshedAfterTheSyncJob(): void
    {
        $this->refresh(['lameco-t:a']);
        $event = self::event(new SyncTranslationsWithDb());

        Event::trigger(Queue::class, Queue::EVENT_BEFORE_EXEC, $event);
        self::assertSame([], $this->refreshedTags, 'nothing is refreshed before the job has run');

        Event::trigger(Queue::class, Queue::EVENT_AFTER_EXEC, $event);
        self::assertSame([['lameco-t:a']], $this->refreshedTags);
    }

    public function testChangedTagsAreRefreshedWhenTheSyncJobFails(): void
    {
        // Enupal writes the translation file before the job runs, so the new text is live either way;
        // a retry would miss the messages the failed attempt already copied to Enupal's tables.
        $this->refresh(['lameco-t:a']);
        $event = self::event(new SyncTranslationsWithDb());

        self::runJob($event, fails: true);

        self::assertSame([['lameco-t:a']], $this->refreshedTags);
    }

    public function testEachRunIsRefreshedOnce(): void
    {
        $this->refresh(['lameco-t:a']);
        $event = self::event(new SyncTranslationsWithDb());

        self::runJob($event);
        Event::trigger(Queue::class, Queue::EVENT_AFTER_EXEC, $event);

        self::assertCount(1, $this->refreshedTags);
    }

    public function testUnknownChangesRefreshTheWholeCache(): void
    {
        $this->refresh(null);
        $event = self::event(new SyncTranslationsWithDb());

        self::runJob($event);

        self::assertSame(1, $this->refreshedAll);
        self::assertSame([], $this->refreshedTags);
    }

    public function testNoChangesRefreshNothing(): void
    {
        $this->refresh([]);
        $event = self::event(new SyncTranslationsWithDb());

        self::runJob($event);

        self::assertSame(0, $this->refreshedAll);
        self::assertSame([], $this->refreshedTags);
    }

    public function testOtherJobsAreIgnored(): void
    {
        $this->refresh(['lameco-t:a']);
        $event = self::event(new \stdClass());

        self::runJob($event);

        self::assertSame(0, $this->refreshedAll);
        self::assertSame([], $this->refreshedTags);
    }
}
