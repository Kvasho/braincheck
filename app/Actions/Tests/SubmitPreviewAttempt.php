<?php

declare(strict_types=1);

namespace App\Actions\Tests;

use App\Domain\Questions\Definitions\Definitions;
use App\Domain\Questions\Points;
use App\Domain\Questions\Types\QuestionTypeRegistry;
use App\Enums\AttemptStatus;
use App\Enums\GradingStatus;
use App\Enums\QuestionType;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

/**
 * Grades a preview attempt from its snapshots (simplified until the Stage 7
 * exam engine: answers are graded in one go and not stored per question) and
 * returns the results with the full review for the teacher.
 */
final class SubmitPreviewAttempt
{
    public function __construct(private readonly QuestionTypeRegistry $registry) {}

    /**
     * @param  array<array-key, array<array-key, mixed>|null>  $answers  attempt question position => answer
     * @return array{score: string, max_score: string, percent: string, passed: bool, questions: list<array<string, mixed>>}
     */
    public function handle(Attempt $attempt, array $answers): array
    {
        return DB::transaction(function () use ($attempt, $answers): array {
            $attempt->loadMissing(['questions', 'test']);
            $score = Points::zero();
            $max = Points::zero();
            $results = [];
            $needsManual = false;

            foreach ($attempt->questions as $row) {
                /** @var AttemptQuestion $row */
                $snapshot = $row->snapshot;
                $type = QuestionType::from((string) $snapshot['type']);
                $definition = Definitions::fromArray($type, (array) $snapshot['definition']);
                $handler = $this->registry->for($type);
                $answer = $answers[$row->position] ?? null;
                $points = Points::of($row->points);

                $grade = $handler->grade($definition, $answer, $points);
                $score = $score->plus($grade->awardedPoints);
                $max = $max->plus($points);
                $needsManual = $needsManual || $grade->needsManualGrading;

                $results[] = [
                    'position' => $row->position,
                    'type' => $type->value,
                    'body' => $snapshot['body'],
                    'points' => $points->toString(),
                    'result' => $grade->toArray(),
                    'review' => $handler->toReviewPayload($definition, $answer, revealCorrect: true),
                    'explanation' => $snapshot['explanation'],
                ];
            }

            $percent = $max->isZero()
                ? BigDecimal::zero()->toScale(2)
                : $score->value->multipliedBy(100)->dividedBy($max->value, 2, RoundingMode::HalfUp);
            $passed = ! $needsManual && $percent->isGreaterThanOrEqualTo($attempt->test->pass_percent);
            $percent = $percent->toString();

            $attempt->forceFill([
                'status' => AttemptStatus::Submitted,
                'grading_status' => $needsManual ? GradingStatus::PendingManual : GradingStatus::Graded,
                'submitted_at' => now(),
                'graded_at' => $needsManual ? null : now(),
                'score' => $score->toString(),
                'max_score' => $max->toString(),
                'score_percent' => $percent,
                'passed' => $needsManual ? null : $passed,
            ])->save();

            return [
                'score' => $score->toString(),
                'max_score' => $max->toString(),
                'percent' => $percent,
                'passed' => $passed,
                'questions' => $results,
            ];
        });
    }
}
