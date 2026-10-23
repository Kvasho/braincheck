<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Users\ChangeUserRole;
use App\Exceptions\RoleChangeNotAllowed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRoleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class UserRoleController extends Controller
{
    /**
     * Change a user's role (password confirmation required by the route).
     * The audit entry is written by the UserRoleChanged listener.
     */
    public function update(UpdateUserRoleRequest $request, User $user, ChangeUserRole $changeUserRole): RedirectResponse
    {
        try {
            $changeUserRole->handle($user, $request->role(), $request->user() ?? abort(403));
        } catch (RoleChangeNotAllowed $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return back();
    }
}
