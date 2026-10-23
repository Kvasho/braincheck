<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Actions\Users\AssignInitialRole;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Give the user a role (roles must be synced first: RolesAndPermissionsSeeder).
     */
    public function withRole(Role $role): static
    {
        return $this->afterCreating(function (User $user) use ($role): void {
            app(AssignInitialRole::class)->handle($user, $role);
        });
    }

    public function admin(): static
    {
        return $this->withRole(Role::Admin);
    }

    public function teacher(): static
    {
        return $this->withRole(Role::Teacher);
    }

    public function student(): static
    {
        return $this->withRole(Role::Student);
    }

    /**
     * Indicate that the user has been blocked.
     */
    public function blocked(string $reason = 'Test block'): static
    {
        return $this->state(fn (array $attributes) => [
            'blocked_at' => now(),
            'blocked_reason' => $reason,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
