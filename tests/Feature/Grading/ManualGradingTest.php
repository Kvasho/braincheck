<?php

declare(strict_types=1);

use App\Domain\Questions\Definitions\TextDefinition;
use App\Enums\AuditAction;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\AuditLog;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\GradingFixtures;

/**
 * A teacher's test with one text question (5 points) and $count submitted attempts.
 *
 * @return array{0: User, 1: list<Attempt>}
 */
function essays(int $count = 1, string $text = 'An essay'): array
{
    $teacher = User::factory()->teacher()->withTwoFactor()->create();
    $students = User::factory()->student()->count($count)->create();
    $test = GradingFixtures::test($teacher, $students[0], [[new TextDefinition(2000, 'Reference text'), '5.00']]);
    $attempts = [];

    foreach ($students as $student) {
        $test->groups()->firstOrFail()->members()->syncWithoutDetaching([$student->id => ['joined_at' => now()]]);
        $attempts[] = GradingFixtures::submitted($test, $student, [1 => ['text' => $text]]);
    }

    return [$teacher, $attempts];
}

function versionOf(Attempt $attempt): string
{
    return AttemptAnswer::query()->where('attempt_question_id', $attempt->questions()->firstOrFail()->id)->sole()->gradingVersion();
}

it('lists only the teacher\'s own pending answers, anonymously, oldest first', function (): void {
    [$teacher, $attempts] = essays(2);
    [$other] = essays();

    $this->actingAs($teacher)->get(route('teacher.grading.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teacher/grading/index')
            ->has('answers.data', 2)
            ->where('answers.data.0.attempt', $attempts[0]->ulid)
            ->where('answers.data.0.student', 'Student #1')
            ->where('answers.data.1.student', 'Student #2')
            ->where('counts.0.pending', 2)
            ->where('anonymous', true));

    $html = (string) $this->get(route('teacher.grading.index'))->getContent();
    expect($html)->not->toContain($attempts[0]->user->name)
        ->and($html)->not->toContain($attempts[0]->user->email);
});

it('shows the student text as a plain string, never as markup', function (): void {
    $script = "<script>alert('x')</script>\nSecond line";
    [$teacher, [$attempt]] = essays(text: $script);

    $this->actingAs($teacher)->get(route('teacher.grading.answers.show', [$attempt, 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teacher/grading/answer')
            ->where('answer.text', $script)
            ->where('answer.reference_answer', 'Reference text')
            ->where('student.name', null)
            ->where('student.label', 'Student #1'));
});

it('grades, then moves to the next pending answer', function (): void {
    [$teacher, [$first, $second]] = essays(2);

    $this->actingAs($teacher)
        ->put(route('teacher.grading.answers.update', [$first, 1]), ['points' => '4.25', 'feedback' => 'Good', 'version' => versionOf($first), 'next' => true])
        ->assertRedirect(route('teacher.grading.answers.show', [$second, 1]));

    $answer = AttemptAnswer::query()->where('attempt_question_id', $first->questions()->firstOrFail()->id)->sole();
    expect($answer->awarded_points)->toBe('4.25')
        ->and($answer->is_correct)->toBeNull()
        ->and($answer->graded_by)->toBe($teacher->id)
        ->and($answer->feedback)->toBe('Good')
        ->and($first->fresh()?->grading_status->value)->toBe('graded')
        ->and($first->fresh()?->score)->toBe('4.25');
});

it('sets is_correct for full and zero points', function (string $points, ?bool $correct): void {
    [$teacher, [$attempt]] = essays();

    $this->actingAs($teacher)->put(route('teacher.grading.answers.update', [$attempt, 1]), ['points' => $points, 'version' => versionOf($attempt)])->assertSessionHasNoErrors();

    expect(AttemptAnswer::query()->sole()->is_correct)->toBe($correct);
})->with([
    'full' => ['5.00', true],
    'zero' => ['0', false],
    'partial' => ['2.5', null],
]);

it('validates the points', function (string $points): void {
    [$teacher, [$attempt]] = essays();

    $this->actingAs($teacher)
        ->putJson(route('teacher.grading.answers.update', [$attempt, 1]), ['points' => $points, 'version' => versionOf($attempt)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('points');

    expect(AttemptAnswer::query()->sole()->graded_at)->toBeNull();
})->with([
    'above the maximum' => ['5.25'],
    'negative' => ['-1'],
    'off the 0.25 grid' => ['1.3'],
    'not a number' => ['many'],
]);

it('refuses feedback over 2000 characters', function (): void {
    [$teacher, [$attempt]] = essays();

    $this->actingAs($teacher)
        ->putJson(route('teacher.grading.answers.update', [$attempt, 1]), ['points' => '1', 'feedback' => str_repeat('a', 2001), 'version' => versionOf($attempt)])
        ->assertJsonValidationErrors('feedback');
});

it('gives the second of two concurrent graders a conflict', function (): void {
    [$teacher, [$attempt]] = essays();
    $loaded = versionOf($attempt);

    $this->actingAs($teacher)->put(route('teacher.grading.answers.update', [$attempt, 1]), ['points' => '5', 'version' => $loaded])->assertSessionHasNoErrors();

    $this->putJson(route('teacher.grading.answers.update', [$attempt, 1]), ['points' => '1', 'version' => $loaded])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('version');

    expect(AttemptAnswer::query()->sole()->awarded_points)->toBe('5.00');
});

it('keeps other teachers out (403)', function (): void {
    [, [$attempt]] = essays();
    $stranger = User::factory()->teacher()->withTwoFactor()->create();

    $this->actingAs($stranger)->get(route('teacher.grading.answers.show', [$attempt, 1]))->assertForbidden();
    $this->put(route('teacher.grading.answers.update', [$attempt, 1]), ['points' => '5', 'version' => versionOf($attempt)])->assertForbidden();
    $this->postJson(route('teacher.grading.identity.store', $attempt))->assertForbidden();
    $this->get(route('teacher.grading.index'))->assertInertia(fn (Assert $page) => $page->has('answers.data', 0));

    expect(AttemptAnswer::query()->sole()->graded_at)->toBeNull();
});

it('reveals one identity at a time, audited, and audits switching anonymity off', function (): void {
    [$teacher, [$attempt]] = essays();

    $this->actingAs($teacher)->postJson(route('teacher.grading.identity.store', $attempt))
        ->assertOk()
        ->assertExactJson(['name' => $attempt->user->name]);

    $this->put(route('teacher.grading.anonymity.update'), ['anonymous' => false])->assertRedirect(route('teacher.grading.index'));
    $this->get(route('teacher.grading.index'))->assertInertia(fn (Assert $page) => $page->where('answers.data.0.student', $attempt->user->name)->where('anonymous', false));

    $logs = AuditLog::query()->where('action', AuditAction::GradingIdentityRevealed->value)->orderBy('id')->get();
    expect($logs)->toHaveCount(2)
        ->and($logs[0]->properties)->toBe(['scope' => 'attempt'])
        ->and($logs[1]->properties)->toBe(['scope' => 'all']);
});
