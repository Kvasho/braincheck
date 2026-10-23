<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\QuestionType;
use App\Models\Group;
use App\Models\Question;
use App\Models\Test;
use App\Models\User;

/**
 * Students, assigned published tests and answers for the exam engine tests.
 */
final class ExamFixtures
{
    /**
     * A published test with $types questions (in order, 1 point each),
     * assigned to a group $student is a member of.
     *
     * @param  list<QuestionType>  $types
     * @param  array<string, mixed>  $attributes
     */
    public static function assignedTest(User $student, array $types = [QuestionType::SingleChoice, QuestionType::SingleChoice], array $attributes = []): Test
    {
        $teacher = User::factory()->teacher()->create();
        $test = Test::factory()->for($teacher, 'owner')->published()->create([
            'shuffle_questions' => false,
            'shuffle_options' => false,
            'time_limit_minutes' => 30,
            'max_attempts' => 2,
            'opens_at' => null,
            'closes_at' => null,
            ...$attributes,
        ]);

        foreach (array_values($types) as $index => $type) {
            $question = Question::factory()->for($teacher, 'owner')->ofType($type)->create(['points' => '1.00']);
            $test->questions()->attach($question, ['position' => $index + 1]);
        }

        $group = Group::factory()->for($teacher, 'owner')->create();
        $group->members()->attach($student, ['joined_at' => now()]);
        $group->tests()->attach($test, ['assigned_at' => now()]);

        return $test;
    }

    public static function student(): User
    {
        return User::factory()->student()->create();
    }

    /**
     * A well-formed answer built only from what the client sees (the player
     * payload), like a real student would send it.
     *
     * @param  array<string, mixed>  $player
     * @return array<string, mixed>
     */
    public static function answerFromPlayer(QuestionType $type, array $player): array
    {
        /** @var list<array{id: string}> $options */
        $options = $player['options'] ?? [];
        /** @var list<array{id: string}> $items */
        $items = $player['items'] ?? [];
        /** @var list<array{id: string}> $left */
        $left = $player['left'] ?? [];
        /** @var list<array{id: string}> $right */
        $right = $player['right'] ?? [];

        return match ($type) {
            QuestionType::SingleChoice => ['selected' => $options[0]['id']],
            QuestionType::MultipleChoice => ['selected' => [$options[0]['id']]],
            QuestionType::TrueFalse => ['value' => true],
            QuestionType::Text => ['text' => 'My answer'],
            QuestionType::Numeric => ['value' => '42'],
            QuestionType::Ordering => ['order' => array_column(array_reverse($items), 'id')],
            QuestionType::Matching => ['pairs' => [['left' => $left[0]['id'], 'right' => $right[0]['id']]]],
        };
    }
}
