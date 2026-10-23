<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Groups\InviteCode;
use App\Models\Concerns\HasPublicUlid;
use Carbon\CarbonImmutable;
use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A teacher's student group. `invite_code` is secret ($hidden) and, like
 * `owner_id`, set only inside Actions.
 *
 * @property int $id
 * @property string $ulid
 * @property int $owner_id
 * @property string $name
 * @property string|null $description
 * @property string $invite_code
 * @property CarbonImmutable|null $invite_code_expires_at
 * @property bool $joining_enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory, HasPublicUlid, SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['name', 'description', 'invite_code_expires_at', 'joining_enabled'];

    /** @var list<string> */
    protected $hidden = ['id', 'invite_code'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'description' => null,
        'invite_code_expires_at' => null,
        'joining_enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'invite_code_expires_at' => 'immutable_datetime',
            'joining_enabled' => 'boolean',
        ];
    }

    /**
     * A new invite code from a CSPRNG (uniqueness: see GenerateUniqueInviteCode).
     */
    public static function generateInviteCode(): string
    {
        return InviteCode::generate();
    }

    /**
     * Whether students can join with the code right now.
     */
    public function acceptsJoins(): bool
    {
        return $this->joining_enabled
            && ! $this->trashed()
            && ($this->invite_code_expires_at === null || $this->invite_code_expires_at->isFuture());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('joined_at');
    }

    /**
     * @return BelongsToMany<Test, $this>
     */
    public function tests(): BelongsToMany
    {
        return $this->belongsToMany(Test::class)->withPivot('assigned_at');
    }
}
