<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttemptStatus;
use App\Enums\GradingStatus;
use App\Models\Concerns\HasPublicUlid;
use Carbon\CarbonImmutable;
use Database\Factories\AttemptFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * An exam attempt: immutable history built from snapshots (attempt_questions).
 * Nothing is mass assignable: every field is set by Actions (deadline_at is
 * computed server-side only).
 *
 * @property int $id
 * @property string $ulid
 * @property int $test_id
 * @property int $user_id
 * @property bool $is_preview
 * @property int $attempt_number
 * @property AttemptStatus $status
 * @property GradingStatus $grading_status
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $deadline_at
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $graded_at
 * @property string|null $score
 * @property string|null $max_score
 * @property string|null $score_percent
 * @property bool|null $passed
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $session_hash
 * @property CarbonImmutable|null $last_seen_at
 * @property array<string, int>|null $event_overflow
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Attempt extends Model
{
    /** @use HasFactory<AttemptFactory> */
    use HasFactory, HasPublicUlid;

    /** @var list<string> */
    protected $fillable = [];

    /** @var list<string> */
    protected $hidden = ['id', 'active_lock', 'session_hash'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_preview' => false,
        'status' => 'in_progress',
        'grading_status' => 'pending_auto',
        'deadline_at' => null,
        'submitted_at' => null,
        'graded_at' => null,
        'score' => null,
        'max_score' => null,
        'score_percent' => null,
        'passed' => null,
        'ip_address' => null,
        'user_agent' => null,
        'session_hash' => null,
        'last_seen_at' => null,
        'event_overflow' => null,
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'is_preview' => 'boolean',
            'status' => AttemptStatus::class,
            'grading_status' => GradingStatus::class,
            'started_at' => 'immutable_datetime',
            'deadline_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'graded_at' => 'immutable_datetime',
            'score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'score_percent' => 'decimal:2',
            'passed' => 'boolean',
            'last_seen_at' => 'immutable_datetime',
            'event_overflow' => 'array',
        ];
    }

    /**
     * Real attempts only: teacher previews are excluded from every statistic,
     * leaderboard, XP award, certificate and attempt count.
     *
     * @param  Builder<Attempt>  $query
     */
    public function scopeReal(Builder $query): void
    {
        $query->where('is_preview', false);
    }

    /**
     * @param  Builder<Attempt>  $query
     */
    public function scopePreview(Builder $query): void
    {
        $query->where('is_preview', true);
    }

    /**
     * @param  Builder<Attempt>  $query
     */
    public function scopeInProgress(Builder $query): void
    {
        $query->where('status', AttemptStatus::InProgress->value);
    }

    /**
     * In-progress attempts whose server-side deadline has passed (auto-finish job).
     *
     * @param  Builder<Attempt>  $query
     */
    public function scopeExpired(Builder $query, DateTimeInterface $now): void
    {
        $query->where('status', AttemptStatus::InProgress->value)
            ->whereNotNull('deadline_at')
            ->where('deadline_at', '<=', $now);
    }

    /**
     * Re-reads $attempt with a row lock (`SELECT … FOR UPDATE`) inside the
     * caller's transaction: every attempt mutation starts with this.
     */
    public static function lockedCopyOf(Attempt $attempt): ?self
    {
        return self::query()->whereKey($attempt->id)->lockForUpdate()->first();
    }

    /**
     * The next attempt number of $user on $test. Numbering spans previews too,
     * because `unique(test_id, user_id, attempt_number)` covers both.
     */
    public static function nextNumber(Test $test, User $user): int
    {
        return ((int) self::query()->where('test_id', $test->id)->where('user_id', $user->id)->max('attempt_number')) + 1;
    }

    /**
     * @return BelongsTo<Test, $this>
     */
    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Snapshot questions in attempt order.
     *
     * @return HasMany<AttemptQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(AttemptQuestion::class)->orderBy('position');
    }

    /**
     * @return HasMany<AttemptEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(AttemptEvent::class)->orderBy('occurred_at');
    }

    /**
     * @return HasOne<Certificate, $this>
     */
    public function certificate(): HasOne
    {
        return $this->hasOne(Certificate::class);
    }
}
