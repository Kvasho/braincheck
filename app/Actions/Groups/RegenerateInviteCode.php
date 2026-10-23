<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Models\Group;
use Illuminate\Support\Facades\DB;

/**
 * Replaces the invite code; the old one stops working immediately.
 */
final class RegenerateInviteCode
{
    public function __construct(private readonly GenerateUniqueInviteCode $generateCode) {}

    public function handle(Group $group): Group
    {
        return DB::transaction(function () use ($group): Group {
            $group->invite_code = $this->generateCode->handle();
            $group->save();

            return $group;
        });
    }
}
