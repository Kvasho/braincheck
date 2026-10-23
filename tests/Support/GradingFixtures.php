<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Attempts\StartAttempt;
use App\Actions\Attempts\SubmitAttempt;
use App\Domain\Attempts\AttemptClient;
use App\Domain\Questions\Definitions\QuestionDefinition;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Group;
use App\Models\Question;
use App\Models\Test;
use App\Models\User;

/**
 * Finished attempts with explicit definitions and answers, graded through
 * the real path (StartAttempt → stored answers → SubmitAttempt → GradeAttempt).
 */
final class GradingFixtures
{
    /**
     * A published test owned by $teacher with the given questions (in order), assigned to a group of $student.
     *
     * @param  list<array{0: QuestionDefinition, 1: string}>  $questions  definition, points
     * @param  array<string, mixed>  $attributes
     */
    public static function test(User $teacher, User $student, array $questions, array $attributes = []): Test
    {
        $test = Test::factory()->for($teacher, 'owner')->published()->create([
            'shuffle_questions' => false,
            'shuffle_options' => false,
            'time_limit_minutes' => null,
            'max_attempts' => null,
            'pass_percent' => 50,
            ...$attributes,
        ]);

        foreach ($questions as $index => [$definition, $points]) {
            $question = Question::factory()->for($teacher, 'owner')->create([
                'type' => $definition->type(),
                'definition' => $definition,
                'points' => $points,
            ]);
            $test->questions()->attach($question, ['position' => $index + 1]);
        }

        $group = Group::factory()->for($teacher, 'owner')->create();
        $group->members()->attach($student, ['joined_at' => now()]);
        $group->tests()->attach($test, ['assigned_at' => now()]);

        return $test;
    }

    /**
     * Start, store $answers (position => answer, unanswered ones stay null) and submit.
     *
     * @param  array<int, array<string, mixed>|null>  $answers
     */
    public static function submitted(Test $test, User $student, array $answers): Attempt
    {
        $attempt = app(StartAttempt::class)->handle($student, $test, new AttemptClient(null, null, null))['attempt'];

        foreach ($attempt->questions()->get() as $question) {
            $answer = $answers[$question->position] ?? null;
            AttemptAnswer::query()->where('attempt_question_id', $question->id)->firstOrFail()
                ->forceFill(['answer' => $answer, 'answered_at' => $answer === null ? null : now()])
                ->save();
        }

        return app(SubmitAttempt::class)->handle($attempt)->refresh();
    }

    /**
     * One question, one answer: the graded answer row.
     *
     * @param  array<string, mixed>|null  $answer
     */
    public static function gradedAnswer(QuestionDefinition $definition, string $points, ?array $answer): AttemptAnswer
    {
        $student = User::factory()->student()->create();
        $test = self::test(User::factory()->teacher()->create(), $student, [[$definition, $points]]);
        $attempt = self::submitted($test, $student, [1 => $answer]);

        return AttemptAnswer::query()->where('attempt_question_id', $attempt->questions()->firstOrFail()->id)->sole();
    }
}
