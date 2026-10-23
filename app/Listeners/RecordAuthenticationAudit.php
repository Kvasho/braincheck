<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Laravel\Fortify\Events\PasswordUpdatedViaController;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Fortify;

/**
 * Audits authentication events (Laravel auth + Fortify). Auto-discovered for every
 * event in the handle() union. Runs synchronously, inside the request that caused it.
 *
 * Two-factor counts as enabled once it is *confirmed* (Fortify `confirm` is on);
 * merely generating a secret does not protect the account yet.
 */
final class RecordAuthenticationAudit
{
    private const int MAX_EMAIL_LENGTH = 255;

    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(
        Login|Failed|Logout|PasswordReset|PasswordUpdatedViaController|TwoFactorAuthenticationConfirmed|TwoFactorAuthenticationDisabled|RecoveryCodesGenerated $event,
    ): void {
        if ($event instanceof Failed) {
            $this->recordFailedLogin($event);

            return;
        }

        $user = $event->user instanceof User ? $event->user : null;

        [$action, $properties] = match (true) {
            $event instanceof Login => [AuditAction::Login, ['guard' => $event->guard, 'persistent' => $event->remember]],
            $event instanceof Logout => [AuditAction::Logout, ['guard' => $event->guard]],
            $event instanceof PasswordReset => [AuditAction::PasswordReset, []],
            $event instanceof PasswordUpdatedViaController => [AuditAction::PasswordChanged, []],
            $event instanceof TwoFactorAuthenticationConfirmed => [AuditAction::TwoFactorEnabled, []],
            $event instanceof TwoFactorAuthenticationDisabled => [AuditAction::TwoFactorDisabled, []],
            $event instanceof RecoveryCodesGenerated => [AuditAction::RecoveryCodesRegenerated, []],
        };

        $this->recordAuditEvent->handle($action, $user, $user, $properties);
    }

    /**
     * Only the attempted email is kept. The credentials array also holds the
     * password, so it is never passed on as a whole.
     */
    private function recordFailedLogin(Failed $event): void
    {
        $email = $event->credentials[Fortify::username()] ?? null;

        $this->recordAuditEvent->handle(
            AuditAction::LoginFailed,
            null,
            $event->user instanceof User ? $event->user : null,
            [
                'guard' => $event->guard,
                'email' => is_string($email) ? mb_substr($email, 0, self::MAX_EMAIL_LENGTH) : null,
            ],
        );
    }
}
