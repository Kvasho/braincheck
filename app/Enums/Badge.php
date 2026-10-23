<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * Achievement badges. Each badge can be awarded to a user at most once
 * (enforced by the `user_badges` primary key). Award logic: Stage 11.
 */
enum Badge: string implements HasLabel
{
    case FirstTest = 'first_test';
    case PerfectScore = 'perfect_score';
    case TenTests = 'ten_tests';
    case SpeedRunner = 'speed_runner';
    case Comeback = 'comeback';
    case Streak7 = 'streak_7';
    case Top10Group = 'top_10_group';
    case AllTypes = 'all_types';

    /**
     * Translated badge name.
     */
    public function label(): string
    {
        return match ($this) {
            self::FirstTest => __('First steps'),
            self::PerfectScore => __('Flawless'),
            self::TenTests => __('Dedicated learner'),
            self::SpeedRunner => __('Speed runner'),
            self::Comeback => __('Comeback'),
            self::Streak7 => __('Week streak'),
            self::Top10Group => __('Top ten'),
            self::AllTypes => __('All-rounder'),
        };
    }

    /**
     * Translated description of how the badge is earned.
     */
    public function description(): string
    {
        return match ($this) {
            self::FirstTest => __('Finish your first test.'),
            self::PerfectScore => __('Score 100% on a test.'),
            self::TenTests => __('Finish ten tests.'),
            self::SpeedRunner => __('Pass a timed test using less than half of the time.'),
            self::Comeback => __('Pass a test you failed before.'),
            self::Streak7 => __('Finish a test on seven days in a row.'),
            self::Top10Group => __("Reach your group's top ten."),
            self::AllTypes => __('Answer every question type correctly.'),
        };
    }

    /**
     * Lucide icon name (kebab-case), rendered by the frontend.
     */
    public function icon(): string
    {
        return match ($this) {
            self::FirstTest => 'footprints',
            self::PerfectScore => 'sparkles',
            self::TenTests => 'library',
            self::SpeedRunner => 'zap',
            self::Comeback => 'rotate-ccw',
            self::Streak7 => 'flame',
            self::Top10Group => 'trophy',
            self::AllTypes => 'shapes',
        };
    }

    /**
     * XP granted when the badge is awarded.
     */
    public function xpReward(): int
    {
        return match ($this) {
            self::FirstTest => 10,
            self::PerfectScore => 50,
            self::TenTests => 40,
            self::SpeedRunner => 30,
            self::Comeback => 30,
            self::Streak7 => 70,
            self::Top10Group => 60,
            self::AllTypes => 40,
        };
    }
}
