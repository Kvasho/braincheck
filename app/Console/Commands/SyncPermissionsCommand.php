<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Authorization\SyncRolesAndPermissions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('braincheck:sync-permissions')]
#[Description('Sync roles and permissions from the enums (idempotent; run on every deploy)')]
final class SyncPermissionsCommand extends Command
{
    public function handle(SyncRolesAndPermissions $sync): int
    {
        $result = $sync->handle();

        $this->components->info('Roles and permissions are in sync with the enums.');

        foreach ([
            'Created permissions' => $result['created_permissions'],
            'Deleted permissions' => $result['deleted_permissions'],
            'Created roles' => $result['created_roles'],
        ] as $label => $names) {
            $this->components->twoColumnDetail($label, $names === [] ? '-' : implode(', ', $names));
        }

        if ($result['stale_roles'] !== []) {
            $this->components->warn('Roles not in App\Enums\Role (permissions removed): '.implode(', ', $result['stale_roles']));
        }

        return self::SUCCESS;
    }
}
