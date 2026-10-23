<?php

declare(strict_types=1);

namespace App\Actions\Attempts;

use App\Domain\Attempts\AttemptClient;
use App\Domain\Attempts\AttemptTiming;
use App\Domain\Attempts\SnapshotBuilder;
use App\Domain\Questions\Points;
use App\Domain\Shared\Randomizers;
use App\Domain\Tests\AvailableTests;
use App\Enums\AttemptStatus;
use App\Enums\GradingStatus;
use App\Enums\TestStatus;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\AttemptQuestion;
use App\Models\Test;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Starts (or resumes) a student's attempt. Everything happens in one
 * transaction with the test row locked (`lockForUpdate` serialises starts per
 * test), and every rule is checked again inside the lock:
 *
 * - visible to the student (AvailableTests), published, window open
 *   (opens_at ≤ now < closes_at), real attempts used < max_attempts;
 * - an attempt already in progress is resumed instead (an expired one is
 *   finished first);
 * - questions, order and option order come from SnapshotBuilder with a CSPRNG;
 * - the deadline is computed here only (AttemptTiming::deadline), never from input;
 * - one empty answer row per question; IP, user agent and session hash stored;
 * - the test is locked (`locked_at`) on its first real attempt.
 *
 * The database is the final guard: `active_lock` (one in-progress attempt per
 * student and test) and `attempt_number` are unique. A unique violation from
 * a parallel request is turned into "resume the existing attempt".
 */
final class StartAttempt
{
    public function __construct(
        private readonly SnapshotBuilder $snapshots,
        private readonly FinishExpiredAttempt $finishExpired,
    ) {}

    /**
     * @return array{attempt: Attempt, resumed: bool}
     *
     * @throws ModelNotFoundException when the test is not visible to the student (404)
     * @throws ValidationException when the test can't be started now
     */
    public function handle(User $student, Test $test, AttemptClient $client): array
    {
        return DB::transaction(function () use ($student, $test, $client): array {
            $locked = Test::query()->whereKey($test->id)->lockForUpdate()->first();

            if ($locked === null || ! AvailableTests::forStudent($student)->whereKey($locked->id)->exists()) {
                throw (new ModelNotFoundException)->setModel(Test::class);
            }

            $existing = $this->inProgress($student, $locked);

            if ($existing !== null && ! $this->finishExpired->handle($existing)) {
                return ['attempt' => $existing, 'resumed' => true];
            }

            $now = CarbonImmutable::now()->startOfSecond();
            $this->ensureStartable($student, $locked, $now);

            try {
                $attempt = DB::transaction(fn (): Attempt => $this->create($student, $locked, $client, $now));
            } catch (UniqueConstraintViolationException $exception) {
                // A parallel request created the attempt first: resume it.
                $existing = $this->inProgress($student, $locked);

                if ($existing === null) {
                    throw $exception;
                }

                return ['attempt' => $existing, 'resumed' => true];
            }

            if ($locked->locked_at === null) {
                $locked->forceFill(['locked_at' => $now])->save();
            }

            return ['attempt' => $attempt, 'resumed' => false];
        });
    }

    /**
     * A locking read: sees rows committed by parallel transactions.
     */
    private function inProgress(User $student, Test $test): ?Attempt
    {
        return Attempt::query()->real()->inProgress()
            ->where('test_id', $test->id)
            ->where('user_id', $student->id)
            ->lockForUpdate()
            ->first();
    }

    /**
     * @throws ValidationException
     */
    private function ensureStartable(User $student, Test $test, CarbonImmutable $now): void
    {
        $refuse = fn (string $message) => throw ValidationException::withMessages(['test' => $message]);

        if ($test->status !== TestStatus::Published) {
            $refuse(__('This test is not available.'));
        }

        if ($test->opens_at !== null && $now->lessThan($test->opens_at)) {
            $refuse(__('This test has not opened yet.'));
        }

        if ($test->closes_at !== null && $now->greaterThanOrEqualTo($test->closes_at)) {
            $refuse(__('This test is closed.'));
        }

        $used = Attempt::query()->real()->where('test_id', $test->id)->where('user_id', $student->id)->count();

        if ($test->max_attempts !== null && $used >= $test->max_attempts) {
            $refuse(__('You have used all your attempts for this test.'));
        }
    }

    /**
     * @throws ValidationException
     */
    private function create(User $student, Test $test, AttemptClient $client, CarbonImmutable $now): Attempt
    {
        $snapshots = $this->snapshots->build($test, Randomizers::secure());

        if ($snapshots === []) {
            throw ValidationException::withMessages(['test' => __('This test is not available.')]);
        }

        $maxScore = array_reduce($snapshots, fn (Points $sum, array $item): Points => $sum->plus($item['points']), Points::zero());

        $attempt = new Attempt;
        $attempt->forceFill([
            'is_preview' => false,
            'attempt_number' => Attempt::nextNumber($test, $student),
            'status' => AttemptStatus::InProgress,
            'grading_status' => GradingStatus::PendingAuto,
            'started_at' => $now,
            'deadline_at' => AttemptTiming::deadline($now, $test->time_limit_minutes, $test->closes_at),
            'max_score' => $maxScore->toString(),
            'ip_address' => $client->ip,
            'user_agent' => $client->userAgent,
            'session_hash' => $client->sessionHash,
            'last_seen_at' => $now,
        ]);
        $attempt->test()->associate($test);
        $attempt->user()->associate($student);
        $attempt->save();

        foreach ($snapshots as $item) {
            $row = new AttemptQuestion;
            $row->forceFill([
                'question_id' => $item['question']->id,
                'position' => $item['position'],
                'points' => $item['points']->toString(),
                'snapshot' => $item['snapshot'],
            ]);
            $row->attempt()->associate($attempt);
            $row->save();

            $answer = new AttemptAnswer;
            $answer->attemptQuestion()->associate($row);
            $answer->save();
        }

        return $attempt;
    }
}
