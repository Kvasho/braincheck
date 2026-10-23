<?php

declare(strict_types=1);

namespace App\Actions\Grading;

use App\Actions\Audit\RecordAuditEvent;
use App\Domain\Questions\Points;
use App\Enums\AttemptStatus;
use App\Enums\AuditAction;
use App\Enums\GradingStatus;
use App\Events\AttemptRegraded;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\AttemptQuestion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The test owner changes the points of an auto-graded answer, with a
 * mandatory reason. Totals are recomputed; the change is audited
 * (`grading.override`: position, old and new points, reason) and, when the
 * attempt's score or pass result changes, AttemptRegraded fires after commit.
 */
final class OverrideAnswerScore
{
    public function __construct(
        private readonly RecalculateAttempt $recalculate,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(AttemptQuestion $question, Points $points, string $reason, User $actor): void
    {
        DB::transaction(function () use ($question, $points, $reason, $actor): void {
            $attempt = Attempt::lockedCopyOf($question->attempt) ?? throw (new ModelNotFoundException)->setModel(Attempt::class);
            /** @var AttemptAnswer $answer */
            $answer = AttemptAnswer::query()->where('attempt_question_id', $question->id)->lockForUpdate()->firstOrFail();
            $max = Points::of($question->points);

            if ($attempt->status === AttemptStatus::InProgress || $attempt->grading_status === GradingStatus::PendingAuto) {
                throw ValidationException::withMessages(['points' => __('This attempt has not been graded yet.')]);
            }

            if ($answer->needs_manual_grading) {
                throw ValidationException::withMessages(['points' => __('This answer is graded manually. Grade it in the grading queue.')]);
            }

            if ($points->isGreaterThan($max)) {
                throw ValidationException::withMessages(['points' => __('At most :max points.', ['max' => $max->toString()])]);
            }

            $old = $answer->awarded_points;

            $answer->forceFill([
                'awarded_points' => $points->toString(),
                'is_correct' => $points->equals($max) ? true : ($points->isZero() ? false : null),
                'graded_by' => $actor->id,
                'graded_at' => CarbonImmutable::now(),
            ])->save();

            $change = $this->recalculate->handle($attempt);

            $this->recordAuditEvent->handle(AuditAction::GradingOverride, $actor, $attempt, [
                'position' => $question->position,
                'old_points' => $old,
                'new_points' => $points->toString(),
                'reason' => $reason,
            ]);

            if ($change['was_graded'] && ($change['old_score'] !== $change['new_score'] || $change['old_passed'] !== $change['new_passed'])) {
                event(new AttemptRegraded($attempt, $change['old_score'], $change['new_score'], $change['old_passed'], $change['new_passed']));
            }
        });
    }
}
