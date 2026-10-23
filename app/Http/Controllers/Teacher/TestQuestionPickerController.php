<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\QuestionIndexRequest;
use App\Http\Resources\QuestionResource;
use App\Models\Question;
use App\Support\Listing;
use App\Support\QuestionListing;
use Illuminate\Http\JsonResponse;

/**
 * The builder's question picker (JSON): the bank listing, limited to the
 * teacher's own, non-archived questions.
 */
final class TestQuestionPickerController extends Controller
{
    public function index(QuestionIndexRequest $request, QuestionListing $listing): JsonResponse
    {
        $user = $request->user() ?? abort(403);

        $result = $listing->paginate(
            [...$request->filters(), 'archived' => null],
            $request->listingParams(),
            Question::query()->ownedBy($user),
        );

        return response()->json([
            'questions' => Listing::toProps($result['paginator'], QuestionResource::class, $request),
            'filters' => $result['filters'],
        ]);
    }
}
