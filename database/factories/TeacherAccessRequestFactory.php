<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TeacherRequestStatus;
use App\Models\TeacherAccessRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherAccessRequest>
 */
class TeacherAccessRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'message' => fake()->paragraph(),
            'status' => TeacherRequestStatus::Pending,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->reviewed(TeacherRequestStatus::Approved);
    }

    public function rejected(): static
    {
        return $this->reviewed(TeacherRequestStatus::Rejected);
    }

    private function reviewed(TeacherRequestStatus $status): static
    {
        return $this->state(fn (): array => [
            'status' => $status,
            'reviewed_by' => User::factory()->admin(),
            'reviewed_at' => now(),
        ]);
    }
}
