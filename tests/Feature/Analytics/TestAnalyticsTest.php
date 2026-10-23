<?php

declare(strict_types=1);

use App\Domain\Analytics\QuestionStatsCalculator;
use App\Domain\Questions\Definitions\Item;
use App\Domain\Questions\Definitions\SingleChoiceDefinition;
use App\Models\Attempt;
use App\Models\AttemptEvent;
use App\Models\Test;
use App\Models\TestQuestionStat;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\GradingFixtures;

/**
 * Four single-choice questions (1 point each, pass mark 50 %), and one
 * student per entry in $correctCounts answering the first N right
 * (so 1, 2, 3, 4 → 25 %, 50 %, 75 %, 100 %).
 *
 * @param  list<int>  $correctCounts
 * @return array{0: User, 1: Test, 2: list<Attempt>, 3: list<array{0: Item, 1: Item}>}
 */
function analyticsTest(array $correctCounts = [1, 2, 3, 4]): array
{
    $teacher = User::factory()->teacher()->withTwoFactor()->create();
    $options = array_map(fn (int $i): array => [Item::make("Right {$i}"), Item::make("Wrong {$i}")], range(1, 4));
    $first = User::factory()->student()->create();
    $test = GradingFixtures::test($teacher, $first, array_map(fn (array $pair): array => [new SingleChoiceDefinition($pair, $pair[0]->id), '1.00'], $options), ['pass_percent' => 50]);
    $attempts = [];

    foreach ($correctCounts as $index => $correct) {
        $student = $index === 0 ? $first : User::factory()->student()->create();
        $test->groups()->firstOrFail()->members()->syncWithoutDetaching([$student->id => ['joined_at' => now()]]);
        $answers = [];

        foreach ($options as $position => [$right, $wrong]) {
            $answers[$position + 1] = ['selected' => $position < $correct ? $right->id : $wrong->id];
        }

        $attempts[] = GradingFixtures::submitted($test, $student, $answers);
    }

    return [$teacher, $test, $attempts, $options];
}

describe('access', function (): void {
    it('is for the owner and admins only', function (): void {
        [$teacher, $test] = analyticsTest([2]);

        $this->actingAs($teacher)->get(route('teacher.tests.analytics.show', $test))->assertOk()->assertInertia(fn (Assert $page) => $page->component('analytics/test'));
        $this->actingAs(User::factory()->teacher()->withTwoFactor()->create())->get(route('teacher.tests.analytics.show', $test))->assertForbidden();
        $this->actingAs(User::factory()->admin()->withTwoFactor()->create())->get(route('admin.tests.analytics.show', $test))->assertOk();
    });

    it('does not show an attempt of another test', function (): void {
        [$teacher, $test] = analyticsTest([2]);
        [, , [$foreign]] = analyticsTest([1]);

        $this->actingAs($teacher)->get(route('teacher.tests.analytics.attempts.show', [$test, $foreign]))->assertNotFound();
    });
});

it('computes the overview from real graded attempts only', function (): void {
    [$teacher, $test] = analyticsTest();
    // A teacher preview with a perfect score must not count anywhere.
    Attempt::factory()->for($test)->for($teacher)->graded()->create(['is_preview' => true, 'attempt_number' => 9, 'score_percent' => '100.00']);

    $overview = $this->actingAs($teacher)->get(route('teacher.tests.analytics.show', $test))->viewData('page')['props']['overview'];

    // Scores 25, 50, 75, 100: mean = median = 62.5; σ = √((37.5² + 12.5² + 12.5² + 37.5²) / 4) = √781.25 = 27.95.
    expect($overview)->toMatchArray([
        'attempt_count' => 4,
        'students' => 4,
        'average' => '62.50',
        'median' => '62.50',
        'standard_deviation' => '27.95',
        'pass_rate' => '0.7500',
    ])->and(array_column($overview['distribution'], 'count'))->toBe([0, 0, 1, 0, 0, 1, 0, 1, 0, 1]);
});

