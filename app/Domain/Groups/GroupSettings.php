<?php

declare(strict_types=1);

namespace App\Domain\Groups;

use Carbon\CarbonImmutable;

/**
 * The editable settings of a group, validated by the Form Request.
 */
final readonly class GroupSettings
{
    /**
     * @param  bool  $changeExpiry  false keeps the current invite expiry (edit form "keep")
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public bool $joiningEnabled,
        public ?CarbonImmutable $inviteExpiresAt,
        public bool $changeExpiry = true,
    ) {}
}
