<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A node of the admin category tree, with usage counts for the delete dialog.
 * Requires questions_count / tests_count; children are included when loaded.
 *
 * @mixin Category
 */
final class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'name' => $this->name->toArray(),
            'slug' => $this->slug,
            'sort_order' => $this->sort_order,
            'questions_count' => (int) $this->questions_count,
            'tests_count' => (int) $this->tests_count,
            'children' => $this->relationLoaded('children')
                ? self::collection($this->children)->resolve($request)
                : [],
        ];
    }
}
