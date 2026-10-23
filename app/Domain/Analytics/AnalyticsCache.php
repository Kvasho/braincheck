<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Cached analytics numbers per test. Each test has a version number in the
 * cache; every key includes it, so bumping the version (on AttemptGraded /
 * AttemptRegraded) invalidates everything of that test at once.
 */
final class AnalyticsCache
{
    public const int TTL_SECONDS = 3600;

    /**
     * @template T
     *
     * @param  array<string, mixed>  $filters
     * @param  Closure(): T  $compute
     * @return T
     */
    public static function remember(int $testId, string $part, array $filters, Closure $compute): mixed
    {
        $key = sprintf('analytics:test:%d:v%d:%s:%s', $testId, self::version($testId), $part, hash('xxh128', (string) json_encode($filters)));

        return Cache::remember($key, self::TTL_SECONDS, $compute);
    }

    public static function invalidate(int $testId): void
    {
        $key = self::versionKey($testId);

        if (Cache::get($key) === null) {
            Cache::forever($key, 1);
        }

        Cache::increment($key);
    }

    public static function version(int $testId): int
    {
        return (int) Cache::get(self::versionKey($testId), 1);
    }

    private static function versionKey(int $testId): string
    {
        return "analytics:test:{$testId}:version";
    }
}
