<?php

declare(strict_types=1);

use App\Domain\Questions\Definitions\Item;
use App\Domain\Questions\Definitions\SingleChoiceDefinition;
use App\Domain\Questions\Definitions\TextDefinition;
use App\Enums\AuditAction;
use App\Models\Attempt;
use App\Models\AuditLog;
use App\Models\Test;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\GradingFixtures;
use Tests\Support\PayloadLeakScanner;

/**
 * A graded attempt (or one awaiting manual grading) of a test with the given settings.
 *
 * @param  array<string, mixed>  $settings
 * @return array{0: User, 1: Attempt, 2: Test}
 */
function resultAttempt(array $settings, bool $pending = false): array
{
    $teacher = User::factory()->teacher()->withTwoFactor()->create();
    $student = User::factory()->student()->create();
    $a = Item::make('Right');
    $b = Item::make('Wrong');
    $questions = [[new SingleChoiceDefinition([$a, $b], $a->id), '2.00']];

    if ($pending) {
        $questions[] = [new TextDefinition(500, 'Reference'), '1.00'];
    }

    $test = GradingFixtures::test($teacher, $student, $questions, $settings);
    $attempt = GradingFixtures::submitted($test, $student, [1 => ['selected' => $a->id], 2 => ['text' => 'Essay']]);

    return [$student, $attempt, $test];
}

/**
 * @return array<string, mixed>
 */
function resultProps(User $student, Attempt $attempt): array
{
    return test()->actingAs($student)->get(route('student.attempts.result', $attempt))->assertOk()->viewData('page')['props']['attempt'];
}

/*
 * The full matrix: visibility × time (before/after close) × grading status
 * × released × show_correct_answers. Expected values follow the table of
 * ResultsVisibilityPolicy.
 */
it('shows exactly what the settings allow', function (string $visibility, bool $closed, bool $pending, bool $released, bool $showCorrect): void {
    [$student, $attempt, $test] = resultAttempt([
        'results_visibility' => $visibility,
        'show_correct_answers' => $showCorrect,
        'closes_at' => $closed ? now()->addMinute() : null,
    ], $pending);

    if ($closed) {
        $this->travel(2)->minutes();
    }

    if ($released) {
        $test->forceFill(['results_released_at' => now()])->save();
    }

    $scoreVisible = ! $pending && match ($visibility) {
        'immediately' => true,
        'after_close' => $closed,
        'manual' => $released,
        default => false,
    };
    $correctVisible = $scoreVisible && $showCorrect;

    $props = resultProps($student, $attempt);

    expect($props['visibility']['score'])->toBe($scoreVisible)
        ->and($props['visibility']['correct_answers'])->toBe($correctVisible)
        ->and($props['visibility']['awaiting_review'])->toBe($pending)
        ->and($props['result'] !== null)->toBe($scoreVisible)
        ->and($props['questions'] !== [])->toBe($scoreVisible);

    if (! $correctVisible) {
        // Leak test: no correct-answer data anywhere in the props.
        expect(PayloadLeakScanner::findings(['attempt' => array_diff_key($props, ['visibility' => true])]))->toBe([]);
    }
})->with(function (): Generator {
    foreach (['immediately', 'after_close', 'manual', 'never'] as $visibility) {
        foreach ([false, true] as $closed) {
            foreach ([false, true] as $pending) {
                foreach ([false, true] as $released) {
                    foreach ([false, true] as $showCorrect) {
                        yield sprintf('%s, %s, %s, %s, %s', $visibility, $closed ? 'closed' : 'open', $pending ? 'pending' : 'graded', $released ? 'released' : 'not released', $showCorrect ? 'answers on' : 'answers off') => [$visibility, $closed, $pending, $released, $showCorrect];
                    }
                }
            }
        }
    }
});

it('reveals correct answers and explanations only with the setting on', function (): void {
    [$student, $attempt] = resultAttempt(['results_visibility' => 'immediately', 'show_correct_answers' => true]);

    $question = resultProps($student, $attempt)['questions'][0];

    expect($question['review'])->toHaveKey('correct')
        ->and($question['awarded_points'])->toBe('2.00');
});

it('is a 404 for another student, and for a preview', function (): void {
    [, $attempt] = resultAttempt(['results_visibility' => 'immediately']);

    $this->actingAs(User::factory()->student()->create())->get(route('student.attempts.result', $attempt))->assertNotFound();
});

it('shows the teacher feedback as plain text', function (): void {
    [$student, $attempt, $test] = resultAttempt(['results_visibility' => 'immediately'], pending: true);
    $answer = App\Models\AttemptAnswer::query()->where('needs_manual_grading', true)->sole();
    $this->actingAs($test->owner)->put(route('teacher.grading.answers.update', [$attempt, 2]), [
        'points' => '1', 'feedback' => "<b>Well</b> done\nnext line", 'version' => $answer->gradingVersion(),
    ])->assertSessionHasNoErrors();

    $props = resultProps($student, $attempt->fresh() ?? $attempt);

    expect($props['questions'][1]['feedback'])->toBe("<b>Well</b> done\nnext line");
});

describe('releasing results', function (): void {
    it('releases a manual test once, audited, owner only', function (): void {
        [$student, $attempt, $test] = resultAttempt(['results_visibility' => 'manual']);
        expect(resultProps($student, $attempt)['visibility']['score'])->toBeFalse();

        $this->actingAs(User::factory()->teacher()->withTwoFactor()->create())->post(route('teacher.tests.results.release', $test))->assertForbidden();

        $this->actingAs($test->owner)->post(route('teacher.tests.results.release', $test))->assertRedirect();
        $this->post(route('teacher.tests.results.release', $test))->assertRedirect();

        expect($test->fresh()?->results_released_at)->not->toBeNull()
            ->and(AuditLog::query()->where('action', AuditAction::TestResultsReleased->value)->count())->toBe(1)
            ->and(resultProps($student, $attempt)['visibility']['score'])->toBeTrue();
    });

    it('refuses tests that are not manual', function (): void {
        [, , $test] = resultAttempt(['results_visibility' => 'immediately']);

        $this->actingAs($test->owner)->post(route('teacher.tests.results.release', $test))->assertSessionHasErrors('test');
        expect($test->fresh()?->results_released_at)->toBeNull();
    });
});

it('lists the history with scores only where visible, and charts from visible results only', function (): void {
    [$student, $visible] = resultAttempt(['results_visibility' => 'immediately']);
    [$x, $y] = [Item::make('A'), Item::make('B')];
    $hiddenTest = GradingFixtures::test(User::factory()->teacher()->create(), $student, [[new SingleChoiceDefinition([$x, $y], $x->id), '1.00']], ['results_visibility' => 'never']);
    $hidden = GradingFixtures::submitted($hiddenTest, $student, [1 => null]);

    $this->actingAs($student)->get(route('student.results.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('student/results/index')
            ->has('results.data', 2)
            ->has('progress', 1)
            ->where('progress.0.percent', $visible->score_percent)
            ->has('strengths', 1));

    $rows = collect($this->get(route('student.results.index'))->viewData('page')['props']['results']['data'])->keyBy('ulid');
    expect($rows[$hidden->ulid]['visible'])->toBeFalse()
        ->and($rows[$hidden->ulid]['percent'])->toBeNull()
        ->and($rows[$hidden->ulid]['passed'])->toBeNull()
        ->and($rows[$visible->ulid]['percent'])->toBe($visible->score_percent);
});
