<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Analytics\AnalyticsCache;
use App\Events\AttemptGraded;
use App\Events\AttemptRegraded;
use App\Jobs\RecomputeTestQuestionStats;

/**
 * Keeps test analytics fresh: a graded or regraded real attempt invalidates
 * the test's cached numbers and queues a (debounced) stats recompute.
 */
final class RefreshTestAnalytics
{
    public function handle(AttemptGraded|AttemptRegraded $event): void
    {
        if ($event->attempt->is_preview) {
            return;
        }

        AnalyticsCache::invalidate($event->attempt->test_id);
        dispatch(new RecomputeTestQuestionStats($event->attempt->test_id));
    }
}
