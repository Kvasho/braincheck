<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Domain\Groups\GroupSettings;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateGroup
{
    public function __construct(private readonly GenerateUniqueInviteCode $generateCode) {}

    public function handle(User $owner, GroupSettings $settings): Group
    {
        return DB::transaction(function () use ($owner, $settings): Group {
            $group = new Group([
                'name' => $settings->name,
                'description' => $settings->description,
                'joining_enabled' => $settings->joiningEnabled,
                'invite_code_expires_at' => $settings->inviteExpiresAt,
            ]);
            $group->owner()->associate($owner);
            $group->invite_code = $this->generateCode->handle();
            $group->save();

            return $group;
        });
    }
}
