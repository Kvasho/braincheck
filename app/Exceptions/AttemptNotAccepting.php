<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Attempt;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The attempt no longer accepts answers or events: it has expired (past the
 * deadline plus grace) or is already finished. JSON clients get 409 with a
 * machine-readable `code`; page visits go to the result page.
 */
final class AttemptNotAccepting extends DomainException
{
    public const string EXPIRED = 'attempt_expired';

    public const string FINISHED = 'attempt_finished';

    private function __construct(public readonly string $reason, public readonly string $attemptUlid, string $message)
    {
        parent::__construct($message);
    }

    public static function expired(Attempt $attempt): self
    {
        return new self(self::EXPIRED, $attempt->ulid, __('Time is up. Your answers up to the deadline were saved.'));
    }

    public static function finished(Attempt $attempt): self
    {
        return new self(self::FINISHED, $attempt->ulid, __('This attempt has already been submitted.'));
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'code' => $this->reason,
                'message' => $this->getMessage(),
                'result_url' => route('student.attempts.result', $this->attemptUlid),
            ], 409);
        }

        Inertia::flash('toast', ['type' => 'info', 'message' => $this->getMessage()]);

        return to_route('student.attempts.result', $this->attemptUlid);
    }
}
