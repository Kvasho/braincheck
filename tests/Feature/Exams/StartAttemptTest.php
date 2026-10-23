<?php

declare(strict_types=1);

use App\Actions\Attempts\StartAttempt;
use App\Domain\Attempts\AttemptClient;
use App\Domain\Attempts\SnapshotBuilder;
use App\Domain\Shared\Randomizers;
use App\Enums\AttemptStatus;
use App\Enums\QuestionType;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Question;
use App\Models\Test;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\ExamFixtures;

function startAs(User $student, Test $test): Illuminate\Testing\TestResponse
{
    return test()->actingAs($student)->post(route('student.exams.attempts.store', $test->ulid));
}

it('starts an attempt from snapshots and opens the player', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-23 10:00:00', 'UTC'));
    $student = ExamFixtures::student();
    $test = ExamFixtures::assignedTest($student, [QuestionType::SingleChoice, QuestionType::Numeric], ['time_limit_minutes' => 20]);

    $response = startAs($student, $test);

    $attempt = Attempt::query()->sole();
    $response->assertRedirect(route('student.attempts.show', $attempt));

    expect($attempt->user_id)->toBe($student->id)
        ->and($attempt->is_preview)->toBeFalse()
        ->and($attempt->status)->toBe(AttemptStatus::InProgress)
        ->and($attempt->attempt_number)->toBe(1)
        ->and($attempt->deadline_at?->toDateTimeString())->toBe('2026-10-23 10:20:00')
        ->and($attempt->max_score)->toBe('2.00')
        ->and($attempt->session_hash)->toHaveLength(64)
        ->and($attempt->ip_address)->not->toBeNull()
        ->and($attempt->questions()->count())->toBe(2)
        ->and(AttemptAnswer::query()->count())->toBe(2)
        ->and($test->fresh()?->locked_at?->toDateTimeString())->toBe('2026-10-23 10:00:00');
});

it('caps the deadline at the closing time and ignores any client time', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-23 10:00:00', 'UTC'));
    $student = ExamFixtures::student();
    $test = ExamFixtures::assignedTest($student, attributes: ['time_limit_minutes' => 60, 'closes_at' => '2026-10-23 10:15:00']);

    $this->actingAs($student)->post(route('student.exams.attempts.store', $test->ulid), [
        'deadline_at' => '2030-01-01 00:00:00',
        'started_at' => '2020-01-01 00:00:00',
    ]);

    expect(Attempt::query()->sole()->deadline_at?->toDateTimeString())->toBe('2026-10-23 10:15:00');
});

it('resumes the attempt in progress instead of starting another', function (): void {
    $student = ExamFixtures::student();
    $test = ExamFixtures::assignedTest($student);

    startAs($student, $test);
    $first = Attempt::query()->sole();
    startAs($student, $test)->assertRedirect(route('student.attempts.show', $first));

    expect(Attempt::query()->count())->toBe(1);
});

it('refuses to start when the rules say no', function (Closure $prepare, int|string $expected): void {
    $student = ExamFixtures::student();
    $test = $prepare($student);

    $response = startAs($student, $test);

    if (is_int($expected)) {
        $response->assertStatus($expected);
    } else {
        $response->assertSessionHasErrors($expected);
    }

    expect(Attempt::query()->real()->inProgress()->where('user_id', $student->id)->exists())->toBeFalse();
})->with([
    'not assigned to the student' => [fn (): Test => ExamFixtures::assignedTest(ExamFixtures::student()), 404],
    'draft' => [fn (User $student): Test => ExamFixtures::assignedTest($student, attributes: ['status' => 'draft']), 404],
    'archived' => [fn (User $student): Test => ExamFixtures::assignedTest($student, attributes: ['status' => 'archived']), 404],
    'not open yet' => [fn (User $student): Test => ExamFixtures::assignedTest($student, attributes: ['opens_at' => now()->addHour()]), 'test'],
    'closed' => [fn (User $student): Test => ExamFixtures::assignedTest($student, attributes: ['closes_at' => now()->subMinute()]), 'test'],
    'attempts used up' => [function (User $student): Test {
        $test = ExamFixtures::assignedTest($student, attributes: ['max_attempts' => 1]);
        Attempt::factory()->for($test)->for($student)->submitted()->create();

        return $test;
    }, 'test'],
]);

it('returns 404 for an unknown ULID, without confirming anything', function (): void {
    $student = ExamFixtures::student();

    startAs($student, (new Test)->forceFill(['ulid' => '01JZZZZZZZZZZZZZZZZZZZZZZZ']))->assertNotFound();
    $this->get(route('student.exams.show', '01JZZZZZZZZZZZZZZZZZZZZZZZ'))->assertNotFound();
});

