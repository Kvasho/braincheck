<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Listing;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base request for index pages: the shared listing parameters plus the page's own
 * filters (filterRules()). Sort and page size are deliberately loose here: Listing
 * whitelists and clamps them, so a bad value falls back instead of failing.
 */
abstract class ListingRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    abstract protected function filterRules(): array;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:'.Listing::MAX_SEARCH_LENGTH],
            'sort' => ['nullable', 'string', 'max:50'],
            'direction' => ['nullable', 'string', 'max:50'],
            'per_page' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            ...$this->filterRules(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function listingParams(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return $validated;
    }
}
