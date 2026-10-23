<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QuestionSelectionMode;
use App\Enums\ResultsVisibility;
use App\Enums\TestStatus;
use App\Models\Question;
use App\Models\Test;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Tests owned by a Teacher. Default: an untimed draft with unlimited attempts.
 *
 * @extends Factory<Test>
 */
class TestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory()->teacher(),
            'category_id' => null,
            'title' => ucfirst(fake()->word().' '.fake()->word().' '.fake()->word()),
            'description' => fake()->optional()->paragraph(),
            'status' => TestStatus::Draft,
            'time_limit_minutes' => null,
            'max_attempts' => null,
            'pass_percent' => 60,
            'shuffle_questions' => fake()->boolean(),
            'shuffle_options' => fake()->boolean(),
            'selection_mode' => QuestionSelectionMode::Fixed,
            'random_question_count' => null,
            'opens_at' => null,
            'closes_at' => null,
            'results_visibility' => ResultsVisibility::Immediately,
            'show_correct_answers' => false,
            'published_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => TestStatus::Draft, 'published_at' => null]);
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => TestStatus::Published, 'published_at' => now()->subDay()]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => TestStatus::Archived, 'published_at' => now()->subMonth()]);
    }

    public function timed(int $minutes = 30): static
    {
        return $this->state(fn (): array => ['time_limit_minutes' => $minutes]);
    }

    public function withAttemptLimit(int $attempts = 1): static
    {
        return $this->state(fn (): array => ['max_attempts' => $attempts]);
    }

    public function randomPool(int $count = 5): static
    {
        return $this->state(fn (): array => [
            'selection_mode' => QuestionSelectionMode::RandomPool,
            'random_question_count' => $count,
        ]);
    }

    public function openWindow(): static
    {
        return $this->state(fn (): array => ['opens_at' => now()->subDay(), 'closes_at' => now()->addWeek()]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => ['opens_at' => now()->subMonth(), 'closes_at' => now()->subDay()]);
    }

    public function notYetOpen(): static
    {
        return $this->state(fn (): array => ['opens_at' => now()->addDay(), 'closes_at' => now()->addMonth()]);
    }

    /**
     * Attach $count new questions owned by the test's owner, in positions 1..$count.
     */
    public function withQuestions(int $count = 3): static
    {
        return $this->afterCreating(function (Test $test) use ($count): void {
            $questions = Question::factory()->count($count)->create(['owner_id' => $test->owner_id]);

            foreach ($questions->values() as $index => $question) {
                $test->questions()->attach($question->id, ['position' => $index + 1, 'points_override' => null]);
            }
        });
    }
}
