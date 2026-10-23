<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Difficulty;
use App\Enums\MultipleChoiceScoring;
use App\Enums\QuestionType;
use App\Models\Category;
use App\Models\Question;
use App\Models\Tag;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Filtering, sorting and select options of the question listings (the
 * teacher's own bank and the admin browse share it; the caller scopes the query).
 */
final readonly class QuestionListing
{
    private const int TAG_OPTIONS_LIMIT = 1000;

    /**
     * @param  array{category: string|null, tags: list<string>, type: QuestionType|null, difficulty: Difficulty|null, archived: string|null}  $filters  validated (QuestionIndexRequest::filters())
     * @param  array<string, mixed>  $params  validated listing parameters
     * @param  Builder<Question>  $query  already scoped by the caller
     * @return array{paginator: LengthAwarePaginator<int, Question>, filters: array<string, mixed>}
     */
    public function paginate(array $filters, array $params, Builder $query, bool $withOwner = false): array
    {
        $listing = new Listing(
            searchable: ['body_text'],
            sortable: [
                'updated_at' => 'updated_at',
                'created_at' => 'created_at',
                // easy < medium < hard (constant SQL; the direction is whitelisted asc/desc).
                'difficulty' => fn (QueryBuilder $query, string $direction) => $query->orderByRaw("FIELD(difficulty, 'easy', 'medium', 'hard') {$direction}"),
            ],
            defaultSort: 'updated_at',
        );

        $query
            ->with(['category', 'tags', ...($withOwner ? ['owner'] : [])])
            ->withCount('tests')
            ->when($filters['archived'] === 'only', fn (Builder $query) => $query->onlyTrashed())
            ->when($filters['archived'] === 'with', fn (Builder $query) => $query->withTrashed())
            ->when($filters['type'], fn (Builder $query, QuestionType $type) => $query->where('type', $type->value))
            ->when($filters['difficulty'], fn (Builder $query, Difficulty $difficulty) => $query->where('difficulty', $difficulty->value))
            ->when($filters['category'], fn (Builder $query, string $ulid) => $query->whereIn('category_id', $this->categoryIds($ulid)))
            ->when($filters['tags'] !== [], fn (Builder $query) => $query->whereHas('tags', fn (Builder $tags) => $tags->whereIn('ulid', $filters['tags'])));

        return [
            'paginator' => $listing->paginate($query, $params),
            'filters' => [
                ...$listing->state($params),
                'category' => $filters['category'],
                'tags' => $filters['tags'],
                'type' => $filters['type']?->value,
                'difficulty' => $filters['difficulty']?->value,
                'archived' => $filters['archived'],
            ],
        ];
    }

    /**
     * Select options for filters and the editor.
     *
     * @return array<string, mixed>
     */
    public static function options(): array
    {
        $label = fn (QuestionType|Difficulty|MultipleChoiceScoring $case): array => ['value' => $case->value, 'label' => $case->label()];

        return [
            'categories' => app(CategoryTree::class)->get(),
            'tags' => Tag::query()->orderBy('name')->limit(self::TAG_OPTIONS_LIMIT)->get(['ulid', 'name'])
                ->map(fn (Tag $tag): array => ['value' => $tag->ulid, 'label' => $tag->name])->all(),
            'types' => array_map($label, QuestionType::cases()),
            'difficulties' => array_map($label, Difficulty::cases()),
            'scorings' => array_map($label, MultipleChoiceScoring::cases()),
        ];
    }

    /**
     * A category and its subcategories (filtering by a parent includes children).
     *
     * @return list<int>
     */
    private function categoryIds(string $ulid): array
    {
        $category = Category::query()->where('ulid', $ulid)->with('children')->first();

        if ($category === null) {
            return [];
        }

        return [$category->id, ...$category->children->modelKeys()];
    }
}
