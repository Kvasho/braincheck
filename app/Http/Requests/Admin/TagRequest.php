<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Tag;
use App\Support\TagSlug;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared rules for creating and editing a tag: unique name and slug.
 */
abstract class TagRequest extends FormRequest
{
    public const int NAME_MAX = 50;

    abstract protected function tag(): ?Tag;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.self::NAME_MAX, Rule::unique('tags', 'name')->ignore($this->tag()?->id)],
            'slug' => [
                'nullable', 'string', 'max:'.TagSlug::MAX_LENGTH, 'regex:'.TagSlug::PATTERN,
                Rule::unique('tags', 'slug')->ignore($this->tag()?->id),
            ],
        ];
    }

    /**
     * Trimmed before validation, so " Algebra " and "Algebra" collide.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'slug' => is_string($this->input('slug')) && trim($this->input('slug')) !== '' ? trim($this->input('slug')) : null,
        ]);
    }

    public function name(): string
    {
        return $this->string('name')->toString();
    }

    public function slug(): ?string
    {
        $slug = $this->string('slug')->toString();

        return $slug === '' ? null : $slug;
    }
}
