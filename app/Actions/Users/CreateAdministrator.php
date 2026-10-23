<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Creates a verified administrator. Only reachable from the CLI
 * (`php artisan braincheck:make-admin`): no web route can create an admin.
 */
final class CreateAdministrator
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private readonly AssignInitialRole $assignInitialRole) {}

    /**
     * @param  array{name: string, email: string, password: string, password_confirmation: string}  $input
     *
     * @throws ValidationException
     */
    public function handle(array $input): User
    {
        $validated = Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();

            $this->assignInitialRole->handle($user, Role::Admin);

            return $user;
        });
    }
}
