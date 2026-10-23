<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Listing;

function userListing(): Listing
{
    return new Listing(
        searchable: ['name', 'email'],
        sortable: ['name' => 'name', 'created_at' => 'created_at'],
        defaultSort: 'created_at',
    );
}

it('escapes LIKE wildcards', function (string $input, string $escaped): void {
    expect(Listing::escapeLike($input))->toBe($escaped);
})->with([
    ['100%', '100\\%'],
    ['a_b', 'a\\_b'],
    ['back\\slash', 'back\\\\slash'],
    ['%_\\', '\\%\\_\\\\'],
    ['plain', 'plain'],
]);

it('matches wildcard characters literally', function (): void {
    User::factory()->create(['name' => 'Nino 100% sure']);
    User::factory()->create(['name' => 'Nino 1000 sure']);
    User::factory()->create(['name' => 'under_score']);
    User::factory()->create(['name' => 'underXscore']);

    $names = fn (string $search): array => userListing()
        ->paginate(User::query(), ['search' => $search])
        ->pluck('name')->sort()->values()->all();

    expect($names('100%'))->toBe(['Nino 100% sure'])
        ->and($names('under_score'))->toBe(['under_score'])
        ->and($names('%'))->toBe(['Nino 100% sure']);
});

it('searches every configured column', function (): void {
    User::factory()->create(['name' => 'Giorgi', 'email' => 'g@example.com']);
    User::factory()->create(['name' => 'Ana', 'email' => 'giorgi.fan@example.com']);
    User::factory()->create(['name' => 'Luka', 'email' => 'luka@example.com']);

    expect(userListing()->paginate(User::query(), ['search' => 'giorgi'])->total())->toBe(2);
});

it('ignores an invalid or malicious sort column', function (mixed $sort): void {
    $state = userListing()->state(['sort' => $sort, 'direction' => 'asc']);

    expect($state['sort'])->toBe('created_at');

    // And the query still runs with the default order.
    User::factory()->count(2)->create();
    expect(userListing()->paginate(User::query(), ['sort' => $sort])->total())->toBe(2);
})->with([
    'sql injection' => 'id; DROP TABLE users',
    'unlisted column' => 'password',
    'raw expression' => 'name desc, (select 1)',
    'array' => [['name']],
    'null' => null,
]);

it('only accepts asc and desc', function (mixed $direction): void {
    expect(userListing()->state(['direction' => $direction])['direction'])->toBe('desc');
})->with(['DESC; DROP TABLE users', 'sideways', '', null]);

it('sorts by a whitelisted column', function (): void {
    User::factory()->create(['name' => 'Beka']);
    User::factory()->create(['name' => 'Ana']);
    User::factory()->create(['name' => 'Ciala']);

    $names = userListing()->paginate(User::query(), ['sort' => 'name', 'direction' => 'asc'])->pluck('name')->all();

    expect($names)->toBe(['Ana', 'Beka', 'Ciala']);
});

it('clamps the page size', function (mixed $perPage, int $expected): void {
    expect(userListing()->perPage($perPage))->toBe($expected);
})->with([
    [100000, Listing::MAX_PER_PAGE],
    [101, Listing::MAX_PER_PAGE],
    [50, 50],
    [0, 1],
    [-5, 1],
    [null, Listing::DEFAULT_PER_PAGE],
    ['abc', Listing::DEFAULT_PER_PAGE],
]);

it('paginates with consistent meta', function (): void {
    User::factory()->count(5)->create();

    $props = Listing::toProps(
        userListing()->paginate(User::query(), ['per_page' => 2]),
        App\Http\Resources\AuthUserResource::class,
        request(),
    );

    expect($props['meta'])->toBe([
        'current_page' => 1,
        'last_page' => 3,
        'per_page' => 2,
        'total' => 5,
        'from' => 1,
        'to' => 2,
    ])->and($props['data'])->toHaveCount(2);
});
