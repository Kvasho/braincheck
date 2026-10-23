<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Analytics\AnalyticsCache;
use App\Http\Controllers\Controller;
use App\Jobs\RecomputeTestQuestionStats;
use App\Models\Test;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * The analytics page's "recompute" button: clears the cached numbers and
 * queues a stats recompute (debounced per test).
 */
final class TestAnalyticsRecomputeController extends Controller
{
    public function store(Request $request, Test $test): RedirectResponse
    {
        Gate::authorize('viewAnalytics', $test);

        AnalyticsCache::invalidate($test->id);
        dispatch(new RecomputeTestQuestionStats($test->id));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Statistics are being recomputed.')]);

        return to_route($request->routeIs('admin.*') ? 'admin.tests.analytics.show' : 'teacher.tests.analytics.show', $test);
    }
}
