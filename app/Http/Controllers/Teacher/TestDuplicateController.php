<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Actions\Tests\DuplicateTest;
use App\Http\Controllers\Controller;
use App\Models\Test;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class TestDuplicateController extends Controller
{
    public function store(Request $request, Test $test, DuplicateTest $duplicateTest): RedirectResponse
    {
        Gate::authorize('duplicate', $test);

        $copy = $duplicateTest->handle($test, $request->user() ?? abort(403));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test duplicated as a new draft.')]);

        return to_route('teacher.tests.edit', $copy);
    }
}
