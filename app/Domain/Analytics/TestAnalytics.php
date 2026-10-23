<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

use App\Enums\GradingStatus;
use App\Models\Attempt;
use App\Models\Group;
use App\Models\Test;
use App\Models\TestQuestionStat;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Teacher analytics of one test: real, fully graded attempts only, optionally
 * of one assigned group's members and a submission date range. Numbers are
 * cached per test (AnalyticsCache) and invalidated on grading events.
 */
final class TestAnalytics
{
    /**
     * @param  array{group: Group|null, from: CarbonImmutable|null, to: CarbonImmutable|null}  $filters
     * @return Builder<Attempt>
     */
    public static function graded(Test $test, array $filters): Builder
    {
        return Attempt::query()->real()
            ->where('test_id', $test->id)
            ->where('grading_status', GradingStatus::Graded->value)
            ->when($filters['group'] !== null, fn (Builder $query) => $query->whereIn('user_id', $filters['group']?->members()->select('users.id')))
            ->when($filters['from'] !== null, fn (Builder $query) => $query->where('submitted_at', '>=', $filters['from']))
            ->when($filters['to'] !== null, fn (Builder $query) => $query->where('submitted_at', '<', $filters['to']));
    }

    /**
     * @param  array{group: Group|null, from: CarbonImmutable|null, to: CarbonImmutable|null}  $filters
     * @return array{attempt_count: int, students: int, average: string|null, median: string|null, standard_deviation: string|null, pass_rate: string|null, average_duration_seconds: int|null, distribution: list<array{from: int, to: int, count: int}>}
     */
    public static function overview(Test $test, array $filters): array
    {
        return AnalyticsCache::remember($test->id, 'overview', self::cacheFilters($filters), function () use ($test, $filters): array {
            $rows = self::graded($test, $filters)->get(['id', 'user_id', 'score_percent', 'passed', 'started_at', 'submitted_at']);
            $percents = array_values($rows->map(fn (Attempt $attempt): string => (string) $attempt->score_percent)->all());
            $durations = $rows->map(fn (Attempt $attempt): int => $attempt->submitted_at === null ? 0 : (int) $attempt->started_at->diffInSeconds($attempt->submitted_at, true));

            return [
                'attempt_count' => $rows->count(),
                'students' => $rows->pluck('user_id')->unique()->count(),
                'average' => Statistics::mean($percents),
                'median' => Statistics::median($percents),
                'standard_deviation' => Statistics::standardDeviation($percents),
                'pass_rate' => $rows->isEmpty() ? null : Statistics::share($rows->where('passed', true)->count(), $rows->count()),
                'average_duration_seconds' => $rows->isEmpty() ? null : (int) round($durations->avg() ?? 0),
                'distribution' => Statistics::histogram($percents),
            ];
        });
    }

    /**
     * Average % per group the test is assigned to (its members' graded attempts).
     *
     * @return list<array{ulid: string, name: string, responses: int, average: string|null}>
     */
    public static function groups(Test $test): array
    {
        return AnalyticsCache::remember($test->id, 'groups', [], function () use ($test): array {
            return array_values($test->groups()->orderBy('name')->get(['groups.id', 'groups.ulid', 'groups.name'])->map(function (Group $group) use ($test): array {
                $percents = array_values(self::graded($test, ['group' => $group, 'from' => null, 'to' => null])
                    ->pluck('score_percent')
                    ->map(fn (mixed $percent): string => (string) $percent)
                    ->all());

                return ['ulid' => $group->ulid, 'name' => $group->name, 'responses' => count($percents), 'average' => Statistics::mean($percents)];
            })->all());
        });
    }

    /**
     * The precomputed per-question rows (RecomputeTestQuestionStats).
     *
     * @return list<array<string, mixed>>
     */
    public static function questions(Test $test): array
    {
        return array_values(TestQuestionStat::query()
            ->where('test_id', $test->id)
            ->with('question:id,ulid')
            ->orderBy('id')
            ->get()
            ->map(fn (TestQuestionStat $stat): array => [
                'question' => $stat->question->ulid,
                'type' => $stat->type,
                'excerpt' => $stat->excerpt,
                'responses' => $stat->responses,
                'difficulty' => $stat->difficulty,
                'discrimination' => $stat->discrimination,
                'average_points' => $stat->average_points,
                'manual_grades' => $stat->manual_grades,
                'needs_review' => $stat->needs_review,
                'distractors' => $stat->distractors,
                'computed_at' => $stat->computed_at->toIso8601String(),
            ])
            ->all());
    }

    /**
     * @param  array{group: Group|null, from: CarbonImmutable|null, to: CarbonImmutable|null}  $filters
     * @return array<string, string|null>
     */
    private static function cacheFilters(array $filters): array
    {
        return [
            'group' => $filters['group']?->ulid,
            'from' => $filters['from']?->toIso8601String(),
            'to' => $filters['to']?->toIso8601String(),
        ];
    }
}
