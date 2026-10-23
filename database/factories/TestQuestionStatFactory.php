<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Question;
use App\Models\Test;
use App\Models\TestQuestionStat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestQuestionStat>
 */
class TestQuestionStatFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'test_id' => Test::factory(),
            'question_id' => Question::factory(),
            'type' => 'single_choice',
            'excerpt' => fake()->sentence(),
            'responses' => 20,
            'difficulty' => '0.6500',
            'discrimination' => '0.4000',
            'average_points' => '0.65',
            'manual_grades' => 0,
            'needs_review' => false,
            'distractors' => null,
            'computed_at' => now(),
        ];
    }

    /**
     * A question with low discrimination (D < 0.2): flagged for review.
     */
    public function needsReview(): static
    {
        return $this->state(fn (): array => ['discrimination' => '0.0500', 'needs_review' => true]);
    }
}
