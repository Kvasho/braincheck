<?php

declare(strict_types=1);

namespace App\Domain\Tests;

use App\Domain\Questions\Points;

/**
 * One entry of a test's question list, in order: the question's ULID and an
 * optional points override (> 0).
 */
final readonly class QuestionLink
{
    public function __construct(
        public string $questionUlid,
        public ?Points $pointsOverride = null,
    ) {}
}
