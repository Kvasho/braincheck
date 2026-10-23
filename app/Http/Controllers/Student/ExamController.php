<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Domain\Attempts\StudentExams;
use App\Domain\Tests\AvailableTests;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\ExamIndexRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The student's exams list and a test's intro page. Tests are looked up only
 * through AvailableTests: a test the student can't see is a 404 (never a
 * 403), so the response doesn't confirm that it exists.
 */
final class ExamController extends Controller
{
    public function index(ExamIndexRequest $request, StudentExams $exams): Response
    {
        $user = $request->user() ?? abort(403);
        $group = $request->group();

        return Inertia::render('student/exams/index', [
            'exams' => $exams->cards($user, $group),
            'groups' => $user->groups()->orderBy('name')->get(['groups.id', 'groups.ulid', 'groups.name'])
                ->map(fn ($group): array => ['value' => $group->ulid, 'label' => $group->name])
                ->all(),
            'filters' => ['group' => $group?->ulid],
            'serverNow' => now()->toIso8601String(),
        ]);
    }

    public function show(Request $request, string $test, StudentExams $exams): Response
    {
        $user = $request->user() ?? abort(403);

        $found = AvailableTests::forStudent($user)
            ->where('ulid', $test)
            ->with(['owner:id,name', 'groups' => fn ($groups) => $groups->whereHas('members', fn ($members) => $members->whereKey($user->id))->select(['groups.id', 'groups.name'])])
            ->withCount('questions')
            ->firstOrFail();

        return Inertia::render('student/exams/show', [
            'exam' => $exams->card($found, student: $user),
            'serverNow' => now()->toIso8601String(),
        ]);
    }
}
