<?php

declare(strict_types=1);

use App\Models\Attempt;
use App\Models\Group;
use App\Models\Test;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ExamFixtures;

it('sorts visible exams into sections and shows nothing else', function (): void {
    $student = ExamFixtures::student();
    $available = ExamFixtures::assignedTest($student, attributes: ['title' => 'A available', 'closes_at' => now()->addDay()]);
    $upcoming = ExamFixtures::assignedTest($student, attributes: ['title' => 'B upcoming', 'opens_at' => now()->addDay()]);
    $closed = ExamFixtures::assignedTest($student, attributes: ['title' => 'C closed', 'closes_at' => now()->subMinute()]);
    $exhausted = ExamFixtures::assignedTest($student, attributes: ['title' => 'D exhausted', 'max_attempts' => 1]);
    Attempt::factory()->for($exhausted)->for($student)->submitted()->create();
    $running = ExamFixtures::assignedTest($student, attributes: ['title' => 'E running']);
    $this->actingAs($student)->post(route('student.exams.attempts.store', $running->ulid));
    ExamFixtures::assignedTest(ExamFixtures::student(), attributes: ['title' => 'Someone else']);
    ExamFixtures::assignedTest($student, attributes: ['title' => 'Draft', 'status' => 'draft']);

    $exams = collect($this->get(route('student.exams.index'))->assertOk()->viewData('page')['props']['exams']);

    expect($exams->pluck('section', 'title')->all())->toBe([
        'A available' => 'available',
        'B upcoming' => 'upcoming',
        'C closed' => 'closed',
        'D exhausted' => 'closed',
        'E running' => 'in_progress',
    ])->and($exams->firstWhere('title', 'E running')['attempt']['ulid'])->toBe(Attempt::query()->where('test_id', $running->id)->sole()->ulid)
        ->and($exams->firstWhere('title', 'D exhausted')['attempts_used'])->toBe(1)
        ->and(array_keys($exams->first()))->toBe(['ulid', 'title', 'description', 'teacher', 'groups', 'time_limit_minutes', 'question_count', 'pass_percent', 'attempts_used', 'max_attempts', 'opens_at', 'closes_at', 'section', 'attempt', 'best_percent']);
});

it('filters by one of the student\'s groups and ignores other groups', function (): void {
    $student = ExamFixtures::student();
    $first = ExamFixtures::assignedTest($student, attributes: ['title' => 'First']);
    ExamFixtures::assignedTest($student, attributes: ['title' => 'Second']);
    $group = $first->groups()->firstOrFail();

    $titles = fn (array $query): array => collect($this->actingAs($student)->get(route('student.exams.index', $query))->viewData('page')['props']['exams'])->pluck('title')->all();

    expect($titles(['group' => $group->ulid]))->toBe(['First'])
        ->and($titles(['group' => Group::factory()->create()->ulid]))->toBe(['First', 'Second']);
});

it('shows the best score only when results visibility allows it', function (string $visibility, bool $closed, bool $shown): void {
    $student = ExamFixtures::student();
    $test = ExamFixtures::assignedTest($student, attributes: ['results_visibility' => $visibility, 'closes_at' => $closed ? now()->subMinute() : null]);
    $attempt = Attempt::factory()->for($test)->for($student)->graded()->create();

    $card = $this->actingAs($student)->get(route('student.exams.index'))->viewData('page')['props']['exams'][0];

    expect($card['best_percent'])->toBe($shown ? $attempt->fresh()?->score_percent : null);
})->with([
    'immediately' => ['immediately', false, true],
    'after close, still open' => ['after_close', false, false],
    'after close, closed' => ['after_close', true, true],
    'manual' => ['manual', true, false],
    'never' => ['never', true, false],
]);

it('shows the intro only for visible tests (404 otherwise)', function (): void {
    $student = ExamFixtures::student();
    $visible = ExamFixtures::assignedTest($student, attributes: ['pass_percent' => 70]);
    $hidden = Test::factory()->published()->create();

    $this->actingAs($student)->get(route('student.exams.show', $visible->ulid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('student/exams/show')->where('exam.pass_percent', 70)->where('exam.section', 'available'));

    $this->get(route('student.exams.show', $hidden->ulid))->assertNotFound();
});
