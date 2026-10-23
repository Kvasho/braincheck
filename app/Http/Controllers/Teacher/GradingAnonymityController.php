<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Actions\Audit\RecordAuditEvent;
use App\Domain\Grading\AnonymousLabels;
use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\GradingAnonymityRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Anonymous grading mode (on by default, kept in the session). Turning it
 * off shows all names, so it is audited like a reveal. Always redirects to
 * the queue (never to a header-supplied target).
 */
final class GradingAnonymityController extends Controller
{
    public function update(GradingAnonymityRequest $request, RecordAuditEvent $recordAuditEvent): RedirectResponse
    {
        $request->session()->put(AnonymousLabels::SESSION_KEY, $request->anonymous());

        if (! $request->anonymous()) {
            $recordAuditEvent->handle(AuditAction::GradingIdentityRevealed, $request->user(), null, ['scope' => 'all']);
        }

        return to_route('teacher.grading.index');
    }
}
