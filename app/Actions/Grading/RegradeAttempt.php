<?php

declare(strict_types=1);

namespace App\Actions\Grading;

use App\Domain\Grading\SnapshotGrader;
use App\Enums\AttemptStatus;
use App\Enums\GradingStatus;
use App\Events\AttemptRegraded;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use Illuminate\Support\Facades\DB;

/**
 * Grades selected questions of a finished attempt again from their (fixed)
 * snapshots and recomputes the totals. Auto-graded results replace earlier
 * ones, overrides included; answers graded manually by a teacher keep their
 * grade. Fires AttemptRegraded when the score or the pass result changes.
 * Used by RegradeTestQuestion; GradeAttempt itself never regrades.
 */
final class RegradeAttempt
{
    public function __construct(
        private readonly SnapshotGrader $grader,
        private readonly RecalculateAttempt $recalculate,
    ) {}

    /**
     * @param  list<int>  $positions
     */
    public function handle(Attempt $attempt, array $positions): void
    {
        DB::transaction(function () use ($attempt, $positions): void {
            $locked = Attempt::lockedCopyOf($attempt);

            if ($locked === null
                || $locked->status === AttemptStatus::InProgress
                || $locked->grading_status === GradingStatus::PendingAuto) {
                return;
            }

            $locked->load('questions.answer');

            foreach ($locked->questions->whereIn('position', $positions) as $question) {
                /** @var AttemptQuestion $question */
                $answer = $question->answer;

                if ($answer === null || ($answer->needs_manual_grading && $answer->graded_at !== null)) {
                    continue;
                }

                $result = $this->grader->grade($question, $answer->answer);

                $answer->forceFill([
                    'is_correct' => $result->isCorrect,
                    'awarded_points' => $result->awardedPoints->toString(),
                    'needs_manual_grading' => $result->needsManualGrading,
                    'graded_by' => null,
                    'graded_at' => null,
                ])->save();
            }

            $change = $this->recalculate->handle($locked);

            if ($change['old_score'] !== $change['new_score'] || $change['old_passed'] !== $change['new_passed']) {
                event(new AttemptRegraded($locked, $change['old_score'], $change['new_score'], $change['old_passed'], $change['new_passed']));
            }
        });
    }
}
