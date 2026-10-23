<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Domain\Results\StudentResults;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\ResultIndexRequest;
use App\Http\Resources\StudentResultRowResource;
use App\Support\Listing;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The student's results: history (scores only where visible), progress over
 * time and strengths by category, both from visible results only.
 */
final class ResultController extends Controller
{
    public function index(ResultIndexRequest $request, StudentResults $results): Response
    {
        $user = $request->user() ?? abort(403);
        $params = $request->listingParams();

        $listing = new Listing(
            searchable: [],
            sortable: ['submitted_at' => 'submitted_at'],
            defaultSort: 'submitted_at',
        );

        $visible = $results->visible($user);

        return Inertia::render('student/results/index', [
            'results' => Listing::toProps($listing->paginate(StudentResults::finished($user), $params), StudentResultRowResource::class, $request),
            'filters' => $listing->state($params),
            'progress' => StudentResults::progress($visible),
            'strengths' => StudentResults::strengths($visible),
        ]);
    }
}
