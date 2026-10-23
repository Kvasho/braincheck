<?php

declare(strict_types=1);

namespace App\Actions\Grading;

use App\Domain\Questions\Points;
use App\Enums\AttemptStatus;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\AttemptQuestion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A teacher grades an answer that needs manual grading (free text).
 *
 * Locks the attempt, then the answer row; checks the points range
 * (0 … question points) and the grading version the teacher loaded: when
 * someone else graded meanwhile, the second grader gets a conflict.
 * is_correct = true for full points, false for 0, null for partial credit.
 * Then the totals are recomputed; with no pending answers left the attempt
 * becomes graded (AttemptGraded after commit).
 */
final class GradeAnswerManually
{
    public function __construct(private readonly RecalculateAttempt $recalculate) {}

    /**
     * @throws ValidationException
     */
    public function handle(AttemptQuestion $question, Points $points, ?string $feedback, User $grader, string $version): void
    {
        DB::transaction(function () use ($question, $points, $feedback, $grader, $version): void {
            $attempt = Attempt::lockedCopyOf($question->attempt) ?? throw (new ModelNotFoundException)->setModel(Attempt::class);
            /** @var AttemptAnswer $answer */
            $answer = AttemptAnswer::query()->where('attempt_question_id', $question->id)->lockForUpdate()->firstOrFail();
            $max = Points::of($question->points);

            if ($attempt->status === AttemptStatus::InProgress) {
                throw ValidationException::withMessages(['points' => __('This attempt is still in progress.')]);
            }

            if (! $answer->needs_manual_grading) {
                throw ValidationException::withMessages(['points' => __('This answer is graded automatically. Use an override to change it.')]);
            }

            if (! hash_equals($answer->gradingVersion(), $version)) {
                throw ValidationException::withMessages(['version' => __('Someone else graded this answer in the meantime. Reload to see the current grade.')]);
            }

            if ($points->isGreaterThan($max)) {
                throw ValidationException::withMessages(['points' => __('At most :max points.', ['max' => $max->toString()])]);
            }

            $answer->forceFill([
                'awarded_points' => $points->toString(),
                'is_correct' => $points->equals($max) ? true : ($points->isZero() ? false : null),
                'graded_by' => $grader->id,
                'graded_at' => CarbonImmutable::now(),
                'feedback' => $feedback,
            ])->save();

            $this->recalculate->handle($attempt);
        });
    }
}
