<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Groups owned by a Teacher, with a CSPRNG invite code.
 *
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory()->teacher(),
            'name' => ucfirst(fake()->word().' '.fake()->word()).' '.fake()->numberBetween(1, 12),
            'description' => fake()->optional()->sentence(),
            'invite_code' => fn (): string => Group::generateInviteCode(),
            'invite_code_expires_at' => null,
            'joining_enabled' => true,
        ];
    }

    /**
     * Add $count new Student members.
     */
    public function withMembers(int $count = 5): static
    {
        return $this->afterCreating(function (Group $group) use ($count): void {
            $students = User::factory()->student()->count($count)->create();

            $group->members()->attach($students->pluck('id')->all(), ['joined_at' => now()]);
        });
    }

    public function expiredInvite(): static
    {
        return $this->state(fn (): array => ['invite_code_expires_at' => now()->subDay()]);
    }

    public function joiningDisabled(): static
    {
        return $this->state(fn (): array => ['joining_enabled' => false]);
    }
}
