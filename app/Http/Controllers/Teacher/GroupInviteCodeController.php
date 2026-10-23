<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Actions\Groups\RegenerateInviteCode;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Support\QrCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * The secret invite code: revealed only here (JSON, owner only, never
 * cached) and regenerated on demand (the old code stops working at once).
 */
final class GroupInviteCodeController extends Controller
{
    public function show(Group $group): JsonResponse
    {
        Gate::authorize('viewInviteCode', $group);

        $joinUrl = route('join', ['code' => $group->invite_code]);

        return response()
            ->json([
                'code' => $group->invite_code,
                'join_url' => $joinUrl,
                'qr' => QrCode::svgDataUri($joinUrl),
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    public function store(Group $group, RegenerateInviteCode $regenerate): RedirectResponse
    {
        Gate::authorize('viewInviteCode', $group);

        $regenerate->handle($group);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('New invite code created. The old code no longer works.')]);

        return to_route('teacher.groups.show', ['group' => $group, 'tab' => 'invite']);
    }
}
