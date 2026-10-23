<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * `parent`: the level to reorder (null = top level). `order`: every sibling's
 * ULID in the new order. ReorderCategories checks the set is exactly the level.
 */
final class ReorderCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reorder', new Category) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'parent' => ['nullable', 'string', 'ulid'],
            'order' => ['required', 'array', 'min:1', 'max:500'],
            'order.*' => ['required', 'string', 'ulid', 'distinct'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->filled('parent') && $this->parentCategory() === null) {
                $validator->errors()->add('parent', __('The selected parent category does not exist.'));
            }
        }];
    }

    public function parentCategory(): ?Category
    {
        $ulid = $this->string('parent')->toString();

        return $ulid === '' ? null : Category::query()->where('ulid', $ulid)->first();
    }

    /**
     * @return list<string>
     */
    public function order(): array
    {
        /** @var list<string> $order */
        $order = array_values($this->validated('order'));

        return $order;
    }
}
