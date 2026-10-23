<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Enums\Role;
use App\Exceptions\UserBlockNotAllowed;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Blocks an account and cuts off every way back in, immediately:
 * - all of the user's database sessions are deleted;
 * - the remember token is rotated, so an existing "remember me" cookie is dead;
 * - in-flight requests are handled by EnsureUserIsNotBlocked.
 *
 * Can't block yourself, and can't block the last active administrator.
 */
final class BlockUser
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    /**
     * @throws UserBlockNotAllowed
     */
    public function handle(User $user, string $reason, User $actor): void
    {
        if ($actor->is($user)) {
            throw UserBlockNotAllowed::cannotBlockSelf();
        }

        DB::transaction(function () use ($user, $reason, $actor): void {
            // Lock every active admin row (same pattern as ChangeUserRole), so two
            // concurrent blocks can't both pass the "last active admin" check.
            $activeAdminIds = User::query()
                ->role(Role::Admin->value)
                ->whereNull('blocked_at')
                ->lockForUpdate()
                ->pluck('id');

            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isBlocked()) {
                return;
            }

            if ($activeAdminIds->contains($locked->getKey()) && $activeAdminIds->count() <= 1) {
                throw UserBlockNotAllowed::lastAdmin();
            }

            $locked->forceFill([
                'blocked_at' => now(),
                'blocked_reason' => $reason,
                'remember_token' => Str::random(60),
            ])->save();

            DB::table((string) config('session.table', 'sessions'))
                ->where('user_id', $locked->getKey())
                ->delete();

            $this->recordAuditEvent->handle(AuditAction::UserBlocked, $actor, $locked, ['reason' => $reason]);
        });

        $user->refresh();
    }
}
