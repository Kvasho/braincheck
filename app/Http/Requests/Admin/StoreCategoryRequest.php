<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Category;

final class StoreCategoryRequest extends CategoryRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Category::class) === true;
    }

    protected function category(): ?Category
    {
        return null;
    }
}
