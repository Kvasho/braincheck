<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * How hard a question is.
 */
enum Difficulty: string implements HasLabel
{
    case Easy = 'easy';
    case Medium = 'medium';
    case Hard = 'hard';

    public function label(): string
    {
        return match ($this) {
            self::Easy => __('Easy'),
            self::Medium => __('Medium'),
            self::Hard => __('Hard'),
        };
    }
}
