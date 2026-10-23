<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Tag;

final class UpdateTagRequest extends TagRequest
{
    public function authorize(): bool
    {
        $tag = $this->tag();

        return $tag !== null && $this->user()?->can('update', $tag) === true;
    }

    protected function tag(): ?Tag
    {
        $tag = $this->route('tag');

        return $tag instanceof Tag ? $tag : null;
    }
}
