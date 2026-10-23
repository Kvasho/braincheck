<?php

declare(strict_types=1);

namespace App\Domain\Groups;

use App\Domain\Shared\Randomizers;
use Random\Randomizer;

/**
 * Group invite codes: 10 characters from a CSPRNG over an unambiguous
 * alphabet (no 0/O, 1/I/L), so ~ 1.2 × 10^15 possible codes. Together with
 * the join rate limits that makes guessing a code impractical.
 */
final class InviteCode
{
    /** Uppercase letters and digits without 0, O, 1, I and L. */
    public const string ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const int LENGTH = 10;

    public static function generate(?Randomizer $randomizer = null): string
    {
        return ($randomizer ?? Randomizers::secure())->getBytesFromString(self::ALPHABET, self::LENGTH);
    }

    /**
     * What a user typed, as stored: trimmed, uppercase, without spaces or
     * dashes (codes may be shown in groups like "ABCDE-FGHJK").
     */
    public static function normalize(string $input): string
    {
        return strtoupper((string) preg_replace('/[\s\-]+/u', '', trim($input)));
    }

    /**
     * A query-string value (join link) reduced to what could be part of a code:
     * A–Z and 0–9, at most 32 characters. Nothing else is echoed into a form.
     */
    public static function fromQuery(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $code = substr((string) preg_replace('/[^A-Z0-9]/', '', self::normalize($value)), 0, 32);

        return $code === '' ? null : $code;
    }

    /**
     * Whether $code could be an invite code at all (cheap check before any query).
     */
    public static function isWellFormed(string $code): bool
    {
        return strlen($code) === self::LENGTH
            && strspn($code, self::ALPHABET) === self::LENGTH;
    }
}
