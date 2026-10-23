<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Actions\TeacherRequests\SubmitTeacherRequest;
use App\Enums\TeacherRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreTeacherRequestRequest;
use App\Http\Resources\TeacherAccessRequestResource;
use App\Models\TeacherAccessRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class TeacherRequestController extends Controller
{
    public function show(Request $request): Response
    {
        Gate::authorize('create', TeacherAccessRequest::class);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $latest = $user->teacherAccessRequests()->latest('id')->first();
        $cooldownEndsAt = SubmitTeacherRequest::cooldownEndsAt($user);

        return Inertia::render('student/teacher-request', [
            'latest' => $latest === null ? null : (new TeacherAccessRequestResource($latest))->resolve($request),
            'canSubmit' => $latest?->status !== TeacherRequestStatus::Pending && $cooldownEndsAt === null,
            'cooldownEndsAt' => $cooldownEndsAt?->toIso8601String(),
            'maxLength' => (int) config('braincheck.teacher_request_message_max'),
        ]);
    }

    public function store(StoreTeacherRequestRequest $request, SubmitTeacherRequest $submitTeacherRequest): RedirectResponse
    {
        $submitTeacherRequest->handle($request->user() ?? abort(403), $request->message());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Request sent. We will email you once it has been reviewed.')]);

        return to_route('student.teacher-request.show');
    }
}
