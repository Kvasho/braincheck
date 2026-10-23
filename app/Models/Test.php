<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuestionSelectionMode;
use App\Enums\ResultsVisibility;
use App\Enums\TestStatus;
use App\Models\Concerns\HasPublicUlid;
use Carbon\CarbonImmutable;
use Database\Factories\TestFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A test (exam). `owner_id`, `status` and `published_at` are set only inside Actions.
 *
 * @property int $id
 * @property string $ulid
 * @property int $owner_id
 * @property int|null $category_id
 * @property string $title
 * @property string|null $description
 * @property TestStatus $status
 * @property int|null $time_limit_minutes
 * @property int|null $max_attempts
 * @property int $pass_percent
 * @property bool $shuffle_questions
 * @property bool $shuffle_options
 * @property QuestionSelectionMode $selection_mode
 * @property int|null $random_question_count
 * @property CarbonImmutable|null $opens_at
 * @property CarbonImmutable|null $closes_at
 * @property ResultsVisibility $results_visibility
 * @property bool $show_correct_answers
 * @property CarbonImmutable|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property bool $issues_certificate
 * @property CarbonImmutable|null $locked_at
 * @property CarbonImmutable|null $results_released_at
 * @property Carbon|null $deleted_at
 */
class Test extends Model
{
    /** @use HasFactory<TestFactory> */
    use HasFactory, HasPublicUlid, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'category_id',
        'title',
        'description',
        'time_limit_minutes',
        'max_attempts',
        'pass_percent',
        'shuffle_questions',
        'shuffle_options',
        'selection_mode',
        'random_question_count',
        'opens_at',
        'closes_at',
        'results_visibility',
        'show_correct_answers',
        'issues_certificate',
    ];

    /** @var list<string> */
    protected $hidden = ['id'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'category_id' => null,
        'description' => null,
        'status' => 'draft',
        'time_limit_minutes' => null,
        'max_attempts' => null,
        'shuffle_questions' => false,
        'shuffle_options' => false,
        'selection_mode' => 'fixed',
        'random_question_count' => null,
        'opens_at' => null,
        'closes_at' => null,
        'results_visibility' => 'immediately',
        'show_correct_answers' => false,
        'issues_certificate' => false,
        'published_at' => null,
        'locked_at' => null,
        'results_released_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'status' => TestStatus::class,
            'time_limit_minutes' => 'integer',
            'max_attempts' => 'integer',
            'pass_percent' => 'integer',
            'shuffle_questions' => 'boolean',
            'shuffle_options' => 'boolean',
            'selection_mode' => QuestionSelectionMode::class,
            'random_question_count' => 'integer',
            'opens_at' => 'immutable_datetime',
            'closes_at' => 'immutable_datetime',
            'results_visibility' => ResultsVisibility::class,
            'show_correct_answers' => 'boolean',
            'issues_certificate' => 'boolean',
            'published_at' => 'immutable_datetime',
            'locked_at' => 'immutable_datetime',
            'results_released_at' => 'immutable_datetime',
        ];
    }

    /**
     * Locked once the first real attempt started: questions and grading-relevant
     * settings are immutable from then on (TestLockRules).
     */
    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    /**
     * @param  Builder<Test>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', TestStatus::Published->value);
    }

    /**
     * Tests whose availability window contains $time (open bounds are null).
     *
     * @param  Builder<Test>  $query
     */
    public function scopeOpenAt(Builder $query, DateTimeInterface $time): void
    {
        $query
            ->where(fn (Builder $q) => $q->whereNull('opens_at')->orWhere('opens_at', '<=', $time))
            ->where(fn (Builder $q) => $q->whereNull('closes_at')->orWhere('closes_at', '>', $time));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Questions in test order.
     *
     * @return BelongsToMany<Question, $this>
     */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'test_question')
            ->withPivot(['position', 'points_override'])
            ->orderByPivot('position');
    }

    /**
     * @return BelongsToMany<Group, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)->withPivot('assigned_at');
    }

    /**
     * Every attempt, previews included. For counts and statistics use realAttempts().
     *
     * @return HasMany<Attempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }

    /**
     * Attempts by students (no teacher previews).
     *
     * @return HasMany<Attempt, $this>
     */
    public function realAttempts(): HasMany
    {
        return $this->hasMany(Attempt::class)->where('is_preview', false);
    }
}
