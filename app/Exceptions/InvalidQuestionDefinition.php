<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * A question `definition` does not match the documented shape for its type
 * (see docs/question-types.md).
 */
final class InvalidQuestionDefinition extends InvalidArgumentException
{
    public static function because(string $reason): self
    {
        return new self("Invalid question definition: {$reason}");
    }
}
