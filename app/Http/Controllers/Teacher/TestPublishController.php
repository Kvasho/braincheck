<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Actions\Tests\PublishTest;
use App\Actions\Tests\UnpublishTest;
use App\Http\Controllers\Controller;
use App\Models\Test;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Publish (store) and unpublish (destroy). The rules live in the Actions.
 */
final class TestPublishController extends Controller
{
    public function store(Test $test, PublishTest $publishTest): RedirectResponse
    {
        Gate::authorize('publish', $test);

        $publishTest->handle($test);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test published.')]);

        return to_route('teacher.tests.edit', $test);
    }

    public function destroy(Test $test, UnpublishTest $unpublishTest): RedirectResponse
    {
        Gate::authorize('publish', $test);

        $unpublishTest->handle($test);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test moved back to draft.')]);

        return to_route('teacher.tests.edit', $test);
    }
}
