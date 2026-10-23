<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Classical item analysis for one question across the graded attempts of a
 * test. Each response is (the attempt's total score %, the share of this
 * question's points the attempt got, 0…1).
 *
 * - Difficulty p = mean share. For all-or-nothing types that is the share
 *   of attempts with full points; for partial types the mean fraction.
 *   Higher p = easier.
 * - Discrimination D = p(upper group) − p(lower group), with the groups the
 *   top and bottom 27 % of the attempts ranked by total score (at least one
 *   attempt each; null with fewer than 2 attempts). D near 1: strong
 *   students get it right and weak ones don't; D < 0.2 needs review.
 */
final class ItemAnalysis
{
    public const string GROUP_SHARE = '0.27';

    public const string REVIEW_BELOW = '0.2';

    /**
     * @param  list<array{score: string, share: string}>  $responses
     * @return array{difficulty: string|null, discrimination: string|null, needs_review: bool}
     */
    public static function analyse(array $responses): array
    {
        if ($responses === []) {
            return ['difficulty' => null, 'discrimination' => null, 'needs_review' => false];
        }

        $difficulty = self::meanShare($responses);
        $discrimination = null;

        if (count($responses) >= 2) {
            ['upper' => $upper, 'lower' => $lower] = self::groups($responses);
            $discrimination = self::meanShare($upper)->minus(self::meanShare($lower))->toScale(4, RoundingMode::HalfUp);
        }

        return [
            'difficulty' => $difficulty->toScale(4, RoundingMode::HalfUp)->toString(),
            'discrimination' => $discrimination?->toString(),
            'needs_review' => $discrimination !== null && $discrimination->isLessThan(self::REVIEW_BELOW),
        ];
    }

    /**
     * The top and bottom 27 % by total score (ties keep their input order).
     *
     * @template T of array{score: string}
     *
     * @param  list<T>  $responses
     * @return array{upper: list<T>, lower: list<T>}
     */
    public static function groups(array $responses): array
    {
        $size = self::groupSize(count($responses));
        $ranked = $responses;
        usort($ranked, fn (array $a, array $b): int => BigDecimal::of($b['score'])->compareTo(BigDecimal::of($a['score'])));

        return [
            'upper' => array_slice($ranked, 0, $size),
            'lower' => array_slice($ranked, -$size),
        ];
    }

    public static function groupSize(int $count): int
    {
        return max(1, BigDecimal::of($count)->multipliedBy(self::GROUP_SHARE)->toScale(0, RoundingMode::HalfUp)->toInt());
    }

    /**
     * @param  list<array{share: string}>  $responses
     */
    private static function meanShare(array $responses): BigDecimal
    {
        $sum = BigDecimal::zero();

        foreach ($responses as $response) {
            $sum = $sum->plus(BigDecimal::of($response['share']));
        }

        return $sum->dividedBy(count($responses), 10, RoundingMode::HalfUp);
    }
}
