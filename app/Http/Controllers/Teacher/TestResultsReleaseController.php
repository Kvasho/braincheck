<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Actions\Tests\ReleaseResults;
use App\Http\Controllers\Controller;
use App\Models\Test;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class TestResultsReleaseController extends Controller
{
    public function store(Request $request, Test $test, ReleaseResults $release): RedirectResponse
    {
        Gate::authorize('releaseResults', $test);

        $release->handle($test, $request->user() ?? abort(403));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Results released. Students can see their graded scores now.')]);

        return to_route('teacher.tests.analytics.show', $test);
    }
}
