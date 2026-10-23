<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Recursively scans a student-facing payload for correct-answer information.
 * Reused by every test that checks what students receive (player payloads now,
 * the exam engine in Stage 7).
 *
 * A finding is:
 * - a forbidden key at any depth (or any key mentioning "correct"/"reference");
 * - a scalar value equal to one of the given secret values (e.g. the numeric
 *   answer or the reference answer text), compared as strings.
 */
final class PayloadLeakScanner
{
    /** @var list<string> */
    public const array FORBIDDEN_KEYS = [
        'correct', 'correct_order', 'correct_pairs', 'pairs', 'is_correct',
        'reference_answer', 'tolerance', 'answer', 'solution',
        'snapshot', 'definition', 'explanation',
    ];

    /**
     * @param  array<array-key, mixed>  $payload
     * @param  list<string>  $secretValues
     * @return list<string> human-readable findings, empty when clean
     */
    public static function findings(array $payload, array $secretValues = [], string $path = '$'): array
    {
        $findings = [];

        foreach ($payload as $key => $value) {
            $here = "{$path}.{$key}";
            $name = strtolower((string) $key);

            if (in_array($name, self::FORBIDDEN_KEYS, true) || str_contains($name, 'correct') || str_contains($name, 'reference')) {
                $findings[] = "forbidden key at {$here}";
            }

            if (is_array($value)) {
                array_push($findings, ...self::findings($value, $secretValues, $here));
            } elseif (is_scalar($value) && $secretValues !== [] && in_array((string) $value, $secretValues, true)) {
                $findings[] = "secret value at {$here}";
            }
        }

        return $findings;
    }
}
