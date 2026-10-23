<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Models\Group;

/**
 * Turns joining with the invite code on or off (the code itself stays).
 */
final class SetGroupJoining
{
    public function handle(Group $group, bool $enabled): Group
    {
        $group->joining_enabled = $enabled;
        $group->save();

        return $group;
    }
}
