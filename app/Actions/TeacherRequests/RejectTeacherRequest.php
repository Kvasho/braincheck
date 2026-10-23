<?php

declare(strict_types=1);

namespace App\Actions\TeacherRequests;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Enums\TeacherRequestStatus;
use App\Exceptions\TeacherRequestNotAllowed;
use App\Mail\TeacherRequestReviewed;
use App\Models\TeacherAccessRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Rejects a pending request (row locked, see ApproveTeacherRequest). The student
 * can try again after the cooldown (braincheck.teacher_request_cooldown_days).
 */
final class RejectTeacherRequest
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    /**
     * @throws TeacherRequestNotAllowed
     */
    public function handle(TeacherAccessRequest $teacherRequest, User $admin, ?string $note = null): void
    {
        DB::transaction(function () use ($teacherRequest, $admin, $note): void {
            $locked = TeacherAccessRequest::query()
                ->with('user')
                ->whereKey($teacherRequest->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== TeacherRequestStatus::Pending) {
                throw TeacherRequestNotAllowed::alreadyReviewed();
            }

            $locked->forceFill([
                'status' => TeacherRequestStatus::Rejected,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);
            $locked->reviewer()->associate($admin);
            $locked->save();

            $this->recordAuditEvent->handle(AuditAction::TeacherRequestRejected, $admin, $locked, [
                'user' => $locked->user->ulid,
                'note' => $note,
            ]);

            Mail::to($locked->user)->queue((new TeacherRequestReviewed($locked))->afterCommit());
        });

        $teacherRequest->refresh();
    }
}
