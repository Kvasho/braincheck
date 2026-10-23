<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Category;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * The category tree as plain arrays (never models), cached because many selects
 * use it. Every category Action calls forget() after its transaction commits.
 */
final readonly class CategoryTree
{
    public const string CACHE_KEY = 'categories.tree';

    public function __construct(private Cache $cache) {}

    /**
     * @return list<array{ulid: string, name: array{ka: string, en: string}, slug: string, children: list<array{ulid: string, name: array{ka: string, en: string}, slug: string}>}>
     */
    public function get(): array
    {
        /** @var list<array{ulid: string, name: array{ka: string, en: string}, slug: string, children: list<array{ulid: string, name: array{ka: string, en: string}, slug: string}>}> */
        return $this->cache->rememberForever(self::CACHE_KEY, fn (): array => Category::query()
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Category $category): array => [
                ...self::node($category),
                'children' => $category->children->map(fn (Category $child): array => self::node($child))->values()->all(),
            ])
            ->values()
            ->all());
    }

    public function forget(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }

    /**
     * @return array{ulid: string, name: array{ka: string, en: string}, slug: string}
     */
    private static function node(Category $category): array
    {
        return [
            'ulid' => $category->ulid,
            'name' => $category->name->toArray(),
            'slug' => $category->slug,
        ];
    }
}