it('counts previews of the owner as nothing and finishes an expired attempt before starting a new one', function (): void {
    $student = ExamFixtures::student();
    $test = ExamFixtures::assignedTest($student, attributes: ['time_limit_minutes' => 10, 'max_attempts' => 2]);

    startAs($student, $test);
    $this->travel(11)->minutes();
    startAs($student, $test);

    expect(Attempt::query()->where('user_id', $student->id)->orderBy('attempt_number')->pluck('status')->map->value->all())
        ->toBe(['timed_out', 'in_progress']);
});

describe('concurrency', function (): void {
    it('turns the unique-index violation of a parallel start into a resume', function (): void {
        $student = ExamFixtures::student();
        $test = ExamFixtures::assignedTest($student);
        $inserted = false;

        // A "parallel request" wins the race: it commits its attempt right
        // after our in-progress check ran (inside our transaction, but before
        // our INSERT), exactly the window the unique index has to cover.
        DB::listen(function (QueryExecuted $query) use (&$inserted, $test, $student): void {
            if ($inserted || ! str_contains($query->sql, 'from `attempts`') || ! str_contains($query->sql, 'for update')) {
                return;
            }

            $inserted = true;
            DB::table('attempts')->insert([
                'ulid' => strtolower((string) Str::ulid()),
                'test_id' => $test->id,
                'user_id' => $student->id,
                'is_preview' => false,
                'attempt_number' => 99,
                'status' => 'in_progress',
                'grading_status' => 'pending_auto',
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $result = app(StartAttempt::class)->handle($student, $test, new AttemptClient(null, null, null));

        expect($result['resumed'])->toBeTrue()
            ->and($result['attempt']->attempt_number)->toBe(99)
            ->and(Attempt::query()->where('user_id', $student->id)->count())->toBe(1);
    });

    it('lets the database refuse a second in-progress attempt (final guard)', function (): void {
        $student = ExamFixtures::student();
        $test = ExamFixtures::assignedTest($student);
        Attempt::factory()->for($test)->for($student)->inProgress()->create(['attempt_number' => 1]);

        expect(fn () => Attempt::factory()->for($test)->for($student)->inProgress()->create(['attempt_number' => 2]))
            ->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
    });
});

describe('randomness', function (): void {
    it('draws N distinct questions from the pool, every pool question eventually', function (): void {
        $student = ExamFixtures::student();
        $test = ExamFixtures::assignedTest($student, array_fill(0, 6, QuestionType::SingleChoice), ['selection_mode' => 'random_pool', 'random_question_count' => 3]);
        $pool = $test->questions()->pluck('questions.id')->sort()->values()->all();
        $seen = [];

        foreach (range(1, 60) as $run) {
            $ids = array_map(fn (array $item): int => $item['question']->id, app(SnapshotBuilder::class)->build($test, Randomizers::secure()));

            expect($ids)->toHaveCount(3)
                ->and(array_unique($ids))->toHaveCount(3);
            $seen = [...$seen, ...$ids];
        }

        expect(collect($seen)->unique()->sort()->values()->all())->toBe($pool);
    });

    it('draws through StartAttempt as configured', function (): void {
        $student = ExamFixtures::student();
        $test = ExamFixtures::assignedTest($student, array_fill(0, 5, QuestionType::SingleChoice), ['selection_mode' => 'random_pool', 'random_question_count' => 2]);

        startAs($student, $test);

        expect(Attempt::query()->sole()->questions()->count())->toBe(2);
    });
});

it('is rate limited (10 starts per minute)', function (): void {
    $student = ExamFixtures::student();
    $test = ExamFixtures::assignedTest($student);

    foreach (range(1, 10) as $request) {
        startAs($student, $test)->assertRedirect();
    }

    startAs($student, $test)->assertTooManyRequests();
});

it('lets a removed member finish the attempt in progress, but not start a new one', function (): void {
    $student = ExamFixtures::student();
    $test = ExamFixtures::assignedTest($student, attributes: ['max_attempts' => 3]);
    startAs($student, $test);
    $attempt = Attempt::query()->sole();
    $test->groups()->firstOrFail()->members()->detach($student->id);

    $this->get(route('student.attempts.show', $attempt))->assertOk();
    $position = $attempt->questions()->firstOrFail()->position;
    $this->putJson(route('student.attempts.answers.update', [$attempt, $position]), ['answer' => ['selected' => null]])->assertOk();
    $this->post(route('student.attempts.submit', $attempt))->assertRedirect(route('student.attempts.result', $attempt));

    startAs($student, $test)->assertNotFound();
    $this->get(route('student.exams.show', $test->ulid))->assertNotFound();
    expect(Question::query()->count())->toBeGreaterThan(0);
});
