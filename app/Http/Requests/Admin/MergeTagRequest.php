<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Tag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Merge the route's tag into `target` (another existing tag, by ULID).
 */
final class MergeTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tag = $this->route('tag');

        return $tag instanceof Tag && $this->user()?->can('merge', $tag) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $source = $this->route('tag');

        return [
            'target' => [
                'required', 'string', 'ulid',
                Rule::exists('tags', 'ulid'),
                Rule::notIn([$source instanceof Tag ? $source->ulid : '']),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'target.not_in' => __('A tag cannot be merged into itself.'),
        ];
    }

    public function target(): Tag
    {
        return Tag::query()->where('ulid', $this->string('target')->toString())->firstOrFail();
    }
}
