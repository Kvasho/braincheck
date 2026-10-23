<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Actions\Audit\RecordAuditEvent;
use App\Domain\Shared\LocalizedName;
use App\Enums\AuditAction;
use App\Models\Category;
use App\Models\User;
use App\Support\CategorySlug;
use App\Support\CategoryTree;
use Illuminate\Support\Facades\DB;

/**
 * Creates a category at the end of its level. Input is validated by
 * StoreCategoryRequest (depth rule, slug format and uniqueness).
 */
final class CreateCategory
{
    public function __construct(
        private readonly CategorySlug $slug,
        private readonly CategoryTree $tree,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(LocalizedName $name, ?string $slug, ?Category $parent, User $actor): Category
    {
        $category = DB::transaction(function () use ($name, $slug, $parent, $actor): Category {
            $category = new Category([
                'name' => $name,
                'slug' => $slug ?? $this->slug->generate($name->en),
                'parent_id' => $parent?->id,
                'sort_order' => CategorySlug::nextSortOrder($parent),
            ]);
            $category->save();

            $this->recordAuditEvent->handle(AuditAction::CategoryCreated, $actor, $category, [
                'name' => $name->toArray(),
                'slug' => $category->slug,
                'parent' => $parent?->ulid,
            ]);

            return $category;
        });

        DB::afterCommit(fn () => $this->tree->forget());

        return $category;
    }
}
