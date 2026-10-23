<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Attempt;
use App\Models\AttemptQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The ONLY way player data reaches the client. Per question: position, type,
 * points, body, image URL and the student payload stored in the snapshot at
 * start (toPlayerPayload(), option order already fixed). Plus the student's
 * saved answers and flags. Never the snapshot, definition, explanation or
 * question IDs. Requires `test` and `questions.answer`.
 *
 * @mixin Attempt
 */
final class PlayerAttemptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $questions = $this->questions;

        return [
            'ulid' => $this->ulid,
            'test' => ['title' => $this->test->title],
            'deadline_at' => $this->deadline_at?->toIso8601String(),
            'server_now' => now()->toIso8601String(),
            'question_count' => $questions->count(),
            'questions' => $questions->map(fn (AttemptQuestion $question): array => [
                'position' => $question->position,
                'type' => (string) $question->snapshot['type'],
                'points' => $question->points,
                'body' => $question->snapshot['body'],
                'image_url' => is_string($question->snapshot['image_path'] ?? null)
                    ? route('student.attempts.images.show', ['attempt' => $this->ulid, 'position' => $question->position])
                    : null,
                'player' => $question->snapshot['player'],
            ])->values()->all(),
            'saved' => $questions->map(fn (AttemptQuestion $question): array => [
                'position' => $question->position,
                'value' => $question->answer?->answer,
                'flagged' => (bool) $question->answer?->flagged,
                'answered' => $question->answer?->answered_at !== null,
            ])->values()->all(),
        ];
    }
}
