<?php

declare(strict_types=1);

use App\Domain\Questions\Points;
use Brick\Math\BigDecimal;

it('stores exact values with two decimals', function (int|string $input, string $expected): void {
    expect(Points::of($input)->toString())->toBe($expected);
})->with([
    [1, '1.00'],
    ['1.5', '1.50'],
    ['2.25', '2.25'],
    ['0', '0.00'],
    ['100', '100.00'],
]);

it('rejects values it would have to round, negatives and non-numbers', function (string $input): void {
    expect(fn () => Points::of($input))->toThrow(InvalidArgumentException::class);
})->with(['1.005', '-1', 'abc', '', '1e', 'NaN', 'INF']);

it('cannot be built from a float', function (): void {
    $method = new ReflectionMethod(Points::class, 'of');
    $types = array_map(fn (ReflectionNamedType $type): string => $type->getName(), $method->getParameters()[0]->getType()->getTypes());

    expect($types)->not->toContain('float');
});

it('adds and subtracts exactly where floats would drift', function (): void {
    $sum = Points::zero();

    foreach (range(1, 10) as $i) {
        $sum = $sum->plus(Points::of('0.10'));
    }

    expect($sum->toString())->toBe('1.00')
        ->and(Points::of('0.30')->minus(Points::of('0.10'))->toString())->toBe('0.20')
        ->and(fn () => Points::of('1')->minus(Points::of('2')))->toThrow(InvalidArgumentException::class);
});

it('multiplies by fractions exactly and rounds once, half up', function (string $points, int $numerator, int $denominator, string $expected): void {
    expect(Points::of($points)->timesFraction($numerator, $denominator)->toString())->toBe($expected);
})->with([
    'one third of 1' => ['1', 1, 3, '0.33'],
    'two thirds of 1' => ['1', 2, 3, '0.67'],
    'half of 0.05 (half up)' => ['0.05', 1, 2, '0.03'],
    'half of 0.01 (half up)' => ['0.01', 1, 2, '0.01'],
    'quarter of 2.50' => ['2.50', 1, 4, '0.63'],
    'full' => ['3', 3, 3, '3.00'],
    'nothing' => ['3', 0, 3, '0.00'],
    'negative ratio counts as zero' => ['3', -2, 3, '0.00'],
]);

it('rejects a non-positive denominator', function (int $denominator): void {
    expect(fn () => Points::of('1')->timesFraction(1, $denominator))->toThrow(InvalidArgumentException::class);
})->with([0, -3]);

it('compares and serializes as a string, never a float', function (): void {
    $one = Points::of('1');
    $half = Points::of('0.5');

    expect($one->isGreaterThan($half))->toBeTrue()
        ->and($half->min($one))->toBe($half)
        ->and($one->equals(Points::of('1.00')))->toBeTrue()
        ->and($one->compareTo($half))->toBe(1)
        ->and(Points::zero()->isZero())->toBeTrue()
        ->and(json_encode(['points' => $half]))->toBe('{"points":"0.50"}')
        ->and((string) $half)->toBe('0.50')
        ->and(Points::of(BigDecimal::of('7.1'))->toString())->toBe('7.10');
});
