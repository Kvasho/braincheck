<?php

declare(strict_types=1);

namespace App\Http\Requests\Student;

use App\Models\TeacherAccessRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class StoreTeacherRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TeacherAccessRequest::class) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'min:20', 'max:'.(int) config('braincheck.teacher_request_message_max')],
        ];
    }

    public function message(): string
    {
        return trim($this->string('message')->toString());
    }
}
