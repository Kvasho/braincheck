<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Actions\Groups\RemoveGroupMember;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class GroupMemberController extends Controller
{
    public function destroy(Group $group, User $member, RemoveGroupMember $removeMember): RedirectResponse
    {
        Gate::authorize('update', $group);

        abort_unless($group->members()->whereKey($member->id)->exists(), 404);

        $removeMember->handle($group, $member);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name was removed from the group.', ['name' => $member->name])]);

        return to_route('teacher.groups.show', ['group' => $group, 'tab' => 'members']);
    }
}
