<?php

declare(strict_types=1);

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JoinLinkController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\QuestionImageController;
use Illuminate\Support\Facades\Route;

// Landing page for guests; signed-in users are redirected to /dashboard by `guest`.
Route::inertia('/', 'welcome')->middleware('guest')->name('home');

// Language switch: guests and users, CSRF-protected (web group), rate-limited.
Route::post('locale', [LocaleController::class, 'update'])
    ->middleware('throttle:locale')
    ->name('locale.update');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Question images: private files, authorized per question (QuestionPolicy::viewImage).
    Route::get('question-images/{question}', [QuestionImageController::class, 'show'])
        ->withTrashed()
        ->name('question-images.show');

    // Shareable group join link (and QR target): on to the pre-filled join form.
    Route::get('join', [JoinLinkController::class, 'show'])->name('join');
});

// Development-only pages: never registered outside the local environment.
if (app()->isLocal()) {
    Route::inertia('dev/styleguide', 'dev/styleguide')->name('dev.styleguide');
}

require __DIR__.'/settings.php';
