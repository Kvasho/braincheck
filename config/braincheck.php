<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Mandatory two-factor authentication
    |--------------------------------------------------------------------------
    |
    | Admin and teacher areas require confirmed 2FA. This switch can only turn
    | the requirement off in the `local` environment (convenient demo logins);
    | everywhere else App\Support\TwoFactorEnforcement forces it on, whatever
    | the .env says.
    |
    */

    'enforce_two_factor' => (bool) env('BRAINCHECK_ENFORCE_TWO_FACTOR', true),

    /*
    |--------------------------------------------------------------------------
    | Teacher access requests
    |--------------------------------------------------------------------------
    |
    | After a rejection, a student can submit a new request only once this many
    | days have passed. Submissions are also rate limited (`teacher-request`).
    |
    */

    'teacher_request_cooldown_days' => 7,

    'teacher_request_message_max' => 1000,

    /*
    |--------------------------------------------------------------------------
    | Display timezone
    |--------------------------------------------------------------------------
    |
    | Dates are stored in UTC (app.timezone). Teachers enter and see test
    | schedules in this timezone (Georgia: UTC+4, no daylight saving time).
    |
    */

    'display_timezone' => 'Asia/Tbilisi',

    /** Preview attempts older than this many hours are deleted. */
    'preview_attempt_ttl_hours' => 24,

    /** Group join attempts per student per day (on top of 5 per minute per user + IP). */
    'join_group_daily_limit' => 30,

    /*
     * Exam engine (Stage 7). The server is the only time authority: answers are
     * accepted until deadline_at + grace (network latency), never later.
     */
    'attempt_grace_seconds' => 10,

    /** Anti-cheat events: at most this many per request, and stored rows per attempt (then only counted). */
    'attempt_events_per_request' => 50,
    'attempt_events_per_attempt' => 500,

];
