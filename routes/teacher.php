<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Http\Controllers\Teacher\GradingAnonymityController;
use App\Http\Controllers\Teacher\GradingAnswerController;
use App\Http\Controllers\Teacher\GradingController;
use App\Http\Controllers\Teacher\GradingIdentityController;
use App\Http\Controllers\Teacher\GradingOverrideController;
use App\Http\Controllers\Teacher\GroupArchiveController;
use App\Http\Controllers\Teacher\GroupController;
use App\Http\Controllers\Teacher\GroupInviteCodeController;
use App\Http\Controllers\Teacher\GroupJoiningController;
use App\Http\Controllers\Teacher\GroupMemberController;
use App\Http\Controllers\Teacher\GroupTestController;
use App\Http\Controllers\Teacher\QuestionBulkController;
use App\Http\Controllers\Teacher\QuestionController;
use App\Http\Controllers\Teacher\QuestionDuplicateController;
use App\Http\Controllers\Teacher\QuestionPreviewController;
use App\Http\Controllers\Teacher\QuestionPreviewGradeController;
use App\Http\Controllers\Teacher\QuestionRestoreController;
use App\Http\Controllers\Teacher\TestAnalyticsController;
use App\Http\Controllers\Teacher\TestAnalyticsRecomputeController;
use App\Http\Controllers\Teacher\TestArchiveController;
use App\Http\Controllers\Teacher\TestAttemptDetailController;
use App\Http\Controllers\Teacher\TestController;
use App\Http\Controllers\Teacher\TestDuplicateController;
use App\Http\Controllers\Teacher\TestPreviewController;
use App\Http\Controllers\Teacher\TestPreviewSubmitController;
use App\Http\Controllers\Teacher\TestPublishController;
use App\Http\Controllers\Teacher\TestQuestionPickerController;
use App\Http\Controllers\Teacher\TestRegradeController;
use App\Http\Controllers\Teacher\TestRegradePreviewController;
use App\Http\Controllers\Teacher\TestResultsExportController;
use App\Http\Controllers\Teacher\TestResultsReleaseController;
use Illuminate\Support\Facades\Route;

/*
| Teacher area: /teacher, names teacher.*, middleware web + auth + verified + role:teacher
| (see bootstrap/app.php). Every route also requires its own permission.
*/

// Question bank: the teacher's own questions. Single-question routes are
// authorized by QuestionPolicy (owner only); listings are scoped to the owner.
Route::middleware('permission:'.Permission::QuestionsViewOwn->value)->group(function (): void {
    Route::get('questions', [QuestionController::class, 'index'])->name('questions.index');
    Route::get('questions/{question}/preview', [QuestionPreviewController::class, 'show'])
        ->withTrashed()
        ->name('questions.preview');
    Route::post('questions/{question}/preview/grade', [QuestionPreviewGradeController::class, 'store'])
        ->withTrashed()
        ->middleware('throttle:question-preview')
        ->name('questions.preview.grade');
});

Route::middleware('permission:'.Permission::QuestionsManageOwn->value)->group(function (): void {
    Route::get('questions/create', [QuestionController::class, 'create'])->name('questions.create');
    Route::post('questions', [QuestionController::class, 'store'])->name('questions.store');
    Route::post('questions/bulk', [QuestionBulkController::class, 'store'])->name('questions.bulk');
    Route::get('questions/{question}/edit', [QuestionController::class, 'edit'])->name('questions.edit');
    Route::put('questions/{question}', [QuestionController::class, 'update'])->name('questions.update');
    Route::delete('questions/{question}', [QuestionController::class, 'destroy'])->name('questions.destroy');
    Route::post('questions/{question}/restore', [QuestionRestoreController::class, 'store'])
        ->withTrashed()
        ->name('questions.restore');
    Route::post('questions/{question}/duplicate', [QuestionDuplicateController::class, 'store'])->name('questions.duplicate');
});

// Tests (builder). Single-test routes are authorized by TestPolicy (owner only).
Route::middleware('permission:'.Permission::TestsViewOwn->value)->group(function (): void {
    Route::get('tests', [TestController::class, 'index'])->name('tests.index');
    Route::post('tests/{test}/preview', [TestPreviewController::class, 'store'])->name('tests.preview.store');
    Route::get('tests/{test}/preview/{attempt}', [TestPreviewController::class, 'show'])->name('tests.preview.show');
    Route::post('tests/{test}/preview/{attempt}/submit', [TestPreviewSubmitController::class, 'store'])->name('tests.preview.submit');
});

