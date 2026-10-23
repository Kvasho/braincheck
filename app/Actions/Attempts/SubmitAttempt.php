<?php

declare(strict_types=1);

namespace App\Actions\Attempts;

use App\Actions\Grading\GradeAttempt;
use App\Enums\AttemptStatus;
use App\Models\Attempt;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * The student submits. Idempotent: an attempt that is already finished is
 * returned as it is. Past deadline + grace, the attempt is finished as
 * timed out instead (submitted at its deadline). Grading (GradeAttempt) runs
 * synchronously right after the commit.
 */
final class SubmitAttempt
{
    public function __construct(
        private readonly FinishExpiredAttempt $finishExpired,
        private readonly GradeAttempt $gradeAttempt,
    ) {}

    public function handle(Attempt $attempt): Attempt
    {
        if ($this->finishExpired->handle($attempt)) {
            return $attempt;
        }

        DB::transaction(function () use ($attempt): void {
            $locked = Attempt::lockedCopyOf($attempt) ?? throw (new ModelNotFoundException)->setModel(Attempt::class);

            if ($locked->status !== AttemptStatus::InProgress) {
                return;
            }

            $locked->forceFill([
                'status' => AttemptStatus::Submitted,
                'submitted_at' => CarbonImmutable::now(),
            ])->save();
        });

        $this->gradeAttempt->handle($attempt);

        return $attempt->refresh();
    }
}
