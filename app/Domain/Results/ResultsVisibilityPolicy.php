<?php

declare(strict_types=1);

namespace App\Domain\Results;

use App\Enums\AttemptStatus;
use App\Enums\GradingStatus;
use App\Enums\ResultsVisibility;
use App\Models\Attempt;
use App\Models\Test;
use Carbon\CarbonImmutable;

/**
 * The ONE place that decides what a student may see of their own attempt.
 *
 * Score (and per-question points) — only for a finished, fully graded,
 * real attempt, and then by the test's `results_visibility`:
 * - immediately: once graded;
 * - after_close: once graded and the test's closes_at has passed (a test
 *   without a closing time never reaches that point);
 * - manual: once graded and the owner released the results;
 * - never: never (the student only sees "submitted").
 *
 * Correct answers and explanations: only when the score is visible AND
 * `show_correct_answers` is on.
 */
final class ResultsVisibilityPolicy
{
    public function canSeeScore(Attempt $attempt, ?CarbonImmutable $now = null): bool
    {
        if ($attempt->is_preview
            || $attempt->status === AttemptStatus::InProgress
            || $attempt->grading_status !== GradingStatus::Graded) {
            return false;
        }

        $test = $attempt->test;
        $now ??= CarbonImmutable::now();

        return match ($test->results_visibility) {
            ResultsVisibility::Immediately => true,
            ResultsVisibility::AfterClose => $test->closes_at !== null && $now->greaterThanOrEqualTo($test->closes_at),
            ResultsVisibility::Manual => $test->results_released_at !== null,
            ResultsVisibility::Never => false,
        };
    }

    public function canSeeCorrectAnswers(Attempt $attempt, ?CarbonImmutable $now = null): bool
    {
        return $this->canSeeScore($attempt, $now) && $attempt->test->show_correct_answers;
    }

    /**
     * Whether the student waits for a teacher (free-text answers still to grade).
     */
    public function awaitsReview(Attempt $attempt): bool
    {
        return $attempt->status !== AttemptStatus::InProgress
            && $attempt->grading_status === GradingStatus::PendingManual;
    }

    /**
     * Whether the results of a test can become visible later (for the
     * student's message: "results will appear …" vs. "not shown").
     */
    public function mayShowLater(Test $test): bool
    {
        return $test->results_visibility !== ResultsVisibility::Never;
    }
}
