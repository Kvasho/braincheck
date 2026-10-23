<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Grading\SnapshotGrader;
use App\Domain\Questions\Definitions\TextDefinition;
use App\Domain\Questions\Types\QuestionTypeRegistry;
use App\Models\AttemptQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One answer on the grading screen (test owner only), from the snapshot:
 * question body, reference answer (text questions), the student's text as a
 * plain string (never HTML; the UI renders it as text), or the review with
 * the correct answer for auto-graded types. Requires `answer` and
 * `attempt`; the student label comes from the controller.
 *
 * @mixin AttemptQuestion
 */
final class GradingAnswerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $answer = $this->answer;
        $definition = SnapshotGrader::definition($this->resource);
        $type = SnapshotGrader::type($this->resource);
        $value = $answer?->answer;

        return [
            'attempt' => $this->attempt->ulid,
            'position' => $this->position,
            'type' => $type->value,
            'points' => $this->points,
            'body' => $this->snapshot['body'],
            'manual' => (bool) $answer?->needs_manual_grading,
            'reference_answer' => $definition instanceof TextDefinition ? $definition->referenceAnswer : null,
            'text' => is_string($value['text'] ?? null) ? $value['text'] : null,
            'review' => $definition instanceof TextDefinition
                ? null
                : app(QuestionTypeRegistry::class)->for($type)->toReviewPayload($definition, $value, revealCorrect: true),
            'awarded_points' => $answer?->awarded_points,
            'graded' => $answer?->graded_at !== null,
            'feedback' => $answer?->feedback,
            'version' => $answer?->gradingVersion(),
        ];
    }
}
