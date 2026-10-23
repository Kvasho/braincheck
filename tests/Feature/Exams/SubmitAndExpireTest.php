<?php

declare(strict_types=1);

use App\Enums\AttemptStatus;
use App\Enums\GradingStatus;
use App\Enums\QuestionType;
use App\Models\Attempt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Tests\Support\ExamFixtures;

function runningAttempt(User $student, array $attributes = []): Attempt
{
    $test = ExamFixtures::assignedTest($student, [QuestionType::SingleChoice], $attributes);
    test()->actingAs($student)->post(route('student.exams.attempts.store', $test->ulid));

    return Attempt::query()->where('user_id', $student->id)->where('test_id', $test->id)->sole();
}

describe('submit', function (): void {
    it('submits once, idempotently, and grades it', function (): void {
        $student = ExamFixtures::student();
        $attempt = runningAttempt($student);

        $this->post(route('student.attempts.submit', $attempt))->assertRedirect(route('student.attempts.result', $attempt));
        $submittedAt = $attempt->fresh()?->submitted_at;
        $this->travel(5)->minutes();
        $this->post(route('student.attempts.submit', $attempt))->assertRedirect(route('student.attempts.result', $attempt));

        $attempt->refresh();
        expect($attempt->status)->toBe(AttemptStatus::Submitted)
            ->and($attempt->grading_status)->toBe(GradingStatus::Graded)
            ->and($attempt->graded_at)->not->toBeNull()
            ->and($attempt->submitted_at?->equalTo($submittedAt))->toBeTrue();
    });

    it('marks a submit after deadline + grace as timed out, submitted at the deadline', function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-10-23 10:00:00', 'UTC'));
        $student = ExamFixtures::student();
        $attempt = runningAttempt($student, ['time_limit_minutes' => 10]);

        $this->travelTo(CarbonImmutable::parse('2026-10-23 10:10:30', 'UTC'));
        $this->post(route('student.attempts.submit', $attempt))->assertRedirect(route('student.attempts.result', $attempt));

        $attempt->refresh();
        expect($attempt->status)->toBe(AttemptStatus::TimedOut)
            ->and($attempt->submitted_at?->toDateTimeString())->toBe('2026-10-23 10:10:00');
    });

    it('accepts a submit within the grace period as submitted', function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-10-23 10:00:00', 'UTC'));
        $student = ExamFixtures::student();
        $attempt = runningAttempt($student, ['time_limit_minutes' => 10]);

        $this->travelTo(CarbonImmutable::parse('2026-10-23 10:10:05', 'UTC'));
        $this->post(route('student.attempts.submit', $attempt));

        expect($attempt->fresh()?->status)->toBe(AttemptStatus::Submitted);
    });

    it('is 404 for another student', function (): void {
        $attempt = runningAttempt(ExamFixtures::student());

        $this->actingAs(ExamFixtures::student())->post(route('student.attempts.submit', $attempt))->assertNotFound();
        expect($attempt->fresh()?->status)->toBe(AttemptStatus::InProgress);
    });
});

describe('attempts:expire', function (): void {
    it('finishes exactly the expired real attempts, idempotently, skipping previews', function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-10-23 10:00:00', 'UTC'));
        $alice = ExamFixtures::student();
        $bob = ExamFixtures::student();
        $carol = ExamFixtures::student();
        $expired = runningAttempt($alice, ['time_limit_minutes' => 10]);
        $withinGrace = runningAttempt($bob, ['time_limit_minutes' => 20]);
        $untimed = runningAttempt($carol, ['time_limit_minutes' => null]);
        $preview = Attempt::factory()->for($expired->test)->for($expired->test->owner)->create([
            'is_preview' => true,
            'started_at' => now(),
            'deadline_at' => now()->addMinutes(5),
        ]);

        $this->travelTo(CarbonImmutable::parse('2026-10-23 10:20:05', 'UTC'));
        $this->artisan('attempts:expire')->expectsOutputToContain('Finished 1 expired attempt(s).')->assertSuccessful();
        $this->artisan('attempts:expire')->expectsOutputToContain('Finished 0 expired attempt(s).')->assertSuccessful();

        expect($expired->fresh()?->status)->toBe(AttemptStatus::TimedOut)
            ->and($expired->fresh()?->submitted_at?->toDateTimeString())->toBe('2026-10-23 10:10:00')
            ->and($withinGrace->fresh()?->status)->toBe(AttemptStatus::InProgress)
            ->and($untimed->fresh()?->status)->toBe(AttemptStatus::InProgress)
            ->and($preview->fresh()?->status)->toBe(AttemptStatus::InProgress)
            ->and($expired->fresh()?->grading_status)->toBe(GradingStatus::Graded)
            ->and($withinGrace->fresh()?->grading_status)->toBe(GradingStatus::PendingAuto);
    });

    it('is scheduled every minute without overlapping', function (): void {
        $event = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'attempts:expire'));

        expect($event)->not->toBeNull()
            ->and($event->expression)->toBe('* * * * *')
            ->and($event->withoutOverlapping)->toBeTrue();
    });
});

