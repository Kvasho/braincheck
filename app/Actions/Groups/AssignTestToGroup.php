<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Enums\TestStatus;
use App\Models\Group;
use App\Models\Test;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Assigns one of the group owner's published tests to the group. Idempotent.
 */
final class AssignTestToGroup
{
    public function handle(Group $group, Test $test): void
    {
        if ($test->owner_id !== $group->owner_id) {
            throw new AuthorizationException;
        }

        DB::transaction(function () use ($group, $test): void {
            $locked = Test::query()->whereKey($test->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== TestStatus::Published) {
                throw ValidationException::withMessages(['test' => __('Only published tests can be assigned.')]);
            }

            $group->tests()->syncWithoutDetaching([$locked->id => ['assigned_at' => now()]]);
        });
    }
}
