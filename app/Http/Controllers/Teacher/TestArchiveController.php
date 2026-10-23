<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Actions\Tests\ArchiveTest;
use App\Http\Controllers\Controller;
use App\Models\Test;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class TestArchiveController extends Controller
{
    public function store(Test $test, ArchiveTest $archiveTest): RedirectResponse
    {
        Gate::authorize('delete', $test);

        $archiveTest->handle($test);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test archived. Students no longer see it.')]);

        return to_route('teacher.tests.index');
    }
}
