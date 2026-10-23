<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Question;
use App\Models\Test;
use App\Support\AppTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Everything the owner's builder edits. Times come twice: ISO UTC (for
 * display and relative summaries) and as display-timezone input values
 * (Asia/Tbilisi) for the pickers. No question definitions (no answers).
 *
 * @mixin Test
 */
final class TestBuilderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category?->ulid,
            'status' => $this->status->value,
            'locked' => $this->isLocked(),
            'published_at' => $this->published_at?->toIso8601String(),
            'time_limit_minutes' => $this->time_limit_minutes,
            'max_attempts' => $this->max_attempts,
            'pass_percent' => $this->pass_percent,
            'shuffle_questions' => $this->shuffle_questions,
            'shuffle_options' => $this->shuffle_options,
            'selection_mode' => $this->selection_mode->value,
            'random_question_count' => $this->random_question_count,
            'opens_at' => $this->opens_at?->toIso8601String(),
            'closes_at' => $this->closes_at?->toIso8601String(),
            'opens_at_local' => AppTime::toLocalInput($this->opens_at),
            'closes_at_local' => AppTime::toLocalInput($this->closes_at),
            'results_visibility' => $this->results_visibility->value,
            'show_correct_answers' => $this->show_correct_answers,
            'issues_certificate' => $this->issues_certificate,
            'questions' => $this->questions()->withTrashed()->get()->map(fn (Question $question): array => self::questionRow($question))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function questionRow(Question $question): array
    {
        $pivot = $question->relationLoaded('pivot') ? $question->getRelation('pivot') : null;

        return [
            'ulid' => $question->ulid,
            'excerpt' => Str::limit(preg_replace('/\s+/u', ' ', $question->body_text) ?? '', QuestionResource::EXCERPT_LENGTH),
            'type' => $question->type->value,
            'difficulty' => $question->difficulty->value,
            'points' => $question->points,
            'points_override' => is_object($pivot) && isset($pivot->points_override) ? (string) $pivot->points_override : null,
            'archived' => $question->trashed(),
        ];
    }
}
