<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\QuestionDefinitionCast;
use App\Casts\RichTextCast;
use App\Domain\Questions\Definitions\QuestionDefinition;
use App\Domain\Shared\RichTextDocument;
use App\Enums\Difficulty;
use App\Enums\QuestionType;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A question in a teacher's bank.
 *
 * `definition` contains the correct answer: it is $hidden and only read by
 * dedicated code paths (grading, the owner's editor). `owner_id` and `version`
 * are set only inside Actions. Set `type` before `definition` (the cast needs it).
 * `body` / `explanation` are rich text documents; `body_text` (plain text for
 * search and listings) is derived from `body` on every save. `image_path` is a
 * random file name on the private disk, set only by the image Actions.
 *
 * @property int $id
 * @property string $ulid
 * @property int $owner_id
 * @property int|null $category_id
 * @property QuestionType $type
 * @property Difficulty $difficulty
 * @property RichTextDocument $body
 * @property RichTextDocument|null $explanation
 * @property string $body_text
 * @property string|null $image_path
 * @property QuestionDefinition $definition
 * @property string $points
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory, HasPublicUlid, SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['category_id', 'type', 'difficulty', 'body', 'explanation', 'definition', 'points'];

    /** @var list<string> */
    protected $hidden = ['id', 'definition'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'category_id' => null,
        'explanation' => null,
        'image_path' => null,
        'points' => '1.00',
        'version' => 1,
    ];

    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'difficulty' => Difficulty::class,
            'definition' => QuestionDefinitionCast::class,
            'body' => RichTextCast::class,
            'explanation' => RichTextCast::class,
            'points' => 'decimal:2',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Question $question): void {
            if ($question->isDirty('body')) {
                $question->body_text = mb_substr($question->body->plainText, 0, RichTextDocument::MAX_TEXT_LENGTH);
            }
        });
    }

    /**
     * @param  Builder<Question>  $query
     */
    public function scopeOwnedBy(Builder $query, User $user): void
    {
        $query->where('owner_id', $user->id);
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
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * @return BelongsToMany<Test, $this>
     */
    public function tests(): BelongsToMany
    {
        return $this->belongsToMany(Test::class, 'test_question')->withPivot(['position', 'points_override']);
    }
}
