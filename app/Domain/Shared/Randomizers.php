<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use Random\Engine\Secure;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * The only places randomizers are created. Legacy functions (rand, mt_rand,
 * shuffle, array_rand, str_shuffle, uniqid) are banned in app/ by an arch test.
 */
final class Randomizers
{
    /**
     * CSPRNG: the default for everything (option shuffling, IDs, codes).
     */
    public static function secure(): Randomizer
    {
        return new Randomizer(new Secure);
    }

    /**
     * Reproducible sequence for the same seed. Use only where reproducibility is
     * explicitly required (e.g. an attempt that must show the same shuffled order
     * on every reload) and the seed itself is secret, never for anything an
     * attacker could profit from predicting.
     */
    public static function seeded(string $seed): Randomizer
    {
        return new Randomizer(new Xoshiro256StarStar(hash('sha256', $seed, true)));
    }
}
