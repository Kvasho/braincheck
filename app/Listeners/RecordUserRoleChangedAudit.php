<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Events\UserRoleChanged;

/**
 * Audits role changes. UserRoleChanged is dispatched after commit, so a rolled-back
 * change is never recorded.
 */
final class RecordUserRoleChangedAudit
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(UserRoleChanged $event): void
    {
        $this->recordAuditEvent->handle(AuditAction::UserRoleChanged, $event->actor, $event->user, [
            'from' => $event->oldRole?->value,
            'to' => $event->newRole->value,
        ]);
    }
}
