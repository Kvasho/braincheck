<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Badge;
use Carbon\CarbonImmutable;
use Database\Factories\UserBadgeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An awarded badge. The table's (user_id, badge) primary key makes each badge
 * awardable once; rows are insert-only (no single-column key, no updates).
 *
 * @property int $user_id
 * @property Badge $badge
 * @property CarbonImmutable $awarded_at
 */
class UserBadge extends Model
{
    /** @use HasFactory<UserBadgeFactory> */
    use HasFactory;

    public $timestamps = false;

    public $incrementing = false;

    /** Composite key (user_id, badge): Eloquent has no single primary key here. */
    protected $primaryKey = 'user_id';

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'badge' => Badge::class,
            'awarded_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
