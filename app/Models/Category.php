<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\LocalizedNameCast;
use App\Domain\Shared\LocalizedName;
use App\Models\Concerns\HasPublicUlid;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Two-level taxonomy (category → subcategory; depth enforced in the application).
 *
 * @property int $id
 * @property string $ulid
 * @property int|null $parent_id
 * @property LocalizedName $name
 * @property string $slug
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasPublicUlid;

    /** @var list<string> */
    protected $fillable = ['parent_id', 'name', 'slug', 'sort_order'];

    /** @var list<string> */
    protected $hidden = ['id'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'parent_id' => null,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'name' => LocalizedNameCast::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    /**
     * @return HasMany<Test, $this>
     */
    public function tests(): HasMany
    {
        return $this->hasMany(Test::class);
    }
}
