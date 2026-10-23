<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * Why XP was awarded (one transaction per user, reason and source).
 */
enum XpReason: string implements HasLabel
{
    case AttemptPassed = 'attempt_passed';
    case PerfectScore = 'perfect_score';
    case FirstAttemptPass = 'first_attempt_pass';
    case BadgeAwarded = 'badge_awarded';
    case Streak = 'streak';

    public function label(): string
    {
        return match ($this) {
            self::AttemptPassed => __('Passed a test'),
            self::PerfectScore => __('Perfect score'),
            self::FirstAttemptPass => __('Passed on the first attempt'),
            self::BadgeAwarded => __('Badge awarded'),
            self::Streak => __('Daily streak'),
        };
    }
}
