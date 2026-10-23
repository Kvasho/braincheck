<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Domain\Groups\GroupSettings;
use App\Enums\InviteExpiry;
use App\Support\AppTime;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared rules of the group create / edit form.
 */
abstract class GroupRequest extends FormRequest
{
    public const int NAME_MIN = 3;

    public const int NAME_MAX = 100;

    public const int DESCRIPTION_MAX = 1000;

    /**
     * The expiry choices this form accepts.
     *
     * @return list<InviteExpiry>
     */
    abstract protected function expiryChoices(): array;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:'.self::NAME_MIN, 'max:'.self::NAME_MAX],
            'description' => ['nullable', 'string', 'max:'.self::DESCRIPTION_MAX],
            'joining_enabled' => ['required', 'boolean'],
            'invite_expiry' => ['required', Rule::enum(InviteExpiry::class)->only($this->expiryChoices())],
            'invite_expires_at' => ['nullable', 'required_if:invite_expiry,'.InviteExpiry::Custom->value, 'date_format:'.AppTime::INPUT_FORMAT],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || $this->expiry() !== InviteExpiry::Custom) {
                    return;
                }

                if (! AppTime::fromLocalInput($this->string('invite_expires_at')->toString())->isFuture()) {
                    $validator->errors()->add('invite_expires_at', __('The expiry must be in the future.'));
                }
            },
        ];
    }

    public function settings(): GroupSettings
    {
        $expiry = $this->expiry();
        $description = trim($this->string('description')->toString());

        return new GroupSettings(
            name: trim($this->string('name')->toString()),
            description: $description === '' ? null : $description,
            joiningEnabled: $this->boolean('joining_enabled'),
            inviteExpiresAt: $expiry === InviteExpiry::Custom
                ? AppTime::fromLocalInput($this->string('invite_expires_at')->toString())
                : $expiry->presetFrom(CarbonImmutable::now()),
            changeExpiry: $expiry !== InviteExpiry::Keep,
        );
    }

    private function expiry(): InviteExpiry
    {
        return $this->enum('invite_expiry', InviteExpiry::class) ?? InviteExpiry::Never;
    }
}
