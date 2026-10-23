<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Category;
use Illuminate\Support\Str;

/**
 * Slug and position helpers for the category Actions.
 */
final class CategorySlug
{
    /** Lowercase words joined by single hyphens. */
    public const string PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public const int MAX_LENGTH = 100;

    /**
     * A unique slug from the English name: "Linear Algebra" → "linear-algebra",
     * then "linear-algebra-2", ... if taken.
     */
    public function generate(string $englishName, ?Category $ignore = null): string
    {
        $base = Str::limit(Str::slug($englishName), self::MAX_LENGTH - 4, '');
        $base = trim($base, '-') !== '' ? trim($base, '-') : 'category';

        $slug = $base;
        $suffix = 2;

        while ($this->taken($slug, $ignore)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * The sort position after the last sibling.
     */
    public static function nextSortOrder(?Category $parent): int
    {
        $max = Category::query()
            ->where('parent_id', $parent?->id)
            ->max('sort_order');

        return is_numeric($max) ? (int) $max + 1 : 0;
    }

    private function taken(string $slug, ?Category $ignore): bool
    {
        return Category::query()
            ->where('slug', $slug)
            ->when($ignore, fn ($query, Category $ignore) => $query->whereKeyNot($ignore->getKey()))
            ->exists();
    }
}
