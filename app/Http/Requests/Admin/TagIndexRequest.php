<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\ListingRequest;
use App\Models\Tag;

final class TagIndexRequest extends ListingRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Tag::class) === true;
    }

    protected function filterRules(): array
    {
        return [];
    }
}
