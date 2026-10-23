<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Search, sort and pagination for every index page.
 *
 * - Search: user input is LIKE-escaped (`%`, `_`, `\`) and matched as "contains"
 *   against the configured columns only.
 * - Sort: only the whitelisted public keys are accepted; anything else (unknown key,
 *   SQL fragment, wrong direction) silently falls back to the default and never
 *   reaches the query.
 * - Page size: `per_page` is clamped to 1..MAX_PER_PAGE.
 *
 * Filters are validated by the page's Form Request (see ListingRequest) and applied
 * by the caller before paginate().
 */
final readonly class Listing
{
    public const int DEFAULT_PER_PAGE = 20;

    public const int MAX_PER_PAGE = 100;

    public const int MAX_SEARCH_LENGTH = 100;

    /**
     * @param  list<string>  $searchable  columns matched by the search term
     * @param  array<string, string|Closure(QueryBuilder, 'asc'|'desc'): mixed>  $sortable  public sort key => column, or a
     *                                                                                      closure that orders the query (code, never user input)
     * @param  'asc'|'desc'  $defaultDirection
     */
    public function __construct(
        private array $searchable,
        private array $sortable,
        private string $defaultSort,
        private string $defaultDirection = 'desc',
    ) {}

    /**
     * Escape LIKE wildcards so user input always matches literally.
     */
    public static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $params  validated input (see ListingRequest)
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(Builder $query, array $params): LengthAwarePaginator
    {
        $state = $this->state($params);

        if ($state['search'] !== null && $this->searchable !== []) {
            $term = '%'.self::escapeLike($state['search']).'%';

            $query->where(function (Builder $query) use ($term): void {
                foreach ($this->searchable as $column) {
                    $query->orWhere($column, 'like', $term);
                }
            });
        }

        $sort = $this->sortable[$state['sort']];

        if ($sort instanceof Closure) {
            $sort($query->getQuery(), $state['direction']);
        } else {
            $query->orderBy($sort, $state['direction']);
        }

        $query
            // Unique tiebreaker: rows with equal sort values never shift between pages.
            ->orderBy($query->getModel()->getQualifiedKeyName(), $state['direction']);

        return $query->paginate($state['per_page'])->withQueryString();
    }

    /**
     * The effective listing state (after whitelisting and clamping), echoed back
     * to the page so the UI shows what was actually applied.
     *
     * @param  array<string, mixed>  $params
     * @return array{search: string|null, sort: string, direction: 'asc'|'desc', per_page: int}
     */
    public function state(array $params): array
    {
        $search = is_string($params['search'] ?? null) ? trim($params['search']) : '';
        $sort = $params['sort'] ?? null;
        $direction = $params['direction'] ?? null;

        return [
            'search' => $search === '' ? null : mb_substr($search, 0, self::MAX_SEARCH_LENGTH),
            'sort' => is_string($sort) && array_key_exists($sort, $this->sortable) ? $sort : $this->defaultSort,
            'direction' => in_array($direction, ['asc', 'desc'], true) ? $direction : $this->defaultDirection,
            'per_page' => $this->perPage($params['per_page'] ?? null),
        ];
    }

    public function perPage(mixed $value): int
    {
        if (! is_numeric($value)) {
            return self::DEFAULT_PER_PAGE;
        }

        return max(1, min(self::MAX_PER_PAGE, (int) $value));
    }

    /**
     * Paginated rows plus consistent pagination meta for the shared DataTable / Pagination.
     *
     * @template TModel of Model
     *
     * @param  LengthAwarePaginator<int, TModel>  $paginator
     * @param  class-string<JsonResource>  $resource
     * @return array{data: array<array-key, mixed>, meta: array{current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null}}
     */
    public static function toProps(LengthAwarePaginator $paginator, string $resource, Request $request): array
    {
        return [
            'data' => $resource::collection($paginator->items())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }
}
