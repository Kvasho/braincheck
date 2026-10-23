<?php

declare(strict_types=1);

use App\Enums\Badge;
use App\Enums\XpReason;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use App\Models\Question;
use App\Models\TeacherAccessRequest;
use App\Models\Test;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * The database enforces the rules it can. Every case inserts directly (bypassing
 * models and validation) and must be rejected by MySQL itself.
 */

/**
 * @param  array<string, mixed>  $overrides
 */
function insertAttempt(Test $test, User $user, array $overrides = []): void
{
    DB::table('attempts')->insert($overrides + [
        'ulid' => strtolower((string) Str::ulid()),
        'test_id' => $test->id,
        'user_id' => $user->id,
        'attempt_number' => 1,
        'status' => 'in_progress',
        'grading_status' => 'pending_auto',
        'started_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function insertTest(User $owner, array $overrides = []): void
{
    DB::table('tests')->insert($overrides + [
        'ulid' => strtolower((string) Str::ulid()),
        'owner_id' => $owner->id,
        'title' => 'Constraint probe',
        'pass_percent' => 60,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

describe('required constraints', function (): void {
    it('allows only one in-progress attempt per user and test', function (): void {
        $test = Test::factory()->published()->create();
        $student = User::factory()->student()->create();

        insertAttempt($test, $student);

        expect(fn () => insertAttempt($test, $student, ['attempt_number' => 2]))->toThrow(QueryException::class);

        // Finished attempts don't hold the lock.
        insertAttempt($test, $student, ['attempt_number' => 3, 'status' => 'submitted', 'submitted_at' => now()]);
        insertAttempt($test, $student, ['attempt_number' => 4, 'status' => 'timed_out', 'submitted_at' => now()]);

        expect(DB::table('attempts')->where('test_id', $test->id)->count())->toBe(3);
    });

    it('rejects a duplicate attempt number', function (): void {
        $test = Test::factory()->published()->create();
        $student = User::factory()->student()->create();

        insertAttempt($test, $student, ['status' => 'submitted', 'submitted_at' => now()]);

        expect(fn () => insertAttempt($test, $student, ['status' => 'submitted', 'submitted_at' => now()]))
            ->toThrow(QueryException::class);
    });

    it('rejects a duplicate XP award for the same source', function (): void {
        $attempt = Attempt::factory()->create();
        $row = [
            'user_id' => $attempt->user_id,
            'amount' => 10,
            'reason' => XpReason::AttemptPassed->value,
            'source_type' => 'attempt',
            'source_id' => $attempt->id,
            'created_at' => now(),
        ];

        DB::table('xp_transactions')->insert($row);

        expect(fn () => DB::table('xp_transactions')->insert($row))->toThrow(QueryException::class);
    });

    it('rejects a duplicate badge', function (): void {
        $student = User::factory()->student()->create();
        $row = ['user_id' => $student->id, 'badge' => Badge::FirstTest->value, 'awarded_at' => now()];

        DB::table('user_badges')->insert($row);

        expect(fn () => DB::table('user_badges')->insert($row))->toThrow(QueryException::class);
    });

    it('rejects a second certificate for the same attempt', function (): void {
        $attempt = Attempt::factory()->graded(passed: true)->create();
        $row = fn (): array => [
            'ulid' => strtolower((string) Str::ulid()),
            'attempt_id' => $attempt->id,
            'user_id' => $attempt->user_id,
            'test_id' => $attempt->test_id,
            'verification_code' => bin2hex(random_bytes(16)),
            'holder_name' => 'Holder',
            'test_title' => 'Title',
            'score_percent' => 100,
            'issued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('certificates')->insert($row());

        expect(fn () => DB::table('certificates')->insert($row()))->toThrow(QueryException::class);
    });

    it('rejects pass_percent above 100', function (): void {
        expect(fn () => insertTest(User::factory()->teacher()->create(), ['pass_percent' => 101]))
            ->toThrow(QueryException::class);
    });

    it('rejects closes_at before (or equal to) opens_at', function (string $closesAt): void {
        expect(fn () => insertTest(User::factory()->teacher()->create(), [
            'opens_at' => '2030-01-10 10:00:00',
            'closes_at' => $closesAt,
        ]))->toThrow(QueryException::class);
    })->with(['2030-01-09 10:00:00', '2030-01-10 10:00:00']);
});

describe('additional constraints', function (): void {
    it('rejects invalid rows', function (Closure $insert): void {
        expect($insert)->toThrow(QueryException::class);
    })->with([
        'category name without en' => fn () => DB::table('categories')->insert(['ulid' => strtolower((string) Str::ulid()), 'name' => '{"ka":"მათ"}', 'slug' => 'x', 'created_at' => now(), 'updated_at' => now()]),
        'question with 0 points' => fn () => DB::table('questions')->insert(['ulid' => strtolower((string) Str::ulid()), 'owner_id' => User::factory()->teacher()->create()->id, 'type' => 'true_false', 'difficulty' => 'easy', 'body' => 'b', 'definition' => '{"correct":true}', 'points' => 0, 'created_at' => now(), 'updated_at' => now()]),
        'question with unknown type' => fn () => DB::table('questions')->insert(['ulid' => strtolower((string) Str::ulid()), 'owner_id' => User::factory()->teacher()->create()->id, 'type' => 'essay', 'difficulty' => 'easy', 'body' => 'b', 'definition' => '{}', 'created_at' => now(), 'updated_at' => now()]),
        'time limit over 600' => fn () => insertTest(User::factory()->teacher()->create(), ['time_limit_minutes' => 601]),
        'random pool without count' => fn () => insertTest(User::factory()->teacher()->create(), ['selection_mode' => 'random_pool']),
        'score above max score' => fn () => insertAttempt(Test::factory()->create(), User::factory()->student()->create(), ['status' => 'submitted', 'submitted_at' => now(), 'score' => 11, 'max_score' => 10]),
        'submitted without submitted_at' => fn () => insertAttempt(Test::factory()->create(), User::factory()->student()->create(), ['status' => 'submitted']),
        'xp amount of zero' => fn () => DB::table('xp_transactions')->insert(['user_id' => User::factory()->create()->id, 'amount' => 0, 'reason' => 'streak', 'created_at' => now()]),
        'unknown badge' => fn () => DB::table('user_badges')->insert(['user_id' => User::factory()->create()->id, 'badge' => 'cheater', 'awarded_at' => now()]),
    ]);

    it('allows only one pending teacher request per user', function (): void {
        $request = TeacherAccessRequest::factory()->create();

        expect(fn () => DB::table('teacher_access_requests')->insert([
            'user_id' => $request->user_id, 'message' => 'again', 'created_at' => now(), 'updated_at' => now(),
        ]))->toThrow(QueryException::class);

        // Reviewed requests don't count.
        TeacherAccessRequest::factory()->rejected()->for(User::query()->findOrFail($request->user_id))->create();
        expect(TeacherAccessRequest::query()->where('user_id', $request->user_id)->count())->toBe(2);
    });
});

describe('foreign keys', function (): void {
    it('refuses to hard-delete a question used in a test', function (): void {
        $test = Test::factory()->withQuestions(1)->create();
        $question = $test->questions()->firstOrFail();

        expect(fn () => $question->forceDelete())->toThrow(QueryException::class);
        expect(Question::withTrashed()->whereKey($question->id)->exists())->toBeTrue();
    });

    it('cascades an attempt deletion to its questions, answers and events', function (): void {
        $attempt = Attempt::factory()->submitted()->create();
        App\Models\AttemptEvent::factory()->count(2)->for($attempt)->create();
        $questionIds = $attempt->questions()->pluck('id');

        expect($questionIds)->not->toBeEmpty();

        DB::table('attempts')->where('id', $attempt->id)->delete();

        expect(AttemptQuestion::query()->where('attempt_id', $attempt->id)->count())->toBe(0)
            ->and(DB::table('attempt_answers')->whereIn('attempt_question_id', $questionIds)->count())->toBe(0)
            ->and(DB::table('attempt_events')->where('attempt_id', $attempt->id)->count())->toBe(0);
    });

    it('sets reviewed_by to null when the reviewer is deleted', function (): void {
        $request = TeacherAccessRequest::factory()->approved()->create();
        $reviewerId = $request->reviewed_by;

        DB::table('users')->where('id', $reviewerId)->delete();

        expect($request->fresh()?->reviewed_by)->toBeNull()
            ->and($request->fresh()?->reviewed_at)->not->toBeNull();
    });

    it('refuses to delete a student who has attempts', function (): void {
        $attempt = Attempt::factory()->create();

        expect(fn () => DB::table('users')->where('id', $attempt->user_id)->delete())->toThrow(QueryException::class);
    });
});
