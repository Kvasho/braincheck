<?php

declare(strict_types=1);

/*
 * Attempt snapshots are history: they are written when an attempt starts
 * (SnapshotBuilder / StartAttempt / StartPreviewAttempt) and changed by
 * RegradeTestQuestion only. Nothing else in app/ may write a snapshot.
 */
test('only the regrade path modifies attempt snapshots', function (): void {
    $root = dirname(__DIR__, 2).'/app';
    $allowed = [
        'Domain/Attempts/SnapshotBuilder.php',
        'Actions/Attempts/StartAttempt.php',
        'Actions/Tests/StartPreviewAttempt.php',
        'Actions/Grading/RegradeTestQuestion.php',
        // Declares the cast only.
        'Models/AttemptQuestion.php',
    ];
    $patterns = [
        '/[\'"]snapshot[\'"]\s*=>/',
        '/->snapshot\s*=[^=]/',
        '/->snapshot\[[^\]]*\]\s*=[^=]/',
    ];
    $offenders = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

        if (! str_ends_with($relative, '.php') || in_array($relative, $allowed, true)) {
            continue;
        }

        foreach (file($file->getPathname()) ?: [] as $index => $line) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $line) === 1) {
                    $offenders[] = "{$relative}:".($index + 1).': '.trim($line);
                }
            }
        }
    }

    expect($offenders)->toBe([]);
});
