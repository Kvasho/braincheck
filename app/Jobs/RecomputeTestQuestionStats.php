<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Analytics\AnalyticsCache;
use App\Domain\Analytics\QuestionStatsCalculator;
use App\Models\Test;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Recomputes a test's per-question stats. Unique per test while queued, so a
 * burst of gradings (e.g. a class submitting at the deadline) results in one
 * recompute ("debounced"). Triggered by AttemptGraded / AttemptRegraded and
 * by the analytics page's "recompute" button.
 */
final class RecomputeTestQuestionStats implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 300;

    public function __construct(public readonly int $testId) {}

    public function uniqueId(): string
    {
        return (string) $this->testId;
    }

    public function handle(QuestionStatsCalculator $calculator): void
    {
        $test = Test::query()->withTrashed()->find($this->testId);

        if ($test === null) {
            return;
        }

        $calculator->recompute($test);
        AnalyticsCache::invalidate($test->id);
    }
}
