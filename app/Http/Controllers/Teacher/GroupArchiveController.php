<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Actions\Groups\ArchiveGroup;
use App\Http\Controllers\Controller;
use App\Models\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class GroupArchiveController extends Controller
{
    public function store(Group $group, ArchiveGroup $archive): RedirectResponse
    {
        Gate::authorize('delete', $group);

        $archive->handle($group);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Group archived. Its students no longer see its tests.')]);

        return to_route('teacher.groups.index');
    }
}
