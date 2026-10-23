<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Actions\Audit\RecordAuditEvent;
use App\Domain\Groups\InviteCode;
use App\Enums\AuditAction;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * A student joins a group with its invite code.
 *
 * - The code is normalised (trim, uppercase, no spaces/dashes) before lookup.
 * - Wrong, expired, disabled and archived codes all fail with the same
 *   message, so a failure tells an attacker nothing about why.
 * - Every failure is audited (`group.join_failed`) with only the first two
 *   characters and the length of the input, never the full code. The join
 *   rate limits bound how many such events one user can cause.
 * - Joining twice is not an error (idempotent).
 */
final class JoinGroup
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    /**
     * @return array{group: Group, joined: bool} `joined` is false when already a member
     *
     * @throws ValidationException
     */
    public function handle(User $student, string $input): array
    {
        $code = InviteCode::normalize($input);

        $group = InviteCode::isWellFormed($code)
            ? Group::query()->where('invite_code', $code)->first()
            : null;

        if ($group === null || ! $group->acceptsJoins()) {
            $this->recordAuditEvent->handle(AuditAction::GroupJoinFailed, $student, null, [
                'input_prefix' => mb_substr($code, 0, 2),
                'input_length' => mb_strlen($code),
            ]);

            throw ValidationException::withMessages(['code' => self::failureMessage()]);
        }

        if ($group->members()->whereKey($student->id)->exists()) {
            return ['group' => $group, 'joined' => false];
        }

        try {
            $group->members()->attach($student->id, ['joined_at' => now()]);
            $joined = true;
        } catch (UniqueConstraintViolationException) {
            // A parallel request joined first: still a success.
            $joined = false;
        }

        return ['group' => $group, 'joined' => $joined];
    }

    /**
     * The one message for every kind of failure.
     */
    public static function failureMessage(): string
    {
        return __('This invite code does not work. Check it, or ask your teacher for a new one.');
    }
}
