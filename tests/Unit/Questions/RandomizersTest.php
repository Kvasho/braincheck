<?php

declare(strict_types=1);

use App\Domain\Questions\Definitions\ShortId;
use App\Domain\Shared\Randomizers;
use Random\Engine\Secure;

it('uses the secure engine by default', function (): void {
    expect(Randomizers::secure()->engine)->toBeInstanceOf(Secure::class);
});

it('repeats a seeded sequence for the same seed only', function (): void {
    $items = range(1, 20);

    $first = Randomizers::seeded('attempt-123')->shuffleArray($items);
    $again = Randomizers::seeded('attempt-123')->shuffleArray($items);
    $other = Randomizers::seeded('attempt-456')->shuffleArray($items);

    expect($first)->toBe($again)
        ->and($first)->not->toBe($other)
        ->and($first)->toEqualCanonicalizing($items);
});

it('generates valid, unique short ids from the CSPRNG', function (): void {
    $ids = array_map(fn (): string => ShortId::generate(), range(1, 500));

    expect(array_filter($ids, fn (string $id): bool => ! ShortId::isValid($id)))->toBe([])
        ->and(array_unique($ids))->toHaveCount(500);
});
