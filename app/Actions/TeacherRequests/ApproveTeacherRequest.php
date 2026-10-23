<?php

declare(strict_types=1);

namespace App\Actions\TeacherRequests;

use App\Actions\Audit\RecordAuditEvent;
use App\Actions\Users\ChangeUserRole;
use App\Enums\AuditAction;
use App\Enums\Role;
use App\Enums\TeacherRequestStatus;
use App\Exceptions\TeacherRequestNotAllowed;
use App\Mail\TeacherRequestReviewed;
use App\Models\TeacherAccessRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Approves a pending request and makes the requester a Teacher.
 *
 * The request row is locked, so two admins can't both process it: the second
 * one finds it already reviewed and gets an error, never a second role change.
 * The queued email is dispatched only after the transaction commits.
 */
final class ApproveTeacherRequest
{
    public function __construct(
        private readonly ChangeUserRole $changeUserRole,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

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

            $requester = $locked->user;

            if ($requester->roleOrNull() !== Role::Student) {
                throw TeacherRequestNotAllowed::requesterIsNotAStudent();
            }

            $locked->forceFill([
                'status' => TeacherRequestStatus::Approved,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);
            $locked->reviewer()->associate($admin);
            $locked->save();

            $this->changeUserRole->handle($requester, Role::Teacher, $admin);

            $this->recordAuditEvent->handle(AuditAction::TeacherRequestApproved, $admin, $locked, [
                'user' => $requester->ulid,
                'note' => $note,
            ]);

            Mail::to($requester)->queue((new TeacherRequestReviewed($locked))->afterCommit());
        });

        $teacherRequest->refresh();
    }
}
