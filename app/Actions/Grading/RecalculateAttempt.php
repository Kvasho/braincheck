<?php

declare(strict_types=1);

namespace App\Actions\Grading;

use App\Domain\Grading\AttemptTotals;
use App\Enums\GradingStatus;
use App\Events\AttemptGraded;
use App\Models\Attempt;
use Carbon\CarbonImmutable;

/**
 * Recomputes an attempt's totals and grading status from its answer rows.
 * The caller holds the attempt's row lock inside a transaction.
 *
 * - Some answer still needs manual grading → `pending_manual`, provisional
 *   score, `passed = null`.
 * - Otherwise → `graded` (graded_at kept from the first time it became
 *   graded), `passed = score_percent ≥ pass_percent`.
 * - Becoming `graded` for the first time fires AttemptGraded (after commit).
 */
final class RecalculateAttempt
{
    /**
     * @return array{old_score: string|null, new_score: string, old_passed: bool|null, new_passed: bool|null, was_graded: bool, is_graded: bool}
     */
    public function handle(Attempt $locked): array
    {
        $locked->load(['questions.answer', 'test:id,pass_percent']);

        $wasGraded = $locked->grading_status === GradingStatus::Graded;
        $oldScore = $locked->score;
        $oldPassed = $locked->passed;
        $totals = AttemptTotals::of($locked);
        $passed = $totals->passes($locked->test->pass_percent);

        $locked->forceFill([
            ...$totals->columns(),
            'grading_status' => $totals->pending ? GradingStatus::PendingManual : GradingStatus::Graded,
            'graded_at' => $totals->pending ? null : ($locked->graded_at ?? CarbonImmutable::now()),
            'passed' => $passed,
        ])->save();

        $isGraded = ! $totals->pending;

        if ($isGraded && ! $wasGraded) {
            event(new AttemptGraded($locked));
        }

        return [
            'old_score' => $oldScore,
            'new_score' => $totals->score->toString(),
            'old_passed' => $oldPassed,
            'new_passed' => $passed,
            'was_graded' => $wasGraded,
            'is_graded' => $isGraded,
        ];
    }
}
