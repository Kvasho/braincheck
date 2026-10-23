<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Attempt;
use App\Models\AttemptQuestion;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A standalone snapshot question appended to an attempt (after its existing ones).
 * Prefer AttemptFactory, which snapshots a whole test consistently.
 *
 * @extends Factory<AttemptQuestion>
 */
class AttemptQuestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attempt_id' => Attempt::factory(),
            'question_id' => Question::factory(),
            'position' => fn (array $attributes): int => (int) AttemptQuestion::query()
                ->where('attempt_id', $attributes['attempt_id'])
                ->max('position') + 1,
            'snapshot' => function (array $attributes): array {
                $question = Question::query()->whereKey($attributes['question_id'])->firstOrFail();

                return [
                    'type' => $question->type->value,
                    'body' => $question->body->toArray(),
                    'explanation' => $question->explanation?->toArray(),
                    'definition' => $question->definition->toArray(),
                    'points' => $question->points,
                    'version' => $question->version,
                ];
            },
            'points' => fn (array $attributes): string => Question::query()->whereKey($attributes['question_id'])->firstOrFail()->points,
        ];
    }
}
