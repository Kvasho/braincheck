<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * How a test picks its questions: a fixed list, or N random questions from the attached pool.
 */
enum QuestionSelectionMode: string implements HasLabel
{
    case Fixed = 'fixed';
    case RandomPool = 'random_pool';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => __('Fixed questions'),
            self::RandomPool => __('Random pool'),
        };
    }
}
