<?php

declare(strict_types=1);

namespace App\Domain\Attempts;

use App\Enums\AttemptStatus;
use App\Models\Attempt;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * The server is the only time authority. This is the one place that knows
 * the deadline formula and the grace period; no endpoint accepts time
 * values from the client.
 */
final class AttemptTiming
{
    /**
     * deadline = min(started + time limit, closes_at), ignoring whichever part
     * is null; null when the test has neither (untimed, never closes).
     */
    public static function deadline(CarbonImmutable $startedAt, ?int $timeLimitMinutes, ?DateTimeInterface $closesAt): ?CarbonImmutable
    {
        $candidates = array_filter([
            $timeLimitMinutes === null ? null : $startedAt->addMinutes($timeLimitMinutes),
            $closesAt === null ? null : CarbonImmutable::instance($closesAt),
        ]);

        return $candidates === [] ? null : min($candidates);
    }

    /**
     * Seconds after the deadline in which answers and submits are still
     * accepted (network latency), braincheck.attempt_grace_seconds.
     */
    public static function graceSeconds(): int
    {
        return max(0, (int) config('braincheck.attempt_grace_seconds'));
    }

    /**
     * Whether an in-progress attempt is past its deadline plus grace: it must
     * be finished as timed out and accepts nothing more.
     */
    public static function isExpired(Attempt $attempt, ?CarbonImmutable $now = null): bool
    {
        return $attempt->status === AttemptStatus::InProgress
            && $attempt->deadline_at !== null
            && ($now ?? CarbonImmutable::now())->greaterThan($attempt->deadline_at->addSeconds(self::graceSeconds()));
    }
}