it('precomputes per-question stats when attempts are graded', function (): void {
    [$teacher, $test, , $options] = analyticsTest();

    $stats = TestQuestionStat::query()->where('test_id', $test->id)->get()->keyBy(fn (TestQuestionStat $stat): string => $stat->excerpt);
    expect($stats)->toHaveCount(4);

    $questions = collect($this->actingAs($teacher)->get(route('teacher.tests.analytics.show', $test))->viewData('page')['props']['questions']);
    // Q1: everybody right (p = 1); Q4: only the best student (p = 0.25).
    expect($questions->pluck('difficulty')->sort()->values()->all())->toBe(['0.2500', '0.5000', '0.7500', '1.0000']);

    $hardest = $questions->firstWhere('difficulty', '0.2500');
    $distractors = collect($hardest['distractors'])->keyBy('id');
    expect($distractors[$options[3][0]->id])->toMatchArray(['correct' => true, 'picks' => 1])
        ->and($distractors[$options[3][1]->id])->toMatchArray(['correct' => false, 'picks' => 3]);
});

it('invalidates the cached numbers when a new attempt is graded', function (): void {
    [$teacher, $test, , $options] = analyticsTest([4]);
    $this->actingAs($teacher)->get(route('teacher.tests.analytics.show', $test))->assertInertia(fn (Assert $page) => $page->where('overview.attempt_count', 1));

    $student = User::factory()->student()->create();
    $test->groups()->firstOrFail()->members()->attach($student, ['joined_at' => now()]);
    GradingFixtures::submitted($test, $student, array_combine([1, 2, 3, 4], array_map(fn (array $pair): array => ['selected' => $pair[1]->id], $options)));

    $this->get(route('teacher.tests.analytics.show', $test))
        ->assertInertia(fn (Assert $page) => $page->where('overview.attempt_count', 2)->where('overview.average', '50.00'));
});

it('filters by group', function (): void {
    [$teacher, $test, $attempts] = analyticsTest();
    $group = App\Models\Group::factory()->for($teacher, 'owner')->create();
    $group->members()->attach($attempts[3]->user_id, ['joined_at' => now()]);
    $group->tests()->attach($test, ['assigned_at' => now()]);

    $this->actingAs($teacher)->get(route('teacher.tests.analytics.show', [$test, 'group' => $group->ulid]))
        ->assertInertia(fn (Assert $page) => $page->where('overview.attempt_count', 1)->where('overview.average', '100.00')->has('attemptRows.data', 1));
});

it('shows an attempt with the integrity timeline', function (): void {
    [$teacher, $test, [$attempt]] = analyticsTest([1]);
    $event = new AttemptEvent(['type' => 'tab_hidden', 'occurred_at' => $attempt->started_at->addSeconds(90), 'meta' => ['question' => 2]]);
    $event->attempt()->associate($attempt)->save();

    $this->actingAs($teacher)->get(route('teacher.tests.analytics.attempts.show', [$test, $attempt]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('analytics/attempt')
            ->where('timeline.0.type', 'tab_hidden')
            ->where('timeline.0.seconds', 90)
            ->has('questions', 4)
            ->has('questions.0.review.correct'));
});

describe('CSV export', function (): void {
    it('neutralises formula injection and keeps Georgian readable', function (): void {
        [$teacher, $test, [$attempt]] = analyticsTest([3]);
        $attempt->user->forceFill(['name' => '=HYPERLINK("http://evil.test","ნინო")'])->save();

        $response = $this->actingAs($teacher)->get(route('teacher.tests.results.export', $test))->assertOk();
        $csv = $response->streamedContent();

        expect(substr($csv, 0, 3))->toBe("\xEF\xBB\xBF")
            ->and($csv)->toContain('"\'=HYPERLINK(""http://evil.test"",""ნინო"")"')
            ->and($csv)->toContain('75.00')
            ->and($response->headers->get('Content-Type'))->toContain('text/csv');
    });

    it('is for the owner and admins only', function (): void {
        [, $test] = analyticsTest([1]);

        $this->actingAs(User::factory()->teacher()->withTwoFactor()->create())->get(route('teacher.tests.results.export', $test))->assertForbidden();
        $this->actingAs(User::factory()->admin()->withTwoFactor()->create())->get(route('admin.tests.results.export', $test))->assertOk();
    });
});

it('stays within a small number of queries for 500 attempts', function (): void {
    $teacher = User::factory()->teacher()->withTwoFactor()->create();
    $test = Test::factory()->for($teacher, 'owner')->published()->withQuestions(3)->create();
    foreach (range(0, 499) as $index) {
        Attempt::factory()->for($test)->gradedWith($index % 4)->create();
    }
    app(QuestionStatsCalculator::class)->recompute($test);

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $this->actingAs($teacher)->get(route('teacher.tests.analytics.show', $test))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('overview.attempt_count', 500)->has('questions', 3));

    expect($queries)->toBeLessThan(40);
})->group('slow');
