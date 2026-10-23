<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AttemptStatus;
use App\Enums\GradingStatus;
use App\Enums\QuestionType;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\AttemptQuestion;
use App\Models\Question;
use App\Models\Test;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Attempts with consistent history: every test question is snapshotted into
 * attempt_questions (type, body, explanation, definition, points, version) and
 * gets exactly one answer row. States keep status, deadlines and scores
 * consistent with the attempts CHECK constraints.
 *
 * @extends Factory<Attempt>
 */
class AttemptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'test_id' => Test::factory()->published()->withQuestions(3),
            'user_id' => User::factory()->student(),
            'attempt_number' => 1,
            'status' => AttemptStatus::InProgress,
            'grading_status' => GradingStatus::PendingAuto,
            'started_at' => now()->subMinutes(20),
            'deadline_at' => null,
            'submitted_at' => null,
            'graded_at' => null,
            'score' => null,
            'max_score' => null,
            'score_percent' => null,
            'passed' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => 'Mozilla/5.0 (BrainCheck factory)',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Attempt $attempt): void {
            self::snapshotQuestions($attempt);
        });
    }

    public function inProgress(): static
    {
        return $this->state(fn (): array => [
            'status' => AttemptStatus::InProgress,
            'submitted_at' => null,
        ]);
    }

    /**
     * Submitted, answered, not graded yet.
     */
    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'status' => AttemptStatus::Submitted,
            'grading_status' => GradingStatus::PendingAuto,
            'submitted_at' => now()->subMinutes(5),
        ])->afterCreating(function (Attempt $attempt): void {
            self::answer($attempt, correctCount: intdiv($attempt->questions()->count(), 2), grade: false);
        });
    }

    /**
     * Finished by the server when the time limit ran out.
     */
    public function timedOut(int $minutes = 30): static
    {
        return $this->state(function (): array {
            $started = now()->subHours(2);

            return [
                'status' => AttemptStatus::TimedOut,
                'started_at' => $started,
            ];
        })->state(fn (array $attributes): array => [
            'deadline_at' => $attributes['started_at']->copy()->addMinutes($minutes),
            'submitted_at' => $attributes['started_at']->copy()->addMinutes($minutes),
        ]);
    }

    /**
     * Submitted and graded: all answers correct when $passed, none otherwise.
     */
    public function graded(bool $passed = true): static
    {
        return $this->gradedState()->afterCreating(function (Attempt $attempt) use ($passed): void {
            self::answer($attempt, correctCount: $passed ? PHP_INT_MAX : 0, grade: true);
        });
    }

    /**
     * Submitted and graded with exactly $correctCount correct answers (varied scores).
     */
    public function gradedWith(int $correctCount): static
    {
        return $this->gradedState()->afterCreating(function (Attempt $attempt) use ($correctCount): void {
            self::answer($attempt, correctCount: $correctCount, grade: true);
        });
    }

    private function gradedState(): static
    {
        return $this->state(fn (): array => [
            'status' => AttemptStatus::Submitted,
            'grading_status' => GradingStatus::Graded,
            'submitted_at' => now()->subMinutes(5),
            'graded_at' => now()->subMinutes(4),
        ]);
    }

    /**
     * Freeze every question of the test into the attempt, with an empty answer row.
     */
    private static function snapshotQuestions(Attempt $attempt): void
    {
        $test = $attempt->test()->firstOrFail();

        if ($attempt->deadline_at === null && $test->time_limit_minutes !== null && $attempt->status === AttemptStatus::InProgress) {
            $attempt->forceFill(['deadline_at' => $attempt->started_at->addMinutes($test->time_limit_minutes)])->save();
        }

        /** @var list<Question> $questions */
        $questions = $test->questions()->get()->all();

        foreach ($questions as $index => $question) {
            /** @var object{position: int, points_override: string|null} $pivot */
            $pivot = $question->getRelation('pivot');
            $points = $pivot->points_override ?? $question->points;

            $attemptQuestion = (new AttemptQuestion)->forceFill([
                'attempt_id' => $attempt->id,
                'question_id' => $question->id,
                'position' => $index + 1,
                'snapshot' => [
                    'type' => $question->type->value,
                    'body' => $question->body->toArray(),
                    'explanation' => $question->explanation?->toArray(),
                    'definition' => $question->definition->toArray(),
                    'points' => $points,
                    'version' => $question->version,
                ],
                'points' => $points,
            ]);
            $attemptQuestion->save();

            (new AttemptAnswer)->forceFill([
                'attempt_question_id' => $attemptQuestion->id,
                'needs_manual_grading' => ! $question->type->isAutoGradable(),
            ])->save();
        }
    }

    /**
     * Fill in answers: the first $correctCount questions correctly, the rest wrongly.
     * When $grade, also mark answers and compute the attempt's score.
     */
    private static function answer(Attempt $attempt, int $correctCount, bool $grade): void
    {
        $attemptQuestions = $attempt->questions()->with('answer')->get();
        $answeredAt = $attempt->submitted_at ?? now();
        $score = 0.0;
        $maxScore = 0.0;

        foreach ($attemptQuestions->values() as $index => $attemptQuestion) {
            $snapshot = $attemptQuestion->snapshot;
            $type = QuestionType::from((string) $snapshot['type']);
            /** @var array<string, mixed> $definition */
            $definition = $snapshot['definition'];
            $correct = $index < $correctCount;
            $points = (float) $attemptQuestion->points;
            $maxScore += $points;

            $values = [
                'answer' => $correct ? self::correctAnswer($type, $definition) : self::wrongAnswer($type, $definition),
                'answered_at' => $answeredAt->subMinute(),
            ];

            if ($grade) {
                $values += [
                    'is_correct' => $correct,
                    'awarded_points' => $correct ? $points : 0,
                    'needs_manual_grading' => false,
                    'graded_at' => $attempt->graded_at ?? now(),
                ];
                $score += $correct ? $points : 0;
            }

            $answer = $attemptQuestion->answer ?? (new AttemptAnswer)->forceFill(['attempt_question_id' => $attemptQuestion->id]);
            $answer->forceFill($values)->save();
        }

        if (! $grade) {
            return;
        }

        $percent = $maxScore > 0 ? round($score / $maxScore * 100, 2) : 0.0;
        $test = $attempt->test()->firstOrFail();

        $attempt->forceFill([
            'score' => $score,
            'max_score' => $maxScore,
            'score_percent' => $percent,
            'passed' => $percent >= $test->pass_percent,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    public static function correctAnswer(QuestionType $type, array $definition): array
    {
        return match ($type) {
            QuestionType::SingleChoice, QuestionType::MultipleChoice => ['selected' => $definition['correct']],
            QuestionType::TrueFalse, QuestionType::Numeric => ['value' => $definition['correct']],
            QuestionType::Text => ['text' => $definition['reference_answer'] ?? 'A complete, well-argued answer.'],
            QuestionType::Ordering => ['order' => $definition['correct_order']],
            QuestionType::Matching => ['pairs' => $definition['pairs']],
        };
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    public static function wrongAnswer(QuestionType $type, array $definition): array
    {
        /** @var list<array{id: string, text: string}> $options */
        $options = $definition['options'] ?? [];
        $correct = $definition['correct'] ?? null;
        $wrongOptions = array_values(array_filter(
            array_column($options, 'id'),
            fn (string $id): bool => is_array($correct) ? ! in_array($id, $correct, true) : $id !== $correct,
        ));

        return match ($type) {
            QuestionType::SingleChoice => ['selected' => $wrongOptions[0] ?? null],
            QuestionType::MultipleChoice => ['selected' => array_slice($wrongOptions, 0, 1)],
            QuestionType::TrueFalse => ['value' => ! $correct],
            QuestionType::Text => ['text' => 'I am not sure.'],
            // Decimal strings, never floats (see NumericDefinition).
            QuestionType::Numeric => ['value' => BigDecimal::of((string) $correct)->plus((string) $definition['tolerance'])->plus(10)->toString()],
            QuestionType::Ordering => ['order' => array_reverse((array) $definition['correct_order'])],
            QuestionType::Matching => ['pairs' => self::shiftedPairs((array) $definition['pairs'])],
        };
    }

    /**
     * Pair every left item with the next pair's right item (all wrong).
     *
     * @param  array<array-key, mixed>  $pairs
     * @return list<array{left: mixed, right: mixed}>
     */
    private static function shiftedPairs(array $pairs): array
    {
        $pairs = array_values($pairs);
        $count = count($pairs);
        $shifted = [];

        foreach ($pairs as $index => $pair) {
            $next = $pairs[($index + 1) % $count];
            $shifted[] = [
                'left' => is_array($pair) ? ($pair['left'] ?? null) : null,
                'right' => is_array($next) ? ($next['right'] ?? null) : null,
            ];
        }

        return $shifted;
    }
}
