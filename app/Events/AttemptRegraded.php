<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Attempt;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An attempt's score changed through a regrade or override. Consumers:
 * certificates (Stage 10: revoke / issue when `passed` flips) and XP
 * (Stage 11: adjustments). Dispatched after commit.
 */
final class AttemptRegraded implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Attempt $attempt,
        public readonly ?string $oldScore,
        public readonly ?string $newScore,
        public readonly ?bool $oldPassed,
        public readonly ?bool $newPassed,
    ) {}
}
