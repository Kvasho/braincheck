<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\XpReason;
use App\Models\User;
use App\Models\XpTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * XP ledger entries. Real awards always carry a source (see forSource()),
 * which is what makes them idempotent.
 *
 * @extends Factory<XpTransaction>
 */
class XpTransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'amount' => fake()->randomElement([10, 20, 50]),
            'reason' => XpReason::AttemptPassed,
            'source_type' => null,
            'source_id' => null,
        ];
    }

    public function forSource(Model $source): static
    {
        return $this->state(fn (): array => [
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
        ]);
    }
}
