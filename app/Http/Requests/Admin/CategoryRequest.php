<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\Shared\LocalizedName;
use App\Models\Category;
use App\Support\CategorySlug;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared rules for creating and editing a category.
 *
 * Depth is at most 2, enforced in both directions:
 * - the parent must be a top-level category (a child can't have children);
 * - a category that has children can't become a child itself.
 */
abstract class CategoryRequest extends FormRequest
{
    public const int NAME_MAX = 100;

    /**
     * The category being edited (null when creating).
     */
    abstract protected function category(): ?Category;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'array:ka,en'],
            'name.ka' => ['required', 'string', 'max:'.self::NAME_MAX],
            'name.en' => ['required', 'string', 'max:'.self::NAME_MAX],
            'slug' => [
                'nullable', 'string', 'max:'.CategorySlug::MAX_LENGTH, 'regex:'.CategorySlug::PATTERN,
                Rule::unique('categories', 'slug')->ignore($this->category()?->id),
            ],
            'parent' => ['nullable', 'string', 'ulid'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('parent')) {
                return;
            }

            $parent = $this->parentCategory();
            $category = $this->category();

            if ($parent === null) {
                $validator->errors()->add('parent', __('The selected parent category does not exist.'));
            } elseif ($parent->parent_id !== null) {
                $validator->errors()->add('parent', __('Categories can only be nested one level deep.'));
            } elseif ($category !== null && $parent->is($category)) {
                $validator->errors()->add('parent', __('A category cannot be its own parent.'));
            } elseif ($category !== null && $category->children()->exists()) {
                $validator->errors()->add('parent', __('A category with subcategories cannot become a subcategory.'));
            }
        }];
    }

    public function localizedName(): LocalizedName
    {
        return new LocalizedName(
            trim($this->string('name.ka')->toString()),
            trim($this->string('name.en')->toString()),
        );
    }

    public function slug(): ?string
    {
        $slug = trim($this->string('slug')->toString());

        return $slug === '' ? null : $slug;
    }

    public function parentCategory(): ?Category
    {
        $ulid = $this->string('parent')->toString();

        return $ulid === '' ? null : Category::query()->where('ulid', $ulid)->first();
    }
}
