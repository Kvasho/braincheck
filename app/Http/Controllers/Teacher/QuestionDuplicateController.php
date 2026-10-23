<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Actions\Questions\DuplicateQuestion;
use App\Http\Controllers\Controller;
use App\Models\Question;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class QuestionDuplicateController extends Controller
{
    public function store(Request $request, Question $question, DuplicateQuestion $duplicateQuestion): RedirectResponse
    {
        Gate::authorize('duplicate', $question);

        $copy = $duplicateQuestion->handle($question, $request->user() ?? abort(403));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Question duplicated. You are editing the copy.')]);

        return to_route('teacher.questions.edit', $copy);
    }
}
