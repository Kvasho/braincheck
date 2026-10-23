<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TeacherRequestStatus;
use App\Models\Concerns\HasPublicUlid;
use Carbon\CarbonImmutable;
use Database\Factories\TeacherAccessRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A "request teacher access" submission (flow: Stage 3). `user_id`, `status`
 * and the review fields are set only inside Actions.
 *
 * @property int $id
 * @property string $ulid
 * @property int $user_id
 * @property string $message
 * @property TeacherRequestStatus $status
 * @property int|null $reviewed_by
 * @property CarbonImmutable|null $reviewed_at
 * @property string|null $review_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TeacherAccessRequest extends Model
{
    /** @use HasFactory<TeacherAccessRequestFactory> */
    use HasFactory, HasPublicUlid;

    /** @var list<string> */
    protected $fillable = ['message'];

    /** @var list<string> */
    protected $hidden = ['id', 'pending_lock'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'pending',
        'reviewed_by' => null,
        'reviewed_at' => null,
        'review_note' => null,
    ];

    protected function casts(): array
    {
        return [
            'status' => TeacherRequestStatus::class,
            'reviewed_at' => 'immutable_datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
