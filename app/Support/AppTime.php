<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Conversion between stored UTC times and the display timezone
 * (braincheck.display_timezone, Asia/Tbilisi) used by schedule pickers.
 */
final class AppTime
{
    /** Format of `<input type="datetime-local">` values. */
    public const string INPUT_FORMAT = 'Y-m-d\TH:i';

    public static function zone(): string
    {
        return (string) config('braincheck.display_timezone', 'Asia/Tbilisi');
    }

    /**
     * "2026-11-01T02:00" in the display timezone → UTC.
     *
     * @throws InvalidArgumentException
     */
    public static function fromLocalInput(string $value): CarbonImmutable
    {
        $local = CarbonImmutable::createFromFormat(self::INPUT_FORMAT, $value, self::zone());

        if (! $local instanceof CarbonImmutable || $local->format(self::INPUT_FORMAT) !== $value) {
            throw new InvalidArgumentException("Invalid local date-time [{$value}].");
        }

        return $local->utc();
    }

    /**
     * UTC → "2026-11-01T02:00" in the display timezone (for the pickers).
     */
    public static function toLocalInput(?DateTimeInterface $time): ?string
    {
        return $time === null ? null : CarbonImmutable::instance($time)->setTimezone(self::zone())->format(self::INPUT_FORMAT);
    }
}
