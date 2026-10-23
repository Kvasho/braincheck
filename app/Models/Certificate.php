<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Carbon\CarbonImmutable;
use Database\Factories\CertificateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A certificate for a passed attempt. holder_name, test_title and score_percent
 * are snapshots taken at issue time. Everything is set by Actions
 * (the verification code comes from a CSPRNG).
 *
 * @property int $id
 * @property string $ulid
 * @property int $attempt_id
 * @property int $user_id
 * @property int $test_id
 * @property string $verification_code
 * @property string $holder_name
 * @property string $test_title
 * @property string $score_percent
 * @property CarbonImmutable $issued_at
 * @property CarbonImmutable|null $revoked_at
 * @property string|null $revoked_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Certificate extends Model
{
    /** @use HasFactory<CertificateFactory> */
    use HasFactory, HasPublicUlid;

    public const VERIFICATION_CODE_LENGTH = 32;

    /** @var list<string> */
    protected $fillable = [];

    /** @var list<string> */
    protected $hidden = ['id'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'revoked_at' => null,
        'revoked_reason' => null,
    ];

    protected function casts(): array
    {
        return [
            'score_percent' => 'decimal:2',
            'issued_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * @return BelongsTo<Attempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Test, $this>
     */
    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class)->withTrashed();
    }
}
