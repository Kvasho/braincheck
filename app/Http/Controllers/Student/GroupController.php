<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Actions\Groups\RemoveGroupMember;
use App\Domain\Tests\AvailableTests;
use App\Http\Controllers\Controller;
use App\Http\Resources\StudentGroupResource;
use App\Models\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The student's groups (cards) and leaving a group. Students see a group's
 * name, its teacher's name and how many tests it gives them, never the other
 * members.
 */
final class GroupController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user() ?? abort(403);

        $groups = $user->groups()
            ->with('owner:id,name')
            ->orderByPivot('joined_at', 'desc')
            ->get()
            ->each(fn (Group $group) => $group->setAttribute('available_tests_count', AvailableTests::forStudent($user, $group)->count()));

        return Inertia::render('student/groups/index', [
            'groups' => StudentGroupResource::collection($groups)->resolve($request),
        ]);
    }

    public function destroy(Request $request, Group $group, RemoveGroupMember $removeMember): RedirectResponse
    {
        $user = $request->user() ?? abort(403);

        // Not a member (or someone else's group): the same 404 as an unknown group.
        abort_unless(Gate::allows('leave', $group), 404);

        $removeMember->handle($group, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('You left :name.', ['name' => $group->name])]);

        return to_route('student.groups.index');
    }
}
