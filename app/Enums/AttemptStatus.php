<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * State of an attempt. `timed_out` means the server finished it when the deadline passed.
 */
enum AttemptStatus: string implements HasLabel
{
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case TimedOut = 'timed_out';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => __('In progress'),
            self::Submitted => __('Submitted'),
            self::TimedOut => __('Timed out'),
        };
    }
}
