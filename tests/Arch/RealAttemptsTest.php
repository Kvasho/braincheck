<?php

declare(strict_types=1);

/*
 * Teacher preview attempts must never count: every attempt query in app/
 * (statistics, leaderboards, XP, certificates, attempt counts) uses
 * Attempt::real() / ->real() / realAttempts(), or is one of the few places
 * that deal with previews on purpose.
 */
test('attempt queries exclude preview attempts', function (): void {
    $root = dirname(__DIR__, 2).'/app';
    $allowed = [
        // The model defines the scopes and relations.
        'Models/Attempt.php',
        'Models/Test.php',
        'Models/User.php',
        'Models/AttemptQuestion.php',
        // Preview handling itself.
        'Actions/Tests/StartPreviewAttempt.php',
        'Actions/Tests/SubmitPreviewAttempt.php',
        'Console/Commands/PrunePreviewAttemptsCommand.php',
        'Http/Controllers/Teacher/TestPreviewController.php',
        'Http/Controllers/Teacher/TestPreviewSubmitController.php',
        'Http/Requests/Teacher/SubmitPreviewRequest.php',
    ];

    $pattern = '/Attempt::query\(\)|->attempts\(\)|[\'"]attempts[\'"]/';
    $offenders = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

        // API Resources only read counts that were loaded (with real()) elsewhere.
        if (! str_ends_with($relative, '.php') || in_array($relative, $allowed, true) || str_starts_with($relative, 'Http/Resources/')) {
            continue;
        }

        foreach (file($file->getPathname()) ?: [] as $index => $line) {
            if (preg_match($pattern, $line) === 1 && preg_match('/real\(\)|realAttempts|->preview\(\)|is_preview/', $line) !== 1) {
                $offenders[] = "{$relative}:".($index + 1).': '.trim($line);
            }
        }
    }

    expect($offenders)->toBe([]);
});
