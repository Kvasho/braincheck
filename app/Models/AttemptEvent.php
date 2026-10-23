<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AntiCheatEvent;
use Carbon\CarbonImmutable;
use Database\Factories\AttemptEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Anti-cheat log entry (client-reported, timestamped with server time).
 *
 * @property int $id
 * @property int $attempt_id
 * @property AntiCheatEvent $type
 * @property CarbonImmutable $occurred_at
 * @property array<string, mixed>|null $meta
 */
class AttemptEvent extends Model
{
    /** @use HasFactory<AttemptEventFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['type', 'occurred_at', 'meta'];

    /** @var list<string> */
    protected $hidden = ['id'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'meta' => null,
    ];

    protected function casts(): array
    {
        return [
            'type' => AntiCheatEvent::class,
            'occurred_at' => 'immutable_datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Attempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }
}
