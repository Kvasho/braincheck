<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

final class InvalidRichText extends InvalidArgumentException
{
    public static function because(string $reason): self
    {
        return new self("Invalid rich text: {$reason}");
    }
}
