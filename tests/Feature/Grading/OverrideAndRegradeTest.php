<?php

declare(strict_types=1);

use App\Domain\Questions\Definitions\Item;
use App\Domain\Questions\Definitions\SingleChoiceDefinition;
use App\Domain\Questions\Types\QuestionTypeRegistry;
use App\Enums\AuditAction;
use App\Enums\QuestionType;
use App\Events\AttemptRegraded;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\AttemptQuestion;
use App\Models\AuditLog;
use App\Models\Question;
use App\Models\Test;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\Support\GradingFixtures;

/**
 * Two single-choice questions (5 points each, pass mark 60 %). The first one's
 * key marks the wrong option ($wrongKey) as correct.
 *
 * @return array{teacher: User, test: Test, right: Item, wrongKey: Item, question: Question}
 */
function mistakenKey(): array
{
    $teacher = User::factory()->teacher()->withTwoFactor()->create();
    $student = User::factory()->student()->create();
    $right = Item::make('Tbilisi');
    $wrongKey = Item::make('Batumi');
    $other = [Item::make('A'), Item::make('B')];
    $test = GradingFixtures::test($teacher, $student, [
        [new SingleChoiceDefinition([$right, $wrongKey], $wrongKey->id), '5.00'],
        [new SingleChoiceDefinition($other, $other[0]->id), '5.00'],
    ], ['pass_percent' => 60]);

    // The student answered the truly right option (marked wrong) and the second question correctly.
    GradingFixtures::submitted($test, $student, [1 => ['selected' => $right->id], 2 => ['selected' => $other[0]->id]]);

    return ['teacher' => $teacher, 'test' => $test, 'right' => $right, 'wrongKey' => $wrongKey, 'question' => $test->questions()->orderByPivot('position')->firstOrFail()];
}

/**
 * A definition as the editor sends it (the handler's editor input, JSON text).
 *
 * @param  list<Item>  $options
 */
function regradeEditorInput(array $options, Item $correct): string
{
    return (string) json_encode(app(QuestionTypeRegistry::class)->for(QuestionType::SingleChoice)->toEditorInput(new SingleChoiceDefinition($options, $correct->id)));
}

/**
 * The fixed definition: the right option is now the correct one.
 */
function fixedKey(Item $right, Item $wrongKey): string
{
    return regradeEditorInput([$right, $wrongKey], $right);
}

