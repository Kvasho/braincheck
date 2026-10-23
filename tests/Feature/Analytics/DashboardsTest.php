<?php

declare(strict_types=1);

use App\Domain\Questions\Definitions\Item;
use App\Domain\Questions\Definitions\SingleChoiceDefinition;
use App\Domain\Questions\Definitions\TextDefinition;
use App\Models\Attempt;
use App\Models\TestQuestionStat;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\GradingFixtures;

it('gives teachers their cards, closing tests, recent submissions and flagged questions', function (): void {
    $teacher = User::factory()->teacher()->withTwoFactor()->create();
    $student = User::factory()->student()->create();
    [$a, $b] = [Item::make('A'), Item::make('B')];
    $test = GradingFixtures::test($teacher, $student, [
        [new SingleChoiceDefinition([$a, $b], $a->id), '1.00'],
        [new TextDefinition(500, null), '1.00'],
    ], ['closes_at' => now()->addDays(2)]);
    GradingFixtures::submitted($test, $student, [1 => ['selected' => $a->id], 2 => ['text' => 'Essay']]);
    TestQuestionStat::factory()->needsReview()->for($test)->create(['excerpt' => 'Flagged question']);
    // Someone else's test never shows up.
    GradingFixtures::test(User::factory()->teacher()->create(), User::factory()->student()->create(), [[new SingleChoiceDefinition([$a, $b], $a->id), '1.00']]);

    $this->actingAs($teacher)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/teacher')
            ->where('dashboard.cards.active_tests', 1)
            ->where('dashboard.cards.submissions_week', 1)
            ->where('dashboard.cards.pending_grading', 1)
            ->where('dashboard.closing_soon.0.ulid', $test->ulid)
            ->has('dashboard.recent', 1)
            ->where('dashboard.needs_review.0.excerpt', 'Flagged question'));
});

it('gives students their exams, results and placeholders, from visible results only', function (): void {
    $student = User::factory()->student()->create();
    [$a, $b] = [Item::make('A'), Item::make('B')];
    $visible = GradingFixtures::test(User::factory()->teacher()->create(), $student, [[new SingleChoiceDefinition([$a, $b], $a->id), '1.00']]);
    $hidden = GradingFixtures::test(User::factory()->teacher()->create(), $student, [[new SingleChoiceDefinition([$a, $b], $a->id), '1.00']], ['results_visibility' => 'never']);
    GradingFixtures::submitted($visible, $student, [1 => ['selected' => $a->id]]);
    GradingFixtures::submitted($hidden, $student, [1 => ['selected' => $a->id]]);

    $this->actingAs($student)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/student')
            ->where('dashboard.available', 2)
            ->has('dashboard.latest', 1)
            ->where('dashboard.latest.0.test', $visible->title)
            ->where('dashboard.xp', null)
            ->where('dashboard.level', null));
});

it('gives admins platform analytics without previews', function (): void {
    Cache::flush();
    $admin = User::factory()->admin()->withTwoFactor()->create();
    $student = User::factory()->student()->create();
    [$a, $b] = [Item::make('A'), Item::make('B')];
    $test = GradingFixtures::test(User::factory()->teacher()->create(['name' => 'Most Active']), $student, [[new SingleChoiceDefinition([$a, $b], $a->id), '1.00']]);
    GradingFixtures::submitted($test, $student, [1 => ['selected' => $a->id]]);
    Attempt::factory()->for($test)->for($test->owner)->graded()->create(['is_preview' => true, 'attempt_number' => 5]);

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/admin')
            ->where('platform.submissions_total', 1)
            ->where('platform.pass_rate', '1.0000')
            ->where('platform.top_teachers.0.name', 'Most Active')
            ->where('platform.top_tests.0.ulid', $test->ulid)
            ->has('platform.per_day', 30));
});
