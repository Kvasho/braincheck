<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * Grading progress of an attempt.
 */
enum GradingStatus: string implements HasLabel
{
    case PendingAuto = 'pending_auto';
    case PendingManual = 'pending_manual';
    case Graded = 'graded';

    public function label(): string
    {
        return match ($this) {
            self::PendingAuto => __('Awaiting automatic grading'),
            self::PendingManual => __('Awaiting manual grading'),
            self::Graded => __('Graded'),
        };
    }
}
