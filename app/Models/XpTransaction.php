<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\XpReason;
use Database\Factories\XpTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * An XP ledger entry. Unique per (user, reason, source), so awarding is
 * idempotent; every award must carry a source. Written only by an Action that
 * also updates users.xp_total in the same transaction.
 *
 * @property int $id
 * @property int $user_id
 * @property int $amount
 * @property XpReason $reason
 * @property string|null $source_type
 * @property int|null $source_id
 * @property Carbon $created_at
 */
class XpTransaction extends Model
{
    /** @use HasFactory<XpTransactionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [];

    /** @var list<string> */
    protected $hidden = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'reason' => XpReason::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
