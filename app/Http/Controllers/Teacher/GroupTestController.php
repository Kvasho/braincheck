<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Actions\Groups\AssignTestToGroup;
use App\Actions\Groups\UnassignTestFromGroup;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\AssignGroupTestRequest;
use App\Models\Group;
use App\Models\Test;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Assign the owner's published tests to a group, or unassign them.
 */
final class GroupTestController extends Controller
{
    public function store(AssignGroupTestRequest $request, Group $group, AssignTestToGroup $assign): RedirectResponse
    {
        $assign->handle($group, $request->test());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test assigned to the group.')]);

        return to_route('teacher.groups.show', ['group' => $group, 'tab' => 'tests']);
    }

    public function destroy(Group $group, Test $test, UnassignTestFromGroup $unassign): RedirectResponse
    {
        Gate::authorize('update', $group);

        abort_unless($group->tests()->whereKey($test->id)->exists(), 404);

        $unassign->handle($group, $test);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test removed from the group.')]);

        return to_route('teacher.groups.show', ['group' => $group, 'tab' => 'tests']);
    }
}
