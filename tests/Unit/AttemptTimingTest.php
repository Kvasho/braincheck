<?php

declare(strict_types=1);

use App\Domain\Attempts\AttemptTiming;
use Carbon\CarbonImmutable;

it('computes deadline = min(start + time limit, closes_at), ignoring missing parts', function (?int $limit, ?string $closes, ?string $expected): void {
    $start = CarbonImmutable::parse('2026-10-23 10:00:00', 'UTC');
    $deadline = AttemptTiming::deadline($start, $limit, $closes === null ? null : CarbonImmutable::parse($closes, 'UTC'));

    expect($deadline?->toDateTimeString())->toBe($expected);
})->with([
    'limit only' => [30, null, '2026-10-23 10:30:00'],
    'closes only' => [null, '2026-10-23 12:00:00', '2026-10-23 12:00:00'],
    'limit ends first' => [30, '2026-10-23 12:00:00', '2026-10-23 10:30:00'],
    'window ends first' => [120, '2026-10-23 10:45:00', '2026-10-23 10:45:00'],
    'same moment' => [60, '2026-10-23 11:00:00', '2026-10-23 11:00:00'],
    'neither' => [null, null, null],
]);
