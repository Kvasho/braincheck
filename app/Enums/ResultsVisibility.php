<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * When students may see their results.
 */
enum ResultsVisibility: string implements HasLabel
{
    case Immediately = 'immediately';
    case AfterClose = 'after_close';
    case Manual = 'manual';
    case Never = 'never';

    /**
     * How much this setting reveals: never < manual < after_close < immediately.
     * Once students may have seen results, a locked test may only move up.
     */
    public function revealRank(): int
    {
        return match ($this) {
            self::Never => 0,
            self::Manual => 1,
            self::AfterClose => 2,
            self::Immediately => 3,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Immediately => __('Immediately'),
            self::AfterClose => __('After the test closes'),
            self::Manual => __('When the teacher releases them'),
            self::Never => __('Never'),
        };
    }
}
