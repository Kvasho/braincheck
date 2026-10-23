<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tag;
use Illuminate\Support\Str;

/**
 * Unique tag slugs from the tag name ("Linear algebra" → "linear-algebra",
 * then "linear-algebra-2", ... if taken). Same format as category slugs.
 */
final class TagSlug
{
    public const string PATTERN = CategorySlug::PATTERN;

    public const int MAX_LENGTH = 60;

    public function generate(string $name, ?Tag $ignore = null): string
    {
        $base = trim(Str::limit(Str::slug($name), self::MAX_LENGTH - 4, ''), '-');
        $base = $base !== '' ? $base : 'tag';

        $slug = $base;
        $suffix = 2;

        while (Tag::query()->where('slug', $slug)->when($ignore, fn ($query, Tag $ignore) => $query->whereKeyNot($ignore->getKey()))->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
