<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Attempt;
use App\Models\Certificate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A certificate for a passed, graded attempt, with issue-time snapshots.
 *
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $attempt = fn (array $attributes): Attempt => Attempt::query()->with(['user', 'test'])->whereKey($attributes['attempt_id'])->firstOrFail();

        return [
            'attempt_id' => Attempt::factory()->graded(passed: true),
            'user_id' => fn (array $attributes): int => $attempt($attributes)->user_id,
            'test_id' => fn (array $attributes): int => $attempt($attributes)->test_id,
            'verification_code' => fn (): string => bin2hex(random_bytes(Certificate::VERIFICATION_CODE_LENGTH / 2)),
            'holder_name' => fn (array $attributes): string => $attempt($attributes)->user->name,
            'test_title' => fn (array $attributes): string => $attempt($attributes)->test->title,
            'score_percent' => fn (array $attributes): string => $attempt($attributes)->score_percent ?? '100.00',
            'issued_at' => now(),
            'revoked_at' => null,
            'revoked_reason' => null,
        ];
    }

    public function revoked(string $reason = 'Issued in error.'): static
    {
        return $this->state(fn (): array => [
            'revoked_at' => now(),
            'revoked_reason' => $reason,
        ]);
    }
}
