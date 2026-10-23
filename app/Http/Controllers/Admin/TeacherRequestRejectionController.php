<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\TeacherRequests\RejectTeacherRequest;
use App\Exceptions\TeacherRequestNotAllowed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewTeacherRequestRequest;
use App\Models\TeacherAccessRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class TeacherRequestRejectionController extends Controller
{
    public function store(ReviewTeacherRequestRequest $request, TeacherAccessRequest $teacherRequest, RejectTeacherRequest $reject): RedirectResponse
    {
        try {
            $reject->handle($teacherRequest, $request->user() ?? abort(403), $request->note());
        } catch (TeacherRequestNotAllowed $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Request rejected.')]);

        return back();
    }
}
