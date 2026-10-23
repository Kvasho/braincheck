<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Groups\InviteCode;
use App\Enums\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The shareable join link /join?code=… (also the QR code target). It sits
 * behind `auth`, so a guest logs in first and Laravel's intended URL brings
 * them back here with the code. Students go on to the pre-filled join form;
 * the code is only shown there, never checked here.
 */
final class JoinLinkController extends Controller
{
    public function show(Request $request): RedirectResponse
    {
        $user = $request->user() ?? abort(403);

        if (! $user->can(Permission::GroupsJoin->value)) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('Join links are for students.')]);

            return to_route('dashboard');
        }

        return to_route('student.groups.join', array_filter(['code' => InviteCode::fromQuery($request->query('code'))]));
    }
}
