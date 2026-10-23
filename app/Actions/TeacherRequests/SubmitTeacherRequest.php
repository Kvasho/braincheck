<?php

declare(strict_types=1);

namespace App\Actions\TeacherRequests;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Enums\TeacherRequestStatus;
use App\Models\TeacherAccessRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A student asks for the teacher role.
 *
 * - One pending request at a time (validated here; the DB unique "pending lock"
 *   index is the backstop against races).
 * - After a rejection, a new request is allowed only after the cooldown.
 */
final class SubmitTeacherRequest
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $user, string $message): TeacherAccessRequest
    {
        $cooldownEndsAt = self::cooldownEndsAt($user);

        if ($cooldownEndsAt !== null) {
            throw ValidationException::withMessages([
                'message' => __('You can send a new request after :date.', ['date' => $cooldownEndsAt->translatedFormat('j F Y')]),
            ]);
        }

        try {
            return DB::transaction(function () use ($user, $message): TeacherAccessRequest {
                $hasPending = $user->teacherAccessRequests()
                    ->where('status', TeacherRequestStatus::Pending->value)
                    ->lockForUpdate()
                    ->exists();

                if ($hasPending) {
                    throw self::alreadyPending();
                }

                $request = new TeacherAccessRequest(['message' => $message]);
                $request->user()->associate($user);
                $request->save();

                $this->recordAuditEvent->handle(AuditAction::TeacherRequestSubmitted, $user, $request);

                return $request;
            });
        } catch (UniqueConstraintViolationException) {
            throw self::alreadyPending();
        }
    }

    /**
     * When the student may submit again after their latest rejection, or null
     * if no cooldown applies.
     */
    public static function cooldownEndsAt(User $user): ?CarbonImmutable
    {
        $rejectedAt = $user->teacherAccessRequests()
            ->where('status', TeacherRequestStatus::Rejected->value)
            ->max('reviewed_at');

        if (! is_string($rejectedAt)) {
            return null;
        }

        $endsAt = CarbonImmutable::parse($rejectedAt)
            ->addDays((int) config('braincheck.teacher_request_cooldown_days'));

        return $endsAt->isFuture() ? $endsAt : null;
    }

    private static function alreadyPending(): ValidationException
    {
        return ValidationException::withMessages([
            'message' => __('You already have a pending request.'),
        ]);
    }
}
