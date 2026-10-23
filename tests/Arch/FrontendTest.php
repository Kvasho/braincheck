<?php

declare(strict_types=1);

/*
 * Synapse design rule: components use theme tokens (bg-primary, text-muted-foreground, ...),
 * never Tailwind palette colours or colour literals. A deliberate exception must carry a
 * `synapse-allow-color` comment on the same or the previous line, with the reason.
 */
test('frontend components use theme tokens, not hard-coded colours', function (): void {
    $root = dirname(__DIR__, 2).'/resources/js';
    $generated = ['actions', 'routes', 'wayfinder'];

    $palette = 'white|black|slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose';
    $patterns = [
        '/\b(?:text|bg|border|ring|stroke|fill|decoration|from|via|to|outline|shadow|divide|placeholder|caret)-(?:'.$palette.')(?:-\d{2,3})?\b/',
        '/[\'"`(\[\s]#[0-9a-fA-F]{3,8}\b/',
        '/\b(?:rgba?|hsla?|oklch|oklab)\(/',
    ];

    $offenders = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

        if (! preg_match('/\.tsx?$/', $relative) || in_array(explode('/', $relative)[0], $generated, true)) {
            continue;
        }

        $lines = file($file->getPathname()) ?: [];

        foreach ($lines as $index => $line) {
            $allowed = str_contains($line, 'synapse-allow-color')
                || str_contains($lines[$index - 1] ?? '', 'synapse-allow-color')
                || str_contains($lines[$index - 2] ?? '', 'synapse-allow-color');

            foreach ($patterns as $pattern) {
                if (! $allowed && preg_match($pattern, $line, $match) === 1) {
                    $offenders[] = "{$relative}:".($index + 1)." ({$match[0]})";
                }
            }
        }
    }

    expect($offenders)->toBe([]);
});
