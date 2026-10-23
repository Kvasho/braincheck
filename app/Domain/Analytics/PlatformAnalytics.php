<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

use App\Enums\GradingStatus;
use App\Models\Attempt;
use App\Models\Test;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Platform-wide analytics for admins (real attempts only), cached for a
 * few minutes: totals, average pass rate, most active teachers and tests
 * (last 30 days) and attempts per day.
 */
final class PlatformAnalytics
{
    public const string CACHE_KEY = 'analytics.platform';

    public const int CACHE_SECONDS = 300;

    public const int TOP = 5;

    public const int DAYS = 30;

    /**
     * @return array<string, mixed>
     */
    public static function get(): array
    {
        /** @var array<string, mixed> */
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn (): array => self::compute());
    }

    /**
     * @return array<string, mixed>
     */
    private static function compute(): array
    {
        $now = CarbonImmutable::now();
        $since = $now->subDays(self::DAYS - 1)->startOfDay();
        $graded = Attempt::query()->real()->where('grading_status', GradingStatus::Graded->value);
        $gradedCount = (clone $graded)->count();

        $recent = Attempt::query()->real()
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '>=', $since)
            ->with('test:id,ulid,title,owner_id')
            ->get(['id', 'test_id', 'submitted_at']);

        $perDay = $recent->countBy(fn (Attempt $attempt): string => (string) $attempt->submitted_at?->toDateString());
        $days = [];

        for ($day = $since; $day->lessThanOrEqualTo($now); $day = $day->addDay()) {
            $days[] = ['date' => $day->toDateString(), 'count' => (int) $perDay->get($day->toDateString(), 0)];
        }

        $byTest = $recent->countBy('test_id')->sortDesc();
        $byTeacher = $recent->countBy(fn (Attempt $attempt): int => $attempt->test->owner_id)->sortDesc();
        $teachers = User::query()->whereIn('id', $byTeacher->keys()->take(self::TOP))->pluck('name', 'id');
        $tests = $recent->pluck('test')->keyBy('id');

        return [
            'tests_total' => Test::query()->count(),
            'submissions_total' => Attempt::query()->real()->whereNotNull('submitted_at')->count(),
            'pass_rate' => $gradedCount === 0 ? null : Statistics::share((clone $graded)->where('passed', true)->count(), $gradedCount),
            'top_teachers' => array_values($byTeacher->take(self::TOP)->map(fn (int $count, int $id): array => ['name' => (string) ($teachers[$id] ?? '—'), 'count' => $count])->all()),
            'top_tests' => array_values($byTest->take(self::TOP)->map(fn (int $count, int $id): array => [
                'ulid' => (string) $tests->get($id)?->ulid,
                'title' => (string) $tests->get($id)?->title,
                'count' => $count,
            ])->all()),
            'per_day' => $days,
        ];
    }
}
