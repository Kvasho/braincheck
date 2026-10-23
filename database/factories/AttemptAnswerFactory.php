<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AttemptAnswer;
use App\Models\AttemptQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The answer row for a standalone attempt question (AttemptFactory already
 * creates one answer per snapshot question).
 *
 * @extends Factory<AttemptAnswer>
 */
class AttemptAnswerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attempt_question_id' => AttemptQuestion::factory(),
            'answer' => null,
            'answered_at' => null,
            'is_correct' => null,
            'awarded_points' => null,
            'needs_manual_grading' => false,
            'graded_by' => null,
            'graded_at' => null,
            'feedback' => null,
        ];
    }
}
