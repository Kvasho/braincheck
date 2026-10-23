<?php

declare(strict_types=1);

namespace App\Actions\Tests;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Enums\ResultsVisibility;
use App\Models\Test;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The owner releases the results of a `manual` test: students see their
 * (graded) scores from now on. One-way: released results are never
 * un-released (the "reveal only more" rule of Stage 5). Idempotent; audited.
 */
final class ReleaseResults
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    /**
     * @throws ValidationException
     */
    public function handle(Test $test, User $actor): Test
    {
        return DB::transaction(function () use ($test, $actor): Test {
            $locked = Test::query()->withTrashed()->whereKey($test->id)->lockForUpdate()->firstOrFail();

            if ($locked->results_visibility !== ResultsVisibility::Manual) {
                throw ValidationException::withMessages(['test' => __('Only tests with manually released results can be released.')]);
            }

            if ($locked->results_released_at === null) {
                $locked->forceFill(['results_released_at' => CarbonImmutable::now()])->save();
                $this->recordAuditEvent->handle(AuditAction::TestResultsReleased, $actor, $locked, ['title' => $locked->title]);
            }

            return $locked;
        });
    }
}
