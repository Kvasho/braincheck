<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Domain\Groups\InviteCode;
use App\Models\Group;
use RuntimeException;

/**
 * A fresh invite code no group (archived ones included) uses. Collisions are
 * astronomically unlikely; we still retry, and the unique index is the final guard.
 */
final class GenerateUniqueInviteCode
{
    public const int MAX_TRIES = 5;

    public function handle(): string
    {
        for ($try = 0; $try < self::MAX_TRIES; $try++) {
            $code = InviteCode::generate();

            if (! Group::query()->withTrashed()->where('invite_code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Could not generate a unique invite code.');
    }
}
