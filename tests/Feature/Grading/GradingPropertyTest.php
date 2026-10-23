<?php

declare(strict_types=1);

use App\Domain\Questions\Points;
use App\Enums\QuestionType;
use App\Models\AttemptAnswer;
use App\Models\User;
use Database\Factories\QuestionFactory;
use Tests\Support\ExamFixtures;
use Tests\Support\GradingFixtures;

/*
 * Property: for randomly generated attempts (random types, definitions,
 * points and answers built from what the player shows), the totals are
 * always consistent: score == Σ awarded_points and 0 ≤ score ≤ max_score.
 */
it('keeps score consistent for random attempts', function (int $run): void {
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $types = QuestionType::cases();
    $questions = [];

    foreach (range(1, random_int(1, 6)) as $index) {
        $type = $types[array_rand($types)];
        $points = random_int(1, 999).'.'.str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT);
        $questions[] = [QuestionFactory::definitionFor($type), $points];
    }

    $test = GradingFixtures::test($teacher, $student, $questions);
    $attempt = app(App\Actions\Attempts\StartAttempt::class)->handle($student, $test, new App\Domain\Attempts\AttemptClient(null, null, null))['attempt'];

    $answers = [];
    foreach ($attempt->questions()->get() as $question) {
        $answers[$question->position] = random_int(0, 3) === 0
            ? null
            : ExamFixtures::answerFromPlayer(QuestionType::from($question->snapshot['type']), $question->snapshot['player']);
    }

    foreach ($attempt->questions()->get() as $question) {
        AttemptAnswer::query()->where('attempt_question_id', $question->id)->firstOrFail()
            ->forceFill(['answer' => $answers[$question->position], 'answered_at' => now()])->save();
    }

    $attempt = app(App\Actions\Attempts\SubmitAttempt::class)->handle($attempt)->refresh();
    $awarded = AttemptAnswer::query()
        ->whereIn('attempt_question_id', $attempt->questions()->pluck('id'))
        ->pluck('awarded_points')
        ->reduce(fn (Points $sum, ?string $points): Points => $sum->plus(Points::of($points ?? '0')), Points::zero());

    $score = Points::of((string) $attempt->score);
    $max = Points::of((string) $attempt->max_score);

    expect($score->equals($awarded))->toBeTrue()
        ->and($score->compareTo(Points::zero()))->toBeGreaterThanOrEqual(0)
        ->and($score->isGreaterThan($max))->toBeFalse()
        ->and((float) $attempt->score_percent)->toBeGreaterThanOrEqual(0.0)->toBeLessThanOrEqual(100.0);
})->with(fn (): array => array_map(fn (int $run): array => [$run], range(1, 25)));
