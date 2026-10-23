<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use App\Support\AuditPropertySanitizer;
use Carbon\CarbonImmutable;

function sanitizeAudit(array $properties): array
{
    return (new AuditPropertySanitizer)->sanitize($properties);
}

it('strips sensitive keys', function (string $key): void {
    expect(sanitizeAudit([$key => 'secret-value', 'kept' => 1]))->toBe(['kept' => 1]);
})->with([
    'password', 'password_confirmation', 'Password', 'passwordHash',
    'token', 'api_token', 'remember_token', 'TOKEN', 'csrf_token',
    'secret', 'client_secret', 'two_factor_secret',
    'recovery_codes', 'remember', 'remember_me',
    'two_factor_confirmed_at', 'code', 'invite_code', 'verification_code',
]);

it('keeps keys that only resemble sensitive ones', function (): void {
    $properties = ['email' => 'a@example.com', 'reason' => 'spam', 'codename' => 'x', 'from' => 'student'];

    expect(sanitizeAudit($properties))->toBe($properties);
});

it('strips sensitive keys at every depth', function (): void {
    $properties = [
        'user' => [
            'name' => 'Nino',
            'password' => 'hunter2',
            'settings' => ['two_factor_secret' => 'x', 'locale' => 'ka', 'tokens' => ['a', 'b']],
        ],
        'list' => [['code' => '123456', 'id' => 1], ['id' => 2]],
    ];

    expect(sanitizeAudit($properties))->toBe([
        'user' => ['name' => 'Nino', 'settings' => ['locale' => 'ka']],
        'list' => [['id' => 1], ['id' => 2]],
    ]);
});

it('normalizes values to JSON scalars without serializing objects', function (): void {
    $user = User::factory()->make(['password' => 'hunter2']);

    expect(sanitizeAudit([
        'role' => Role::Teacher,
        'at' => CarbonImmutable::parse('2026-10-22 10:00:00', 'UTC'),
        'user' => $user,
        'nan' => NAN,
    ]))->toBe([
        'role' => 'teacher',
        'at' => '2026-10-22T10:00:00+00:00',
        'user' => '[App\Models\User]',
        'nan' => null,
    ]);
});

it('limits nesting depth', function (): void {
    $sanitized = sanitizeAudit(['a' => ['b' => ['c' => ['d' => ['e' => ['f' => 'deep']]]]]]);

    expect($sanitized)->toBe(['a' => ['b' => ['c' => ['d' => ['e' => AuditPropertySanitizer::TRUNCATED_MARKER]]]]]);
});

it('caps long strings', function (): void {
    $sanitized = sanitizeAudit(['note' => str_repeat('ა', 5000)]);

    expect(mb_strlen($sanitized['note']))->toBe(AuditPropertySanitizer::MAX_STRING_LENGTH + 1)
        ->and($sanitized['note'])->toEndWith('…');
});

it('truncates oversized payloads with a marker and keeps valid JSON', function (): void {
    $properties = [];
    for ($i = 0; $i < 100; $i++) {
        $properties["field_{$i}"] = str_repeat('x', 500);
    }

    $sanitized = sanitizeAudit($properties);
    $encoded = json_encode($sanitized, JSON_THROW_ON_ERROR);

    expect(strlen($encoded))->toBeLessThanOrEqual(AuditPropertySanitizer::MAX_BYTES)
        ->and($sanitized[AuditPropertySanitizer::TRUNCATED_MARKER])->toBeTrue()
        ->and($sanitized)->toHaveKey('field_0')
        ->and($sanitized)->not->toHaveKey('field_99');
});

it('leaves payloads within the limit untouched', function (): void {
    $properties = ['note' => str_repeat('x', 900), 'from' => 'student'];

    expect(sanitizeAudit($properties))->toBe($properties);
});

it('replaces invalid UTF-8 so the payload can always be encoded', function (): void {
    $sanitized = sanitizeAudit(["bad\xC3" => "value\xFF"]);

    expect(json_encode($sanitized))->not->toBeFalse();
});
