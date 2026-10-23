<?php

declare(strict_types=1);

namespace App\Domain\Attempts;

use Illuminate\Http\Request;

/**
 * Who is taking the attempt, as far as the server can tell: IP, user agent
 * (truncated) and the SHA-256 of the session ID (never the ID itself).
 */
final readonly class AttemptClient
{
    public const int USER_AGENT_MAX = 255;

    public function __construct(
        public ?string $ip,
        public ?string $userAgent,
        public ?string $sessionHash,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $userAgent = $request->userAgent();

        return new self(
            ip: $request->ip(),
            userAgent: $userAgent === null ? null : mb_substr($userAgent, 0, self::USER_AGENT_MAX),
            sessionHash: $request->hasSession() ? hash('sha256', $request->session()->getId()) : null,
        );
    }
}
