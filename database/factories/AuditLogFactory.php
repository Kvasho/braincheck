<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_id' => User::factory()->admin(),
            'action' => 'user.role_changed',
            'subject_type' => null,
            'subject_id' => null,
            'properties' => ['from' => 'student', 'to' => 'teacher'],
            'ip_address' => fake()->ipv4(),
            'user_agent' => 'Mozilla/5.0 (BrainCheck factory)',
        ];
    }

    public function about(Model $subject): static
    {
        return $this->state(fn (): array => [
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
        ]);
    }
}
