<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Models\Group;
use App\Models\User;

/**
 * Removes a student from a group (also used when a student leaves).
 *
 * Consequences: the student no longer sees or starts the group's tests
 * (AvailableTests), an attempt already in progress may still be finished
 * until its deadline, and past results stay. Idempotent.
 */
final class RemoveGroupMember
{
    public function handle(Group $group, User $member): void
    {
        $group->members()->detach($member->id);
    }
}
