<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Category;

final class UpdateCategoryRequest extends CategoryRequest
{
    public function authorize(): bool
    {
        $category = $this->category();

        return $category !== null && $this->user()?->can('update', $category) === true;
    }

    protected function category(): ?Category
    {
        $category = $this->route('category');

        return $category instanceof Category ? $category : null;
    }
}
