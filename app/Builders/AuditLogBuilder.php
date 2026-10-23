<?php

declare(strict_types=1);

namespace App\Builders;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Query builder for the append-only audit log: mass updates and deletes throw.
 *
 * @extends Builder<AuditLog>
 */
final class AuditLogBuilder extends Builder
{
    public function update(array $values): never
    {
        throw new LogicException('Audit log entries are immutable.');
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @param  array<array-key, mixed>|string  $uniqueBy
     * @param  array<array-key, mixed>|null  $update
     */
    public function upsert(array $values, $uniqueBy, $update = null): never
    {
        throw new LogicException('Audit log entries are immutable.');
    }

    public function increment($column, $amount = 1, array $extra = []): never
    {
        throw new LogicException('Audit log entries are immutable.');
    }

    public function decrement($column, $amount = 1, array $extra = []): never
    {
        throw new LogicException('Audit log entries are immutable.');
    }

    /**
     * @param  string|null  $column
     */
    public function touch($column = null): never
    {
        throw new LogicException('Audit log entries are immutable.');
    }

    public function delete(): never
    {
        throw new LogicException('Audit log entries can not be deleted.');
    }

    public function forceDelete(): never
    {
        throw new LogicException('Audit log entries can not be deleted.');
    }
}
