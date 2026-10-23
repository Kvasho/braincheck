<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Tag;

final class StoreTagRequest extends TagRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Tag::class) === true;
    }

    protected function tag(): ?Tag
    {
        return null;
    }
}
