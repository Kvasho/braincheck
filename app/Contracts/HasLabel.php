<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * An enum with a translated, human-readable label (ka + en).
 */
interface HasLabel
{
    public function label(): string;
}
