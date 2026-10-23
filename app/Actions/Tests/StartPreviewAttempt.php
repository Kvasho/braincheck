<?php

declare(strict_types=1);

namespace App\Actions\Tests;

use App\Domain\Attempts\SnapshotBuilder;
use App\Domain\Questions\Points;
use App\Domain\Shared\Randomizers;
use App\Enums\AttemptStatus;
use App\Enums\GradingStatus;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use App\Models\Test;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The owner's "preview as student": an `is_preview` attempt built with the
 * same SnapshotBuilder real attempts use. Previews never lock the test, never
 * count anywhere (Attempt::real()) and are pruned after 24 hours. A previous
 * unfinished preview of the same test is discarded first.
 */
final class StartPreviewAttempt
{
    public function __construct(private readonly SnapshotBuilder $snapshots) {}

    /**
     * @throws ValidationException
     */
    public function handle(Test $test, User $teacher): Attempt
    {
        return DB::transaction(function () use ($test, $teacher): Attempt {
            Attempt::query()->preview()
                ->where('test_id', $test->id)
                ->where('user_id', $teacher->id)
                ->inProgress()
                ->get()
                ->each(fn (Attempt $attempt) => $attempt->delete());

            $snapshots = $this->snapshots->build($test, Randomizers::secure());

            if ($snapshots === []) {
                throw ValidationException::withMessages(['test' => __('Add at least one question before previewing.')]);
            }

            $maxScore = array_reduce($snapshots, fn (Points $sum, array $item): Points => $sum->plus($item['points']), Points::zero());
            $now = now()->toImmutable();

            $attempt = new Attempt;
            $attempt->forceFill([
                'is_preview' => true,
                'attempt_number' => Attempt::nextNumber($test, $teacher),
                'status' => AttemptStatus::InProgress,
                'grading_status' => GradingStatus::PendingAuto,
                'started_at' => $now,
                'deadline_at' => $test->time_limit_minutes === null ? null : $now->addMinutes($test->time_limit_minutes),
                'max_score' => $maxScore->toString(),
            ]);
            $attempt->test()->associate($test);
            $attempt->user()->associate($teacher);
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
            }

            return $attempt;
        });
    }
}
