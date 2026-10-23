<?php

declare(strict_types=1);

use App\Domain\Groups\InviteCode;
use App\Models\Group;

it('uses an unambiguous alphabet without 0, O, 1, I and L', function (): void {
    foreach (['0', 'O', '1', 'I', 'L'] as $ambiguous) {
        expect(InviteCode::ALPHABET)->not->toContain($ambiguous);
    }

    expect(InviteCode::ALPHABET)->toMatch('/^[A-Z2-9]+$/')
        ->and(count(array_unique(str_split(InviteCode::ALPHABET))))->toBe(strlen(InviteCode::ALPHABET));
});

it('generates 10 characters from the alphabet, different every time', function (): void {
    $codes = collect(range(1, 200))->map(fn (): string => InviteCode::generate());

    $codes->each(function (string $code): void {
        expect($code)->toHaveLength(InviteCode::LENGTH)
            ->and(InviteCode::isWellFormed($code))->toBeTrue();
    });

    expect($codes->unique())->toHaveCount(200)
        ->and(Group::generateInviteCode())->toHaveLength(10);
});

it('normalises what students type', function (string $input, string $expected): void {
    expect(InviteCode::normalize($input))->toBe($expected);
})->with([
    'lower case' => ['abcde fghjk', 'ABCDEFGHJK'],
    'dashes and spaces' => ['  ABCDE-FGHJK ', 'ABCDEFGHJK'],
]);

it('recognises only well-formed codes', function (string $code, bool $wellFormed): void {
    expect(InviteCode::isWellFormed($code))->toBe($wellFormed);
})->with([
    'valid' => ['ABCDEFGHJK', true],
    'too short' => ['ABCDEFGHJ', false],
    'ambiguous letter' => ['ABCDEFGHJL', false],
    'zero' => ['ABCDEFGHJ0', false],
]);

it('reduces join-link query values to code characters', function (mixed $value, ?string $expected): void {
    expect(InviteCode::fromQuery($value))->toBe($expected);
})->with([
    'code' => ['abcde-fghjk', 'ABCDEFGHJK'],
    'markup' => ['<script>x</script>', 'SCRIPTXSCRIPT'],
    'array' => [['A'], null],
    'empty' => ['---', null],
    'long' => [str_repeat('A', 50), str_repeat('A', 32)],
]);
