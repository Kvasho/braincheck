<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A user's role was changed. Consumed by the audit log (Stage 3).
 * Dispatched only after the surrounding transaction commits.
 */
final class UserRoleChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly User $actor,
        public readonly ?Role $oldRole,
        public readonly Role $newRole,
    ) {}
}
