<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Lifts a block. The user signs in again normally (old sessions and the old
 * remember token were destroyed when they were blocked).
 */
final class UnblockUser
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(User $user, User $actor): void
    {
        DB::transaction(function () use ($user, $actor): void {
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isBlocked()) {
                return;
            }

            $previousReason = $locked->blocked_reason;

            $locked->forceFill([
                'blocked_at' => null,
                'blocked_reason' => null,
            ])->save();

            $this->recordAuditEvent->handle(AuditAction::UserUnblocked, $actor, $locked, ['previous_reason' => $previousReason]);
        });

        $user->refresh();
    }
}
