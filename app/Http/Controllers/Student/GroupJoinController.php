<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Actions\Groups\JoinGroup;
use App\Domain\Groups\InviteCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\JoinGroupRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The join form (optionally pre-filled from a join link) and joining.
 * POST is rate limited: join-group (5/min per user + IP) and
 * join-group-daily (braincheck.join_group_daily_limit per user).
 */
final class GroupJoinController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('student/groups/join', [
            'code' => InviteCode::fromQuery($request->query('code')),
        ]);
    }

    public function store(JoinGroupRequest $request, JoinGroup $joinGroup): RedirectResponse
    {
        ['group' => $group, 'joined' => $joined] = $joinGroup->handle($request->user() ?? abort(403), $request->code());

        Inertia::flash('toast', ['type' => 'success', 'message' => $joined
            ? __('You joined :name.', ['name' => $group->name])
            : __('You are already a member of :name.', ['name' => $group->name])]);

        return to_route('student.groups.index');
    }
}
