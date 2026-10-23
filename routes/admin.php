<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CategoryOrderController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Admin\TagMergeController;
use App\Http\Controllers\Admin\TeacherRequestApprovalController;
use App\Http\Controllers\Admin\TeacherRequestController;
use App\Http\Controllers\Admin\TeacherRequestRejectionController;
use App\Http\Controllers\Admin\UserBlockController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\Teacher\TestAnalyticsController;
use App\Http\Controllers\Teacher\TestAnalyticsRecomputeController;
use App\Http\Controllers\Teacher\TestAttemptDetailController;
use App\Http\Controllers\Teacher\TestRegradeController;
use App\Http\Controllers\Teacher\TestRegradePreviewController;
use App\Http\Controllers\Teacher\TestResultsExportController;
use Illuminate\Support\Facades\Route;

/*
| Admin area: /admin, names admin.*, middleware web + auth + verified + role:admin
| + mandatory 2FA (see bootstrap/app.php). Every route also requires its own
| permission; sensitive actions also require a recent password confirmation.
*/

// Users
Route::middleware('permission:'.Permission::UsersView->value)->group(function (): void {
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
});

Route::put('users/{user}/role', [UserRoleController::class, 'update'])
    ->middleware(['permission:'.Permission::UsersManageRoles->value, 'password.confirm'])
    ->name('users.role.update');

Route::middleware(['permission:'.Permission::UsersBlock->value, 'password.confirm'])->group(function (): void {
    Route::post('users/{user}/block', [UserBlockController::class, 'store'])->name('users.block');
    Route::delete('users/{user}/block', [UserBlockController::class, 'destroy'])->name('users.unblock');
});

// Teacher access requests. Approving grants a role, so it needs a fresh password confirmation.
Route::middleware('permission:'.Permission::UsersManageRoles->value)->group(function (): void {
    Route::get('teacher-requests', [TeacherRequestController::class, 'index'])->name('teacher-requests.index');
    Route::post('teacher-requests/{teacherRequest}/approve', [TeacherRequestApprovalController::class, 'store'])
        ->middleware('password.confirm')
        ->name('teacher-requests.approve');
    Route::post('teacher-requests/{teacherRequest}/reject', [TeacherRequestRejectionController::class, 'store'])
        ->name('teacher-requests.reject');
});

// Categories (2-level tree). `reorder` is declared before {category} on purpose.
Route::middleware('permission:'.Permission::CategoriesManage->value)->group(function (): void {
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('categories/reorder', [CategoryOrderController::class, 'update'])->name('categories.reorder');
    Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
});

// Tags
Route::middleware('permission:'.Permission::TagsManage->value)->group(function (): void {
    Route::get('tags', [TagController::class, 'index'])->name('tags.index');
    Route::post('tags', [TagController::class, 'store'])->name('tags.store');
    Route::put('tags/{tag}', [TagController::class, 'update'])->name('tags.update');
    Route::delete('tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');
    Route::post('tags/{tag}/merge', [TagMergeController::class, 'store'])->name('tags.merge');
});

// Question browse (moderation): read-only.
Route::middleware('permission:'.Permission::QuestionsViewAny->value)->group(function (): void {
    Route::get('questions', [QuestionController::class, 'index'])->name('questions.index');
    Route::get('questions/{question}', [QuestionController::class, 'show'])->withTrashed()->name('questions.show');
});

// Audit log: read-only. No route may ever update or delete entries.
Route::get('audit-log', [AuditLogController::class, 'index'])
    ->middleware('permission:'.Permission::AuditLogView->value)
    ->name('audit-log.index');

// Regrade any test's question (same controller as the owner's; TestPolicy::regrade).
Route::middleware('permission:'.Permission::AttemptsRegradeAny->value)->group(function (): void {
    Route::get('tests/{test}/regrade', [TestRegradeController::class, 'show'])->withTrashed()->name('tests.regrade.show');
    Route::post('tests/{test}/regrade/preview', [TestRegradePreviewController::class, 'store'])->withTrashed()->name('tests.regrade.preview');
    Route::post('tests/{test}/regrade', [TestRegradeController::class, 'store'])
        ->withTrashed()
        ->middleware('password.confirm')
        ->name('tests.regrade.store');
});

// Platform-wide analytics of any test (same controllers as the owner's).
Route::middleware('permission:'.Permission::AnalyticsViewAny->value)->group(function (): void {
    Route::get('tests/{test}/analytics', [TestAnalyticsController::class, 'show'])->withTrashed()->name('tests.analytics.show');
    Route::post('tests/{test}/analytics/recompute', [TestAnalyticsRecomputeController::class, 'store'])->withTrashed()->name('tests.analytics.recompute');
    Route::get('tests/{test}/analytics/attempts/{attempt}', [TestAttemptDetailController::class, 'show'])->withTrashed()->name('tests.analytics.attempts.show');
    Route::get('tests/{test}/results.csv', [TestResultsExportController::class, 'show'])->withTrashed()->name('tests.results.export');
});
