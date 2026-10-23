<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Models\Category;
use App\Models\Question;
use App\Models\Test;
use App\Models\User;
use App\Support\CategoryTree;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deletes a category without children. Questions and tests that used it keep
 * existing with no category (FK `ON DELETE SET NULL`), archived ones included;
 * the counts are recorded in the audit entry.
 */
final class DeleteCategory
{
    public function __construct(
        private readonly CategoryTree $tree,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(Category $category, User $actor): void
    {
        DB::transaction(function () use ($category, $actor): void {
            $locked = Category::query()->whereKey($category->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->children()->exists()) {
                throw ValidationException::withMessages([
                    'category' => __('Delete or move its subcategories first.'),
                ]);
            }

            $properties = [
                'name' => $locked->name->toArray(),
                'slug' => $locked->slug,
                'questions_detached' => Question::withTrashed()->where('category_id', $locked->id)->count(),
                'tests_detached' => Test::withTrashed()->where('category_id', $locked->id)->count(),
            ];

            // Recorded first: the entry keeps the subject type/id even after the row is gone.
            $this->recordAuditEvent->handle(AuditAction::CategoryDeleted, $actor, $locked, $properties);

            $locked->delete();
        });

        DB::afterCommit(fn () => $this->tree->forget());
    }
}
