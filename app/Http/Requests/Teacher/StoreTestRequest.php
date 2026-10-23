<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Models\Category;
use App\Models\Test;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Step one: title, description and category. The builder does the rest.
 */
final class StoreTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Test::class) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:'.UpdateTestRequest::TITLE_MIN, 'max:'.UpdateTestRequest::TITLE_MAX],
            'description' => ['nullable', 'string', 'max:'.UpdateTestRequest::DESCRIPTION_MAX],
            'category' => ['nullable', 'string', 'ulid', Rule::exists('categories', 'ulid')],
        ];
    }

    public function title(): string
    {
        return trim($this->string('title')->toString());
    }

    public function description(): ?string
    {
        $description = trim($this->string('description')->toString());

        return $description === '' ? null : $description;
    }

    public function category(): ?Category
    {
        $ulid = $this->string('category')->toString();

        return $ulid === '' ? null : Category::query()->where('ulid', $ulid)->first();
    }
}