Route::middleware('permission:'.Permission::TestsManageOwn->value)->group(function (): void {
    Route::get('tests/create', [TestController::class, 'create'])->name('tests.create');
    Route::post('tests', [TestController::class, 'store'])->name('tests.store');
    Route::get('tests/question-picker', [TestQuestionPickerController::class, 'index'])->name('tests.question-picker');
    Route::get('tests/{test}/edit', [TestController::class, 'edit'])->name('tests.edit');
    Route::put('tests/{test}', [TestController::class, 'update'])->name('tests.update');
    Route::post('tests/{test}/publish', [TestPublishController::class, 'store'])->name('tests.publish');
    Route::delete('tests/{test}/publish', [TestPublishController::class, 'destroy'])->name('tests.unpublish');
    Route::post('tests/{test}/archive', [TestArchiveController::class, 'store'])->name('tests.archive');
    Route::post('tests/{test}/duplicate', [TestDuplicateController::class, 'store'])->name('tests.duplicate');
    Route::post('tests/{test}/results/release', [TestResultsReleaseController::class, 'store'])->withTrashed()->name('tests.results.release');
});

// Groups. Single-group routes are authorized by GroupPolicy (owner only).
Route::middleware('permission:'.Permission::GroupsManageOwn->value)->group(function (): void {
    Route::get('groups', [GroupController::class, 'index'])->name('groups.index');
    Route::get('groups/create', [GroupController::class, 'create'])->name('groups.create');
    Route::post('groups', [GroupController::class, 'store'])->name('groups.store');
    Route::get('groups/{group}', [GroupController::class, 'show'])->name('groups.show');
    Route::get('groups/{group}/edit', [GroupController::class, 'edit'])->name('groups.edit');
    Route::put('groups/{group}', [GroupController::class, 'update'])->name('groups.update');
    Route::post('groups/{group}/archive', [GroupArchiveController::class, 'store'])->name('groups.archive');
    Route::get('groups/{group}/invite-code', [GroupInviteCodeController::class, 'show'])->name('groups.invite-code.show');
    Route::post('groups/{group}/invite-code', [GroupInviteCodeController::class, 'store'])->name('groups.invite-code.regenerate');
    Route::put('groups/{group}/joining', [GroupJoiningController::class, 'update'])->name('groups.joining.update');
    Route::delete('groups/{group}/members/{member}', [GroupMemberController::class, 'destroy'])->name('groups.members.destroy');
    Route::post('groups/{group}/tests', [GroupTestController::class, 'store'])->name('groups.tests.store');
    Route::delete('groups/{group}/tests/{test}', [GroupTestController::class, 'destroy'])->name('groups.tests.destroy');
});

// Analytics of the teacher's own tests (TestPolicy::viewAnalytics; admins use the admin area).
Route::middleware('permission:'.Permission::AnalyticsViewOwnTests->value)->group(function (): void {
    Route::get('tests/{test}/analytics', [TestAnalyticsController::class, 'show'])->withTrashed()->name('tests.analytics.show');
    Route::post('tests/{test}/analytics/recompute', [TestAnalyticsRecomputeController::class, 'store'])->withTrashed()->name('tests.analytics.recompute');
    Route::get('tests/{test}/analytics/attempts/{attempt}', [TestAttemptDetailController::class, 'show'])->withTrashed()->name('tests.analytics.attempts.show');
    Route::get('tests/{test}/results.csv', [TestResultsExportController::class, 'show'])->withTrashed()->name('tests.results.export');
});

// Grading: the teacher's own tests only (AttemptPolicy::grade, TestPolicy::regrade).
// Answers are addressed by attempt ULID + position (no answer IDs in URLs).
Route::middleware('permission:'.Permission::AttemptsGradeOwnTests->value)->group(function (): void {
    Route::get('grading', [GradingController::class, 'index'])->name('grading.index');
    Route::put('grading/anonymity', [GradingAnonymityController::class, 'update'])->name('grading.anonymity.update');
    Route::get('grading/attempts/{attempt}/questions/{position}', [GradingAnswerController::class, 'show'])
        ->whereNumber('position')
        ->name('grading.answers.show');
    Route::put('grading/attempts/{attempt}/questions/{position}', [GradingAnswerController::class, 'update'])
        ->whereNumber('position')
        ->name('grading.answers.update');
    Route::put('grading/attempts/{attempt}/questions/{position}/override', [GradingOverrideController::class, 'update'])
        ->whereNumber('position')
        ->name('grading.answers.override');
    Route::post('grading/attempts/{attempt}/identity', [GradingIdentityController::class, 'store'])->name('grading.identity.store');

    // Regrade: fixes a question's correct answer in this test's snapshots (password confirmed).
    Route::get('tests/{test}/regrade', [TestRegradeController::class, 'show'])->withTrashed()->name('tests.regrade.show');
    Route::post('tests/{test}/regrade/preview', [TestRegradePreviewController::class, 'store'])->withTrashed()->name('tests.regrade.preview');
    Route::post('tests/{test}/regrade', [TestRegradeController::class, 'store'])
        ->withTrashed()
        ->middleware('password.confirm')
        ->name('tests.regrade.store');
});
