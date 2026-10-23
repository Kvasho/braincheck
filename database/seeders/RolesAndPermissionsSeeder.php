<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Authorization\SyncRolesAndPermissions;
use Illuminate\Database\Seeder;

/**
 * Syncs roles and permissions from the enums. Idempotent, safe in production,
 * never creates users. Same logic as `php artisan braincheck:sync-permissions`.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(SyncRolesAndPermissions $sync): void
    {
        $sync->handle();
    }
}