describe('overrides', function (): void {
    it('requires a reason', function (): void {
        ['teacher' => $teacher, 'test' => $test] = mistakenKey();
        $attempt = Attempt::query()->where('test_id', $test->id)->sole();

        $this->actingAs($teacher)->putJson(route('teacher.grading.answers.override', [$attempt, 1]), ['points' => '5'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
    });

    it('changes the points, recomputes totals, audits and fires AttemptRegraded', function (): void {
        Event::fake([AttemptRegraded::class]);
        ['teacher' => $teacher, 'test' => $test] = mistakenKey();
        $attempt = Attempt::query()->where('test_id', $test->id)->sole();
        expect($attempt->score)->toBe('5.00')->and($attempt->passed)->toBeFalse();

        $this->actingAs($teacher)
            ->put(route('teacher.grading.answers.override', [$attempt, 1]), ['points' => '5', 'reason' => 'The key was wrong'])
            ->assertSessionHasNoErrors();

        $attempt->refresh();
        expect($attempt->score)->toBe('10.00')
            ->and($attempt->score_percent)->toBe('100.00')
            ->and($attempt->passed)->toBeTrue();

        $log = AuditLog::query()->where('action', AuditAction::GradingOverride->value)->sole();
        expect($log->properties)->toBe(['position' => 1, 'old_points' => '0.00', 'new_points' => '5.00', 'reason' => 'The key was wrong'])
            ->and($log->actor_id)->toBe($teacher->id);
        Event::assertDispatched(AttemptRegraded::class, fn (AttemptRegraded $event): bool => $event->oldScore === '5.00' && $event->newScore === '10.00' && $event->oldPassed === false && $event->newPassed === true);
    });

    it('is only for the test owner', function (): void {
        ['test' => $test] = mistakenKey();
        $attempt = Attempt::query()->where('test_id', $test->id)->sole();

        $this->actingAs(User::factory()->teacher()->withTwoFactor()->create())
            ->put(route('teacher.grading.answers.override', [$attempt, 1]), ['points' => '5', 'reason' => 'Mine now'])
            ->assertForbidden();
    });
});

describe('regrade', function (): void {
    it('previews the score changes without writing anything', function (): void {
        ['teacher' => $teacher, 'test' => $test, 'right' => $right, 'wrongKey' => $wrongKey, 'question' => $question] = mistakenKey();

        $preview = $this->actingAs($teacher)->postJson(route('teacher.tests.regrade.preview', $test), [
            'question' => $question->ulid,
            'version' => $question->version,
            'definition' => fixedKey($right, $wrongKey),
        ])->assertOk()->json();

        expect($preview['summary'])->toBe(['affected' => 1, 'changed' => 1, 'passed_flips' => 1])
            ->and($preview['changes'][0])->toMatchArray(['old_score' => '5.00', 'new_score' => '10.00', 'old_passed' => false, 'new_passed' => true])
            ->and(Attempt::query()->sole()->score)->toBe('5.00');
    });

    it('needs a fresh password confirmation to start', function (): void {
        ['teacher' => $teacher, 'test' => $test, 'right' => $right, 'wrongKey' => $wrongKey, 'question' => $question] = mistakenKey();

        $this->actingAs($teacher)->post(route('teacher.tests.regrade.store', $test), [
            'question' => $question->ulid,
            'version' => $question->version,
            'definition' => fixedKey($right, $wrongKey),
        ])->assertRedirect(route('password.confirm'));

        expect(Attempt::query()->sole()->score)->toBe('5.00');
    });

    it('fixes only the targeted test\'s snapshots, recomputes scores and pass results, audits and fires events', function (): void {
        Event::fake([AttemptRegraded::class]);
        ['teacher' => $teacher, 'test' => $test, 'right' => $right, 'wrongKey' => $wrongKey, 'question' => $question] = mistakenKey();

        // The same question in another test of the same teacher: must stay untouched.
        $otherStudent = User::factory()->student()->create();
        $otherTest = GradingFixtures::test($teacher, $otherStudent, []);
        $otherTest->questions()->attach($question, ['position' => 1]);
        $other = GradingFixtures::submitted($otherTest, $otherStudent, [1 => ['selected' => $right->id]]);
        $otherSnapshot = AttemptQuestion::query()->where('attempt_id', $other->id)->sole()->snapshot;

        $this->actingAs($teacher)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('teacher.tests.regrade.store', $test), [
                'question' => $question->ulid,
                'version' => $question->version,
                'definition' => fixedKey($right, $wrongKey),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('teacher.tests.regrade.show', $test));

        $attempt = Attempt::query()->where('test_id', $test->id)->sole();
        $snapshot = AttemptQuestion::query()->where('attempt_id', $attempt->id)->where('position', 1)->sole()->snapshot;

        expect($attempt->score)->toBe('10.00')
            ->and($attempt->passed)->toBeTrue()
            ->and($snapshot['definition']['correct'])->toBe($right->id)
            ->and($snapshot['regraded_at'])->not->toBeNull()
            ->and($snapshot['player'])->toBe(AttemptQuestion::query()->where('attempt_id', $attempt->id)->where('position', 1)->sole()->snapshot['player'])
            // Other test: same question, untouched.
            ->and(AttemptQuestion::query()->where('attempt_id', $other->id)->sole()->snapshot)->toBe($otherSnapshot)
            ->and($other->fresh()?->score)->toBe('0.00')
            // The question bank itself is not changed by a regrade.
            ->and($question->fresh()?->definition->toArray()['correct'])->toBe($wrongKey->id);

        $log = AuditLog::query()->where('action', AuditAction::GradingRegrade->value)->sole();
        expect($log->properties)->toMatchArray(['version' => $question->version, 'affected' => 1, 'changed' => 1, 'passed_flips' => 1]);
        Event::assertDispatchedTimes(AttemptRegraded::class, 1);
    });

    it('refuses to change the options students saw', function (): void {
        ['teacher' => $teacher, 'test' => $test, 'right' => $right, 'question' => $question] = mistakenKey();
        $changed = regradeEditorInput([$right, Item::make('A new option')], $right);

        $this->actingAs($teacher)->postJson(route('teacher.tests.regrade.preview', $test), [
            'question' => $question->ulid,
            'version' => $question->version,
            'definition' => $changed,
        ])->assertUnprocessable()->assertJsonValidationErrors('definition');
    });

    it('lets admins regrade any test, and nobody else', function (): void {
        ['test' => $test, 'right' => $right, 'wrongKey' => $wrongKey, 'question' => $question] = mistakenKey();
        $payload = ['question' => $question->ulid, 'version' => $question->version, 'definition' => fixedKey($right, $wrongKey)];

        $this->actingAs(User::factory()->teacher()->withTwoFactor()->create())
            ->postJson(route('teacher.tests.regrade.preview', $test), $payload)
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->withTwoFactor()->create())
            ->postJson(route('admin.tests.regrade.preview', $test), $payload)
            ->assertOk();
    });

    it('regrades only the targeted question and keeps the others, overrides included', function (): void {
        ['teacher' => $teacher, 'test' => $test, 'right' => $right, 'wrongKey' => $wrongKey, 'question' => $question] = mistakenKey();
        $attempt = Attempt::query()->where('test_id', $test->id)->sole();
        $this->actingAs($teacher)->put(route('teacher.grading.answers.override', [$attempt, 2]), ['points' => '2', 'reason' => 'Half credit']);

        $this->withSession(['auth.password_confirmed_at' => time()])->post(route('teacher.tests.regrade.store', $test), [
            'question' => $question->ulid,
            'version' => $question->version,
            'definition' => fixedKey($right, $wrongKey),
        ]);

        // Question 1 regraded (5), question 2 keeps the override (2).
        expect($attempt->fresh()?->score)->toBe('7.00')
            ->and(AttemptAnswer::query()->where('attempt_question_id', AttemptQuestion::query()->where('attempt_id', $attempt->id)->where('position', 2)->sole()->id)->sole()->awarded_points)->toBe('2.00');
    });
});
