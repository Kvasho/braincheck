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
 * Renames / re-slugs / moves a category. Input is validated by
 * UpdateCategoryRequest (depth rule in both directions, slug rules). A category
 * that moves to another level goes to the end of that level.
 */
final class UpdateCategory
{
    public function __construct(
        private readonly CategorySlug $slug,
        private readonly CategoryTree $tree,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(Category $category, LocalizedName $name, ?string $slug, ?Category $parent, User $actor): Category
    {
        DB::transaction(function () use ($category, $name, $slug, $parent, $actor): void {
            $before = [
                'name' => $category->name->toArray(),
                'slug' => $category->slug,
                'parent' => $category->parent_id,
            ];

            $category->name = $name;
            $category->slug = $slug ?? $this->slug->generate($name->en, $category);

            if ($category->parent_id !== $parent?->id) {
                $category->parent_id = $parent?->id;
                $category->sort_order = CategorySlug::nextSortOrder($parent);
            }

            $category->save();

            $this->recordAuditEvent->handle(AuditAction::CategoryUpdated, $actor, $category, [
                'from' => [...$before, 'parent' => $before['parent'] === null ? null : Category::query()->whereKey($before['parent'])->value('ulid')],
                'to' => ['name' => $name->toArray(), 'slug' => $category->slug, 'parent' => $parent?->ulid],
            ]);
        });

        DB::afterCommit(fn () => $this->tree->forget());

        return $category;
    }
}
