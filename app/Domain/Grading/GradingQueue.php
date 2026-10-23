<?php

declare(strict_types=1);

namespace App\Domain\Grading;

use App\Enums\AttemptStatus;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\AttemptQuestion;
use App\Models\Group;
use App\Models\Test;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Answers waiting for manual grading in the teacher's own tests: real,
 * finished attempts only; optionally one test or one of the teacher's
 * groups (its members' attempts on tests assigned to it).
 */
final class GradingQueue
{
    /**
     * @return Builder<AttemptAnswer>
     */
    public static function pending(User $teacher, ?Test $test = null, ?Group $group = null): Builder
    {
        $attempts = Attempt::query()->real()
            ->where('status', '!=', AttemptStatus::InProgress->value)
            ->whereIn('test_id', $test === null
                ? Test::query()->withTrashed()->where('owner_id', $teacher->id)->select('id')
                : [$test->id])
            ->when($group !== null, fn (Builder $query) => $query
                ->whereIn('user_id', $group?->members()->select('users.id'))
                ->whereIn('test_id', $group?->tests()->select('tests.id')))
            ->select('id');

        return AttemptAnswer::query()
            ->where('needs_manual_grading', true)
            ->whereNull('graded_at')
            ->whereIn('attempt_question_id', AttemptQuestion::query()->whereIn('attempt_id', $attempts)->select('id'));
    }

    /**
     * Order by the attempt's submission time (oldest first by default).
     *
     * @param  'asc'|'desc'  $direction
     */
    public static function orderBySubmission(QueryBuilder $query, string $direction): void
    {
        $query->orderBy(
            AttemptQuestion::query()
                ->join('attempts as submitted', 'submitted.id', '=', 'attempt_questions.attempt_id')
                ->whereColumn('attempt_questions.id', 'attempt_answers.attempt_question_id')
                ->select('submitted.submitted_at')
                ->toBase(),
            $direction,
        );
    }

    /**
     * Pending answers per test of the teacher.
     *
     * @return list<array{ulid: string, title: string, pending: int}>
     */
    public static function countsPerTest(User $teacher): array
    {
        $counts = self::pending($teacher)
            ->with('attemptQuestion:id,attempt_id', 'attemptQuestion.attempt:id,test_id')
            ->get(['id', 'attempt_question_id'])
            ->countBy(fn (AttemptAnswer $answer): int => $answer->attemptQuestion->attempt->test_id);

        return array_values(Test::query()->withTrashed()
            ->whereIn('id', $counts->keys())
            ->orderBy('title')
            ->get(['id', 'ulid', 'title'])
            ->map(fn (Test $test): array => ['ulid' => $test->ulid, 'title' => $test->title, 'pending' => (int) $counts->get($test->id, 0)])
            ->all());
    }

    /**
     * The next pending answer after $current (oldest submission first), for "save & next".
     */
    public static function next(User $teacher, ?AttemptQuestion $current = null): ?AttemptAnswer
    {
        $query = self::pending($teacher)->with('attemptQuestion.attempt');

        if ($current !== null) {
            $query->where('attempt_question_id', '!=', $current->id);
        }

        self::orderBySubmission($query->getQuery(), 'asc');

        return $query->orderBy('id')->first();
    }
}
