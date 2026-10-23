<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Models\Group;
use App\Models\Test;

/**
 * Same consequences as removing a member: the group's students no longer see
 * the test, attempts in progress may be finished, results stay. Idempotent.
 */
final class UnassignTestFromGroup
{
    public function handle(Group $group, Test $test): void
    {
        $group->tests()->detach($test->id);
    }
}
