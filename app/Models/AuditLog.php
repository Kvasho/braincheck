<?php

declare(strict_types=1);

namespace App\Models;

use App\Builders\AuditLogBuilder;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only audit trail. Updating or deleting an entry throws, both on the
 * model (save/delete) and through the query builder (mass update/delete).
 *
 * @property int $id
 * @property int|null $actor_id
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed> $properties
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 */
#[UseEloquentBuilder(AuditLogBuilder::class)]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['action', 'properties', 'ip_address', 'user_agent'];

    /** @var list<string> */
    protected $hidden = ['id'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'ip_address' => null,
        'user_agent' => null,
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Audit log entries are immutable.'));
        static::deleting(fn (): never => throw new LogicException('Audit log entries can not be deleted.'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
