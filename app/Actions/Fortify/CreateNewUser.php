<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Actions\Users\AssignInitialRole;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

final class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private readonly AssignInitialRole $assignInitialRole) {}

    /**
     * Validate and create a newly registered user.
     *
     * Self-registered users are always Students. The role is never read from the
     * request: only the validated name, email and password are used.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
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

            $this->assignInitialRole->handle($user, Role::Student);

            return $user;
        });
    }
}
