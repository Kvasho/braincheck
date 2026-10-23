<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\HasLabel;
use Carbon\CarbonImmutable;

/**
 * Invite expiry choices of the group form. Presets count from "now";
 * `Keep` (edit only) leaves the current expiry unchanged.
 */
enum InviteExpiry: string implements HasLabel
{
    case Never = 'never';
    case OneDay = '1d';
    case SevenDays = '7d';
    case ThirtyDays = '30d';
    case Custom = 'custom';
    case Keep = 'keep';

    public function label(): string
    {
        return match ($this) {
            self::Never => __('Never expires'),
            self::OneDay => __('In 1 day'),
            self::SevenDays => __('In 7 days'),
            self::ThirtyDays => __('In 30 days'),
            self::Custom => __('On a date'),
            self::Keep => __('Keep the current expiry'),
        };
    }

    /**
     * The expiry for a preset; null for Never (and for Custom/Keep, which the caller resolves).
     */
    public function presetFrom(CarbonImmutable $now): ?CarbonImmutable
    {
        return match ($this) {
            self::OneDay => $now->addDay(),
            self::SevenDays => $now->addDays(7),
            self::ThirtyDays => $now->addDays(30),
            default => null,
        };
    }
}
