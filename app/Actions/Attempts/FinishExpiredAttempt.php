<?php

declare(strict_types=1);

namespace App\Actions\Attempts;

use App\Actions\Grading\GradeAttempt;
use App\Domain\Attempts\AttemptTiming;
use App\Enums\AttemptStatus;
use App\Models\Attempt;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Finishes a real in-progress attempt whose deadline (plus grace) has passed:
 * status `timed_out`, submitted at the deadline, then grading (GradeAttempt). Used by the
 * `attempts:expire` command and lazily by the player, autosave and submit.
 * Idempotent: anything else is left untouched.
 */
final class FinishExpiredAttempt
{
    public function __construct(private readonly GradeAttempt $gradeAttempt) {}

    /**
     * @return bool whether this call finished the attempt
     */
    public function handle(Attempt $attempt, ?CarbonImmutable $now = null): bool
    {
        $finished = DB::transaction(function () use ($attempt, $now): bool {
            $locked = Attempt::lockedCopyOf($attempt);

            if ($locked === null || $locked->is_preview || ! AttemptTiming::isExpired($locked, $now)) {
                return false;
            }

            $locked->forceFill([
                'status' => AttemptStatus::TimedOut,
                'submitted_at' => $locked->deadline_at,
            ])->save();

            return true;
        });

        if ($finished) {
            // Graded synchronously, after the commit above.
            $this->gradeAttempt->handle($attempt);
        }

        return $finished;
    }
}
