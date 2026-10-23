<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Models\Category;
use App\Models\User;
use App\Support\CategoryTree;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sets the order of one level (the top level, or one parent's children).
 *
 * The payload must list exactly the current siblings, each once: a foreign
 * ULID, a category from another level or a missing sibling rejects the whole
 * request, nothing is applied partially. Moving between levels is done by
 * editing the category's parent, never by reordering.
 */
final class ReorderCategories
{
    public function __construct(
        private readonly CategoryTree $tree,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /**
     * @param  list<string>  $orderedUlids
     *
     * @throws ValidationException
     */
    public function handle(?Category $parent, array $orderedUlids, User $actor): void
    {
        DB::transaction(function () use ($parent, $orderedUlids, $actor): void {
            $siblings = Category::query()
                ->where('parent_id', $parent?->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('ulid');

            $given = array_values(array_unique($orderedUlids));
            $current = $siblings->keys()->map(fn (mixed $ulid): string => (string) $ulid)->sort()->values()->all();
            $sortedGiven = $given;
            sort($sortedGiven);

            if (count($given) !== count($orderedUlids) || $sortedGiven !== $current) {
                throw ValidationException::withMessages([
                    'order' => __('The order must list every category of this level exactly once.'),
                ]);
            }

            foreach ($given as $position => $ulid) {
                $category = $siblings->get($ulid);

                if ($category instanceof Category && $category->sort_order !== $position) {
                    $category->sort_order = $position;
                    $category->save();
                }
            }

            $this->recordAuditEvent->handle(AuditAction::CategoryUpdated, $actor, $parent, [
                'reordered' => $parent === null ? 'top level' : $parent->ulid,
                'order' => $given,
            ]);
        });

        DB::afterCommit(fn () => $this->tree->forget());
    }
}
