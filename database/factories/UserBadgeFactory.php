<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Badge;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserBadge>
 */
class UserBadgeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'badge' => fake()->randomElement(Badge::cases()),
            'awarded_at' => now(),
        ];
    }

    public function badge(Badge $badge): static
    {
        return $this->state(fn (): array => ['badge' => $badge]);
    }
}
