<?php

namespace lameco\blitz\helpers;

use Closure;
use yii\base\Event;
use yii\queue\ExecEvent;
use yii\queue\Queue;

/**
 * Refreshes the Blitz cache around Enupal Translate's SyncTranslationsWithDb job.
 *
 * The changed messages are read before the job runs, while Enupal's tables still hold the previous
 * translations. The pages showing them are refreshed once the job has run, and also when it fails:
 * Enupal writes the translation file before queueing the job, so the new text is live either way, and
 * a retry would no longer see the messages the failed attempt already copied to the tables.
 */
final class TranslationRefresh
{
    /**
     * @var array<array-key, string[]|null> Job ID => tags to refresh, or null to refresh everything
     */
    private array $pending = [];

    /**
     * @param Closure(): (string[]|null) $changedTags Tags of the changed messages, or null when unknown
     * @param Closure(string[]): void $refreshTags
     * @param Closure(): void $refreshAll
     */
    public function __construct(
        private readonly Closure $changedTags,
        private readonly Closure $refreshTags,
        private readonly Closure $refreshAll,
    ) {
    }

    public function attach(): void
    {
        Event::on(Queue::class, Queue::EVENT_BEFORE_EXEC, fn(ExecEvent $event) => $this->_beforeRun($event));
        Event::on(Queue::class, Queue::EVENT_AFTER_EXEC, fn(ExecEvent $event) => $this->_afterRun($event));
        Event::on(Queue::class, Queue::EVENT_AFTER_ERROR, fn(ExecEvent $event) => $this->_afterRun($event));
    }

    private function _beforeRun(ExecEvent $event): void
    {
        if (is_object($event->job) && TranslationSync::requiresCacheRefresh($event->job)) {
            $this->pending[(string)$event->id] = ($this->changedTags)();
        }
    }

    private function _afterRun(ExecEvent $event): void
    {
        $id = (string)$event->id;

        if (!array_key_exists($id, $this->pending)) {
            return;
        }

        $tags = $this->pending[$id];
        unset($this->pending[$id]);

        if ($tags === null) {
            ($this->refreshAll)();
        } elseif ($tags !== []) {
            ($this->refreshTags)($tags);
        }
    }
}
