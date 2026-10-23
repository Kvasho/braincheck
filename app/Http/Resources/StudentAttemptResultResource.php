<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Grading\SnapshotGrader;
use App\Domain\Questions\Types\QuestionTypeRegistry;
use App\Domain\Results\ResultsVisibilityPolicy;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A student's result page, cut by ResultsVisibilityPolicy:
 * - always: the test, status and times;
 * - with a visible score: totals and per question the points, the review of
 *   the student's answer and the teacher's feedback (plain text);
 * - with visible correct answers too: the review reveals the correct
 *   answer, and the explanation is included.
 * Requires `test` and `questions.answer`.
 *
 * @mixin Attempt
 */
final class StudentAttemptResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $policy = app(ResultsVisibilityPolicy::class);
        $scoreVisible = $policy->canSeeScore($this->resource);
        $correctVisible = $policy->canSeeCorrectAnswers($this->resource);
        $registry = app(QuestionTypeRegistry::class);

        return [
            'ulid' => $this->ulid,
            'test' => ['ulid' => $this->test->ulid, 'title' => $this->test->title],
            'status' => $this->status->value,
            'started_at' => $this->started_at->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'duration_seconds' => $this->submitted_at === null ? null : (int) $this->started_at->diffInSeconds($this->submitted_at, true),
            'visibility' => [
                'score' => $scoreVisible,
                'correct_answers' => $correctVisible,
                'awaiting_review' => $policy->awaitsReview($this->resource),
                'later' => ! $scoreVisible && $policy->mayShowLater($this->test),
            ],
            'result' => $scoreVisible ? [
                'score' => $this->score,
                'max_score' => $this->max_score,
                'percent' => $this->score_percent,
                'passed' => $this->passed,
            ] : null,
            'questions' => $scoreVisible
                ? $this->questions->map(fn (AttemptQuestion $question): array => [
                    'position' => $question->position,
                    'type' => (string) $question->snapshot['type'],
                    'body' => $question->snapshot['body'],
                    'points' => $question->points,
                    'awarded_points' => $question->answer?->awarded_points,
                    'review' => $registry->for(SnapshotGrader::type($question))
                        ->toReviewPayload(SnapshotGrader::definition($question), $question->answer?->answer, $correctVisible),
                    'feedback' => $question->answer?->feedback,
                    // Only sent with the correct answers (not even as a null key otherwise).
                    ...($correctVisible ? ['explanation' => $question->snapshot['explanation']] : []),
                ])->values()->all()
                : [],
        ];
    }
}
