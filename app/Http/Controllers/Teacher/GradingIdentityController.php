<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\Attempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Reveals the student of one attempt during anonymous grading (JSON). Every
 * reveal is audited (`grading.identity_revealed`).
 */
final class GradingIdentityController extends Controller
{
    public function store(Request $request, Attempt $attempt, RecordAuditEvent $recordAuditEvent): JsonResponse
    {
        Gate::authorize('grade', $attempt);

        $attempt->load('user:id,name');
        $recordAuditEvent->handle(AuditAction::GradingIdentityRevealed, $request->user(), $attempt, ['scope' => 'attempt']);

        return response()->json(['name' => $attempt->user->name])->header('Cache-Control', 'no-store, private');
    }
}
