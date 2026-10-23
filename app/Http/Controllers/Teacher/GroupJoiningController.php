<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Actions\Groups\SetGroupJoining;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\UpdateGroupJoiningRequest;
use App\Models\Group;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class GroupJoiningController extends Controller
{
    public function update(UpdateGroupJoiningRequest $request, Group $group, SetGroupJoining $setJoining): RedirectResponse
    {
        $setJoining->handle($group, $request->enabled());

        Inertia::flash('toast', ['type' => 'success', 'message' => $request->enabled()
            ? __('Students can join with the code again.')
            : __('Joining is turned off.')]);

        return to_route('teacher.groups.show', ['group' => $group, 'tab' => 'invite']);
    }
}
