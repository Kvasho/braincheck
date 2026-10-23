<?php

declare(strict_types=1);

namespace App\Domain\Grading;

use App\Models\Attempt;

/**
 * Anonymous grading: "Student #N", where N is stable per test (students
 * numbered by their first real attempt on it). It hides names only from
 * the screen; the grader can reveal one identity at a time (audited).
 */
final class AnonymousLabels
{
    public const string SESSION_KEY = 'grading.anonymous';

    /**
     * @param  list<int>  $testIds
     * @return array<int, array<int, int>> test id => user id => number
     */
    public static function forTests(array $testIds): array
    {
        $numbers = [];

        Attempt::query()->real()
            ->whereIn('test_id', $testIds)
            ->selectRaw('test_id, user_id, MIN(id) AS first_attempt')
            ->groupBy('test_id', 'user_id')
            ->orderBy('first_attempt')
            ->toBase()
            ->get()
            ->each(function (object $row) use (&$numbers): void {
                /** @var object{test_id: int|string, user_id: int|string} $row */
                $test = (int) $row->test_id;
                $numbers[$test][(int) $row->user_id] = count($numbers[$test] ?? []) + 1;
            });

        return $numbers;
    }
}
