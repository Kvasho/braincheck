<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Actions\Questions\RestoreQuestion;
use App\Http\Controllers\Controller;
use App\Models\Question;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class QuestionRestoreController extends Controller
{
    public function store(Question $question, RestoreQuestion $restoreQuestion): RedirectResponse
    {
        Gate::authorize('restore', $question);

        $restoreQuestion->handle($question);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Question restored.')]);

        return back();
    }
}
