<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Users\BlockUser;
use App\Actions\Users\UnblockUser;
use App\Exceptions\UserBlockNotAllowed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlockUserRequest;
use App\Http\Requests\Admin\UnblockUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Block / unblock (password confirmation required by the routes).
 */
final class UserBlockController extends Controller
{
    public function store(BlockUserRequest $request, User $user, BlockUser $blockUser): RedirectResponse
    {
        try {
            $blockUser->handle($user, $request->reason(), $request->user() ?? abort(403));
        } catch (UserBlockNotAllowed $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User blocked. All of their sessions have ended.')]);

        return back();
    }

    public function destroy(UnblockUserRequest $request, User $user, UnblockUser $unblockUser): RedirectResponse
    {
        $unblockUser->handle($user, $request->user() ?? abort(403));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User unblocked.')]);

        return back();
    }
}
