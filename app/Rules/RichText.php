<?php

declare(strict_types=1);

namespace App\Rules;

use App\Domain\Shared\RichTextDocument;
use App\Exceptions\InvalidRichText;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The value must be a valid rich text document (see RichTextDocument); with
 * $required, it must also contain some text.
 */
final readonly class RichText implements ValidationRule
{
    public function __construct(private bool $required = true) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail(__('The :attribute must be a rich text document.'));

            return;
        }

        try {
            $document = RichTextDocument::fromArray($value);
        } catch (InvalidRichText $exception) {
            $fail(__('The :attribute contains content that is not allowed.').' '.$exception->getMessage());

            return;
        }

        if ($this->required && $document->isEmpty()) {
            $fail(__('The :attribute field is required.'));
        }
    }
}