describe('anti-cheat events', function (): void {
    it('stores events with server timestamps', function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-10-23 10:00:00', 'UTC'));
        $student = ExamFixtures::student();
        $attempt = runningAttempt($student);

        $this->postJson(route('student.attempts.events.store', $attempt), ['events' => [
            ['type' => 'tab_hidden', 'meta' => ['question' => 1]],
            ['type' => 'paste', 'meta' => ['question' => 1, 'chars' => 12]],
            ['type' => 'copy', 'meta' => null],
        ]])->assertNoContent();

        $events = $attempt->events()->get();
        expect($events)->toHaveCount(3)
            ->and($events->pluck('occurred_at')->map->toDateTimeString()->unique()->all())->toBe(['2026-10-23 10:00:00'])
            ->and($events[1]->meta)->toBe(['question' => 1, 'chars' => 12]);
    });

    it('validates type, meta and the batch size', function (array $payload): void {
        $student = ExamFixtures::student();
        $attempt = runningAttempt($student);

        $this->postJson(route('student.attempts.events.store', $attempt), $payload)->assertUnprocessable();
        expect($attempt->events()->count())->toBe(0);
    })->with([
        'server-only type' => [['events' => [['type' => 'session_changed']]]],
        'unknown type' => [['events' => [['type' => 'screenshot']]]],
        'client time' => [['events' => [['type' => 'copy', 'occurred_at' => '2020-01-01']]]],
        'unknown meta key' => [['events' => [['type' => 'copy', 'meta' => ['text' => 'secret']]]]],
        'non-integer meta' => [['events' => [['type' => 'copy', 'meta' => ['question' => 'x']]]]],
        '51 events' => [['events' => array_fill(0, 51, ['type' => 'copy'])]],
        'empty' => [['events' => []]],
    ]);

    it('stores up to the per-attempt cap and only counts the rest per type', function (): void {
        config(['braincheck.attempt_events_per_attempt' => 60]);
        $student = ExamFixtures::student();
        $attempt = runningAttempt($student);

        $this->postJson(route('student.attempts.events.store', $attempt), ['events' => array_fill(0, 50, ['type' => 'copy'])])->assertNoContent();
        $this->postJson(route('student.attempts.events.store', $attempt), ['events' => [...array_fill(0, 20, ['type' => 'window_blur']), ...array_fill(0, 5, ['type' => 'paste'])]])->assertNoContent();

        expect($attempt->events()->count())->toBe(60)
            ->and($attempt->fresh()?->event_overflow)->toBe(['window_blur' => 10, 'paste' => 5]);
    });

    it('refuses events for another student\'s or a finished attempt', function (): void {
        $student = ExamFixtures::student();
        $attempt = runningAttempt($student);

        $this->actingAs(ExamFixtures::student())->postJson(route('student.attempts.events.store', $attempt), ['events' => [['type' => 'copy']]])->assertNotFound();

        $this->actingAs($student)->post(route('student.attempts.submit', $attempt));
        $this->postJson(route('student.attempts.events.store', $attempt), ['events' => [['type' => 'copy']]])->assertStatus(409);
    });

    it('is rate limited', function (): void {
        $student = ExamFixtures::student();
        $attempt = runningAttempt($student);

        foreach (range(1, 20) as $batch) {
            $this->postJson(route('student.attempts.events.store', $attempt), ['events' => [['type' => 'copy']]])->assertNoContent();
        }

        $this->postJson(route('student.attempts.events.store', $attempt), ['events' => [['type' => 'copy']]])->assertTooManyRequests();
    });
});
