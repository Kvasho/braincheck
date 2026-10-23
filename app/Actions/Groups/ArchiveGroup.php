<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Models\Group;
use Illuminate\Support\Facades\DB;

/**
 * Archives (soft-deletes) a group: nobody can join, its students no longer
 * see its tests. Members, assignments and results are kept.
 */
final class ArchiveGroup
{
    public function handle(Group $group): void
    {
        DB::transaction(function () use ($group): void {
            $group->joining_enabled = false;
            $group->save();
            $group->delete();
        });
    }
}
