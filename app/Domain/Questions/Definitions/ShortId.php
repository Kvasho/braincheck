<?php

declare(strict_types=1);

namespace App\Domain\Questions\Definitions;

use App\Domain\Shared\Randomizers;

/**
 * Random IDs for options and items: 8 characters from an unambiguous alphabet
 * (no 0/O, 1/I), generated with a CSPRNG. Never indexes or sequential numbers,
 * so an option's id or position reveals nothing about the answer.
 */
final class ShortId
{
    public const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const LENGTH = 8;

    public static function generate(): string
    {
        return Randomizers::secure()->getBytesFromString(self::ALPHABET, self::LENGTH);
    }

    public static function isValid(mixed $id): bool
    {
        return is_string($id) && preg_match('/^['.self::ALPHABET.']{'.self::LENGTH.'}$/', $id) === 1;
    }
}
