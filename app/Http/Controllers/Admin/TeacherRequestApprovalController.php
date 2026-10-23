<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\TeacherRequests\ApproveTeacherRequest;
use App\Exceptions\TeacherRequestNotAllowed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewTeacherRequestRequest;
use App\Models\TeacherAccessRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Approve a teacher access request (password confirmation required by the
 * route: approving grants a role).
 */
final class TeacherRequestApprovalController extends Controller
{
    public function store(ReviewTeacherRequestRequest $request, TeacherAccessRequest $teacherRequest, ApproveTeacherRequest $approve): RedirectResponse
    {
        try {
            $approve->handle($teacherRequest, $request->user() ?? abort(403), $request->note());
        } catch (TeacherRequestNotAllowed $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Request approved. The user is now a teacher.')]);

        return back();
    }
}
