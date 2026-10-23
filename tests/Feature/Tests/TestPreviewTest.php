<?php

declare(strict_types=1);

use App\Domain\Attempts\SnapshotBuilder;
use App\Domain\Questions\Definitions\SingleChoiceDefinition;
use App\Domain\Shared\Randomizers;
use App\Enums\AttemptStatus;
use App\Enums\TestStatus;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use App\Models\Question;
use App\Models\Test;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PayloadLeakScanner;
use Tests\Support\TestPayloads;

function previewOwner(): User
{
    $teacher = User::factory()->teacher()->withTwoFactor()->create();
    test()->actingAs($teacher);

    return $teacher;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function previewableTest(User $teacher, int $count = 3, array $attributes = []): Test
{
    $test = Test::factory()->for($teacher, 'owner')->create(['shuffle_questions' => false, 'shuffle_options' => false, 'time_limit_minutes' => 20, ...$attributes]);

    foreach (Question::factory()->count($count)->for($teacher, 'owner')->singleChoice()->create(['points' => '2.00']) as $index => $question) {
        $test->questions()->attach($question, ['position' => $index + 1]);
    }

    return $test;
}

it('starts a preview attempt from snapshots without locking or counting', function (): void {
    $teacher = previewOwner();
    $test = previewableTest($teacher);

    $this->post(route('teacher.tests.preview.store', $test))->assertRedirect();

    $attempt = Attempt::query()->sole();
    expect($attempt->is_preview)->toBeTrue()
        ->and($attempt->user_id)->toBe($teacher->id)
        ->and($attempt->status)->toBe(AttemptStatus::InProgress)
        ->and($attempt->deadline_at)->not->toBeNull()
        ->and($attempt->max_score)->toBe('6.00')
        ->and($attempt->questions()->count())->toBe(3)
        ->and($test->fresh()?->locked_at)->toBeNull()
        ->and($test->realAttempts()->count())->toBe(0)
        ->and(Attempt::query()->real()->count())->toBe(0);
});

it('shows the preview in the exam player without answers', function (): void {
    $teacher = previewOwner();
    $test = previewableTest($teacher);
    $this->post(route('teacher.tests.preview.store', $test));
    $attempt = Attempt::query()->sole();

    $response = $this->get(route('teacher.tests.preview.show', [$test, $attempt]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('exam/test-preview')->has('questions', 3));

    $questions = $response->viewData('page')['props']['questions'];

    expect(PayloadLeakScanner::findings(['questions' => array_map(fn (array $q): mixed => $q['player'], $questions)]))->toBe([])
        ->and(array_keys($questions[0]))->toBe(['position', 'type', 'points', 'body', 'image_url', 'player']);
});

it('grades a submitted preview from its snapshots', function (): void {
    $teacher = previewOwner();
    $test = previewableTest($teacher, 2, ['pass_percent' => 50]);
    $this->post(route('teacher.tests.preview.store', $test));
    $attempt = Attempt::query()->sole();

    $answers = $attempt->questions()->get()->mapWithKeys(function (AttemptQuestion $row): array {
        /** @var SingleChoiceDefinition $definition */
        $definition = SingleChoiceDefinition::fromArray($row->snapshot['definition']);

        return [(string) $row->position => ['selected' => $row->position === 1 ? $definition->correct : null]];
    })->all();

    $result = $this->postJson(route('teacher.tests.preview.submit', [$test, $attempt]), ['answers' => json_encode($answers)])
        ->assertOk()
        ->json();

    expect($result['score'])->toBe('2.00')
        ->and($result['max_score'])->toBe('4.00')
        ->and($result['percent'])->toBe('50.00')
        ->and($result['passed'])->toBeTrue()
        ->and($result['questions'][0]['review'])->toHaveKey('correct')
        ->and($attempt->fresh()?->status)->toBe(AttemptStatus::Submitted);

    // Finished: a second submit is refused.
    $this->postJson(route('teacher.tests.preview.submit', [$test, $attempt]), ['answers' => '{}'])->assertStatus(409);
});

it('validates preview answers against the snapshots', function (): void {
    $teacher = previewOwner();
    $test = previewableTest($teacher, 1);
    $this->post(route('teacher.tests.preview.store', $test));
    $attempt = Attempt::query()->sole();

    $this->postJson(route('teacher.tests.preview.submit', [$test, $attempt]), ['answers' => json_encode(['1' => ['selected' => 'ZZZZZZZZ']])])
        ->assertUnprocessable();
});

it('replaces an unfinished preview when a new one starts', function (): void {
    $teacher = previewOwner();
    $test = previewableTest($teacher);

    $this->post(route('teacher.tests.preview.store', $test));
    $this->post(route('teacher.tests.preview.store', $test));

    expect(Attempt::query()->count())->toBe(1);
});

it('keeps other teachers out of a preview', function (): void {
    $teacher = previewOwner();
    $test = previewableTest($teacher);
    $this->post(route('teacher.tests.preview.store', $test));
    $attempt = Attempt::query()->sole();

    $this->actingAs(User::factory()->teacher()->withTwoFactor()->create());

    $this->get(route('teacher.tests.preview.show', [$test, $attempt]))->assertForbidden();
    $this->postJson(route('teacher.tests.preview.submit', [$test, $attempt]), ['answers' => '{}'])->assertForbidden();
});

it('does not open a real attempt as a preview', function (): void {
    $teacher = previewOwner();
    $test = previewableTest($teacher);
    $real = Attempt::factory()->for($test)->create();

    $this->get(route('teacher.tests.preview.show', [$test, $real]))->assertNotFound();
});

it('prunes preview attempts after 24 hours, never real attempts', function (): void {
    $teacher = previewOwner();
    $test = previewableTest($teacher);
    $this->post(route('teacher.tests.preview.store', $test));
    $real = Attempt::factory()->for($test)->create(['created_at' => now()->subDays(3)]);

    $this->travel(23)->hours();
    $this->artisan('braincheck:prune-preview-attempts')->assertSuccessful();
    expect(Attempt::query()->preview()->count())->toBe(1);

    $this->travel(2)->hours();
    $this->artisan('braincheck:prune-preview-attempts')->assertSuccessful();

    expect(Attempt::query()->preview()->count())->toBe(0)
        ->and(AttemptQuestion::query()->whereNot('attempt_id', $real->id)->count())->toBe(0)
        ->and($real->fresh())->not->toBeNull();
});

it('runs the whole teacher flow: create, build, preview, publish', function (): void {
    $teacher = previewOwner();
    $questions = Question::factory()->count(5)->for($teacher, 'owner')->singleChoice()->create(['points' => '1.00'])->all();

    $this->post(route('teacher.tests.store'), ['title' => 'Flow test'])->assertRedirect();
    $test = Test::query()->sole();

    $this->get(route('teacher.tests.question-picker', ['search' => '']))
        ->assertOk()
        ->assertJsonCount(5, 'questions.data');

    $this->put(route('teacher.tests.update', $test), TestPayloads::payload($test, [
        'time_limit_minutes' => 15,
        'pass_percent' => 80,
        'questions' => TestPayloads::links($questions, [4 => '3.00']),
    ]))->assertSessionHasNoErrors();

    $this->post(route('teacher.tests.preview.store', $test))->assertRedirect();
    $preview = Attempt::query()->preview()->sole();
    expect($preview->max_score)->toBe('7.00');

    $this->post(route('teacher.tests.publish', $test))->assertSessionHasNoErrors();

    $test->refresh();
    expect($test->status)->toBe(TestStatus::Published)
        ->and($test->locked_at)->toBeNull()
        ->and($test->realAttempts()->count())->toBe(0);
});

describe('snapshot builder', function (): void {
    it('draws N questions from a random pool and keeps archived attached questions', function (): void {
        $teacher = previewOwner();
        $test = previewableTest($teacher, 5, ['selection_mode' => 'random_pool', 'random_question_count' => 3]);
        $test->questions()->firstOrFail()->delete();

        $drawn = collect(range(1, 20))->map(fn (): string => collect(app(SnapshotBuilder::class)->build($test->fresh() ?? $test, Randomizers::secure()))->pluck('question.id')->sort()->implode(','));

        expect(app(SnapshotBuilder::class)->build($test, Randomizers::secure()))->toHaveCount(3)
            ->and($drawn->unique()->count())->toBeGreaterThan(1);
    });

    it('freezes the question and its points, with overrides', function (): void {
        $teacher = previewOwner();
        $test = previewableTest($teacher, 1);
        $question = $test->questions()->firstOrFail();
        $test->questions()->updateExistingPivot($question->id, ['points_override' => '5.50']);

        [$item] = app(SnapshotBuilder::class)->build($test, Randomizers::secure());

        expect($item['points']->toString())->toBe('5.50')
            ->and($item['snapshot']['version'])->toBe($question->version)
            ->and($item['snapshot']['definition'])->toBe($question->definition->toArray())
            ->and(PayloadLeakScanner::findings($item['snapshot']['player']))->toBe([]);
    });

    it('keeps the pivot order unless questions are shuffled', function (): void {
        $teacher = previewOwner();
        $test = previewableTest($teacher, 6);
        $expected = $test->questions()->pluck('questions.id')->all();

        expect(collect(app(SnapshotBuilder::class)->build($test, Randomizers::secure()))->pluck('question.id')->all())->toBe($expected);

        $test->forceFill(['shuffle_questions' => true])->save();
        $orders = collect(range(1, 20))->map(fn (): string => collect(app(SnapshotBuilder::class)->build($test, Randomizers::secure()))->pluck('question.id')->implode(','));

        expect($orders->unique()->count())->toBeGreaterThan(1);
    });
});
