<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Domain\Groups\GroupSettings;
use App\Models\Group;
use Illuminate\Support\Facades\DB;

final class UpdateGroup
{
    public function handle(Group $group, GroupSettings $settings): Group
    {
        return DB::transaction(function () use ($group, $settings): Group {
            $group->fill([
                'name' => $settings->name,
                'description' => $settings->description,
                'joining_enabled' => $settings->joiningEnabled,
            ]);

            if ($settings->changeExpiry) {
                $group->invite_code_expires_at = $settings->inviteExpiresAt;
            }

            $group->save();

            return $group;
        });
    }
}
