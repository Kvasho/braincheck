<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * Public identifier: a `ulid` column next to the internal auto-increment `id`.
 *
 * - the ulid is generated on create (never mass assignable);
 * - it is the route key, so URLs never contain numeric ids;
 * - route binding rejects anything that isn't a valid ULID (a numeric id is a 404).
 *
 * The integer `id` stays the primary key (only `ulid` is a unique id here).
 */
trait HasPublicUlid
{
    use HasUlids;

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
