<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\TeacherAccessRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Approve or reject, with an optional note (sent to the student).
 */
final class ReviewTeacherRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $teacherRequest = $this->route('teacherRequest');

        return $teacherRequest instanceof TeacherAccessRequest
            && $this->user()?->can('review', $teacherRequest) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function note(): ?string
    {
        $note = trim($this->string('note')->toString());

        return $note === '' ? null : $note;
    }
}
