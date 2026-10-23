<?php

declare(strict_types=1);

namespace App\Actions\Grading;

use App\Domain\Grading\SnapshotGrader;
use App\Enums\AttemptStatus;
use App\Enums\GradingStatus;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\AttemptQuestion;
use Illuminate\Support\Facades\DB;

/**
 * Auto-grades a finished attempt (submitted or timed out) from its
 * snapshots: every answer gets is_correct, awarded_points and
 * needs_manual_grading from the snapshot type's handler; then the totals
 * and status are recomputed (RecalculateAttempt). In one transaction with
 * the attempt row locked.
 *
 * Idempotent: only `pending_auto` attempts are graded. An attempt that is
 * already graded (or waiting for manual grading) is left alone; changing
 * grades afterwards goes through GradeAnswerManually, OverrideAnswerScore or
 * RegradeAttempt.
 *
 * Runs synchronously on submit / expiry: attempts are small (≤ 200
 * questions, pure computation). Should it ever become heavy, wrap it in a
 * queued job that implements ShouldBeUnique per attempt.
 */
final class GradeAttempt
{
    public function __construct(
        private readonly SnapshotGrader $grader,
        private readonly RecalculateAttempt $recalculate,
    ) {}

    public function handle(Attempt $attempt): void
    {
        DB::transaction(function () use ($attempt): void {
            $locked = Attempt::lockedCopyOf($attempt);

            if ($locked === null
                || $locked->status === AttemptStatus::InProgress
                || $locked->grading_status !== GradingStatus::PendingAuto) {
                return;
            }

            $locked->load('questions.answer');

            foreach ($locked->questions as $question) {
                /** @var AttemptQuestion $question */
                $answer = $question->answer ?? $this->emptyAnswer($question);
                $result = $this->grader->grade($question, $answer->answer);

                $answer->forceFill([
                    'is_correct' => $result->isCorrect,
                    'awarded_points' => $result->awardedPoints->toString(),
                    'needs_manual_grading' => $result->needsManualGrading,
                ])->save();

                $question->setRelation('answer', $answer);
            }

            $this->recalculate->handle($locked);
        });

        $attempt->refresh();
    }

    private function emptyAnswer(AttemptQuestion $question): AttemptAnswer
    {
        $answer = new AttemptAnswer;
        $answer->attemptQuestion()->associate($question);

        return $answer;
    }
}
