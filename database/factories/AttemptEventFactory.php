<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AntiCheatEvent;
use App\Models\Attempt;
use App\Models\AttemptEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttemptEvent>
 */
class AttemptEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attempt_id' => Attempt::factory(),
            'type' => fake()->randomElement(AntiCheatEvent::cases()),
            'occurred_at' => now()->subMinutes(fake()->numberBetween(1, 15)),
            'meta' => null,
        ];
    }
}
