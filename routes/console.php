<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Teacher preview attempts are disposable: delete them after 24 hours.
Schedule::command('braincheck:prune-preview-attempts')->hourly()->withoutOverlapping();

// Exam engine: finish attempts past their deadline (+ grace) as timed out.
// The scheduler must run in production (`php artisan schedule:run` every minute).
Schedule::command('attempts:expire')->everyMinute()->withoutOverlapping();
