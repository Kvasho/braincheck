<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Http\Controllers\Student\AttemptAnswerController;
use App\Http\Controllers\Student\AttemptController;
use App\Http\Controllers\Student\AttemptEventController;
use App\Http\Controllers\Student\AttemptImageController;
use App\Http\Controllers\Student\AttemptResultController;
use App\Http\Controllers\Student\AttemptSubmitController;
use App\Http\Controllers\Student\ExamController;
use App\Http\Controllers\Student\GroupController;
use App\Http\Controllers\Student\GroupJoinController;
use App\Http\Controllers\Student\ResultController;
use App\Http\Controllers\Student\TeacherRequestController;
use Illuminate\Support\Facades\Route;

/*
| Student area: /student, names student.*, middleware web + auth + verified + role:student
| (see bootstrap/app.php). Every route also requires its own permission.
*/

// Groups: join with a code (rate limited per minute and per day), list, leave.
Route::middleware('permission:'.Permission::GroupsJoin->value)->group(function (): void {
    Route::get('groups', [GroupController::class, 'index'])->name('groups.index');
    Route::get('groups/join', [GroupJoinController::class, 'create'])->name('groups.join');
    Route::post('groups/join', [GroupJoinController::class, 'store'])
        ->middleware(['throttle:join-group', 'throttle:join-group-daily'])
        ->name('groups.join.store');
    Route::delete('groups/{group}', [GroupController::class, 'destroy'])->name('groups.leave');
});

// Exams. Tests are looked up only through AvailableTests ({test} is a plain
// ULID, not route-model bound); attempts only by their owner (AttemptPolicy::take,
// otherwise 404). The server is the only authority on time and answers.
Route::middleware('permission:'.Permission::ExamsTake->value)->group(function (): void {
    Route::get('exams', [ExamController::class, 'index'])->name('exams.index');
    Route::get('exams/{test}', [ExamController::class, 'show'])->name('exams.show');
    Route::post('exams/{test}/attempts', [AttemptController::class, 'store'])
        ->middleware('throttle:start-attempt')
        ->name('exams.attempts.store');

    Route::get('attempts/{attempt}', [AttemptController::class, 'show'])->name('attempts.show');
    Route::put('attempts/{attempt}/answers/{position}', [AttemptAnswerController::class, 'update'])
        ->whereNumber('position')
        ->middleware('throttle:autosave')
        ->name('attempts.answers.update');
    Route::post('attempts/{attempt}/submit', [AttemptSubmitController::class, 'store'])
        ->middleware('throttle:autosave')
        ->name('attempts.submit');
    Route::post('attempts/{attempt}/events', [AttemptEventController::class, 'store'])
        ->middleware('throttle:attempt-events')
        ->name('attempts.events.store');
    Route::get('attempts/{attempt}/questions/{position}/image', [AttemptImageController::class, 'show'])
        ->whereNumber('position')
        ->name('attempts.images.show');
});

// "Submitted" page (the full result page comes in Stage 9).
Route::get('attempts/{attempt}/result', [AttemptResultController::class, 'show'])
    ->middleware('permission:'.Permission::ResultsViewOwn->value)
    ->name('attempts.result');

// Results history: scores only where the test's results visibility allows (ResultsVisibilityPolicy).
Route::get('results', [ResultController::class, 'index'])
    ->middleware('permission:'.Permission::ResultsViewOwn->value)
    ->name('results.index');

// Teacher access request: 3 submissions per user per day.
Route::middleware('permission:'.Permission::TeacherRequestCreate->value)->group(function (): void {
    Route::get('teacher-request', [TeacherRequestController::class, 'show'])->name('teacher-request.show');
    Route::post('teacher-request', [TeacherRequestController::class, 'store'])
        ->middleware('throttle:teacher-request')
        ->name('teacher-request.store');
});
