<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Descriptive statistics on exact decimals (no floats). Inputs are decimal
 * strings (e.g. score percents); results are strings with 2 decimals,
 * rounded half-up, or null for an empty input.
 *
 * - mean = Σx / n
 * - median = middle value (mean of the two middle values for even n)
 * - standard deviation = population σ = √(Σ(x − mean)² / n)
 */
final class Statistics
{
    public const int SCALE = 2;

    /** Working precision for intermediate results. */
    private const int WORK = 10;

    /**
     * @param  list<string>  $values
     */
    public static function mean(array $values): ?string
    {
        return $values === [] ? null : self::meanOf($values)->toScale(self::SCALE, RoundingMode::HalfUp)->toString();
    }

    /**
     * @param  list<string>  $values
     */
    public static function median(array $values): ?string
    {
        if ($values === []) {
            return null;
        }

        $sorted = array_map(fn (string $value): BigDecimal => BigDecimal::of($value), $values);
        usort($sorted, fn (BigDecimal $a, BigDecimal $b): int => $a->compareTo($b));
        $count = count($sorted);
        $middle = intdiv($count, 2);

        $median = $count % 2 === 1
            ? $sorted[$middle]
            : $sorted[$middle - 1]->plus($sorted[$middle])->dividedBy(2, self::WORK, RoundingMode::HalfUp);

        return $median->toScale(self::SCALE, RoundingMode::HalfUp)->toString();
    }

    /**
     * Population standard deviation.
     *
     * @param  list<string>  $values
     */
    public static function standardDeviation(array $values): ?string
    {
        if ($values === []) {
            return null;
        }

        $mean = self::meanOf($values);
        $squares = BigDecimal::zero();

        foreach ($values as $value) {
            $difference = BigDecimal::of($value)->minus($mean);
            $squares = $squares->plus($difference->multipliedBy($difference));
        }

        $variance = $squares->dividedBy(count($values), self::WORK, RoundingMode::HalfUp);

        return $variance->sqrt(self::WORK, RoundingMode::HalfUp)->toScale(self::SCALE, RoundingMode::HalfUp)->toString();
    }

    /**
     * Counts of percents in 10 buckets: [0, 10), [10, 20), …, [90, 100]
     * (100 belongs to the last bucket).
     *
     * @param  list<string>  $percents
     * @return list<array{from: int, to: int, count: int}>
     */
    public static function histogram(array $percents): array
    {
        $buckets = array_fill(0, 10, 0);

        foreach ($percents as $percent) {
            $index = min(9, max(0, BigDecimal::of($percent)->dividedBy(10, 0, RoundingMode::Down)->toInt()));
            $buckets[$index]++;
        }

        return array_map(fn (int $count, int $index): array => ['from' => $index * 10, 'to' => $index * 10 + 10, 'count' => $count], $buckets, array_keys($buckets));
    }

    /**
     * Share of $part in $total with 4 decimals (0 when $total is 0).
     */
    public static function share(int $part, int $total): string
    {
        return $total === 0 ? '0.0000' : BigDecimal::of($part)->dividedBy($total, 4, RoundingMode::HalfUp)->toString();
    }

    /**
     * @param  non-empty-list<string>  $values
     */
    private static function meanOf(array $values): BigDecimal
    {
        $sum = BigDecimal::zero();

        foreach ($values as $value) {
            $sum = $sum->plus(BigDecimal::of($value));
        }

        return $sum->dividedBy(count($values), self::WORK, RoundingMode::HalfUp);
    }
}
