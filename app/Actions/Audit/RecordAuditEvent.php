<?php

declare(strict_types=1);

namespace App\Actions\Audit;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuditPropertySanitizer;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * The only writer of the audit log. Synchronous: the entry exists once handle() returns.
 *
 * Never pass secrets in $properties; AuditPropertySanitizer is a safety net, not a license.
 * IP address and user agent come from the current HTTP request. In console commands and
 * queue jobs (where no route was dispatched) they are stored as null.
 */
final class RecordAuditEvent
{
    private const int MAX_USER_AGENT_LENGTH = 255;

    public function __construct(
        private readonly AuditPropertySanitizer $sanitizer,
        private readonly Container $container,
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     */
    public function handle(AuditAction $action, ?User $actor = null, ?Model $subject = null, array $properties = []): AuditLog
    {
        $request = $this->currentRequest();

        $log = new AuditLog([
            'action' => $action->value,
            'properties' => $this->sanitizer->sanitize($properties),
            'ip_address' => $request?->ip(),
            'user_agent' => $this->userAgent($request),
        ]);

        $log->actor()->associate($actor);

        if ($subject !== null) {
            $log->subject()->associate($subject);
        }

        $log->save();

        return $log;
    }

    /**
     * The request being handled, if any. Console and queue processes get a synthetic
     * request (127.0.0.1, "Symfony") that never had a route dispatched; it is ignored.
     */
    private function currentRequest(): ?Request
    {
        if (! $this->container->bound('request')) {
            return null;
        }

        $request = $this->container->make(Request::class);

        return $request->route() !== null ? $request : null;
    }

    private function userAgent(?Request $request): ?string
    {
        $userAgent = $request?->userAgent();

        if ($userAgent === null || $userAgent === '') {
            return null;
        }

        return mb_substr(mb_scrub($userAgent, 'UTF-8'), 0, self::MAX_USER_AGENT_LENGTH);
    }
}
