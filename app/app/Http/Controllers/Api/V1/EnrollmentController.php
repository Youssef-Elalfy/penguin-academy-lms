<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Academy\EnrollmentActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\EnrollmentIndexRequest;
use App\Http\Requests\Api\EnrollStudentRequest;
use App\Http\Requests\Api\WithdrawEnrollmentRequest;
use App\Http\Resources\Api\EnrollmentResource;
use App\Models\Enrollment;
use App\Queries\EnrollmentDirectory;
use App\Services\Learning\ProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnrollmentController extends Controller
{
    public function index(EnrollmentIndexRequest $request, EnrollmentDirectory $directory, ProgressService $progress): AnonymousResourceCollection
    {
        return EnrollmentResource::collection($progress->enrollments($directory->forUser($request->user(), $request->validated()), true)
            ->paginate($request->pageSize())->appends($request->validated()));
    }

    public function store(EnrollStudentRequest $request, EnrollmentActions $actions, ProgressService $progress): JsonResponse
    {
        $enrollment = $actions->enroll($request->user(), $request->course(), $request->validated('student_id'));
        $created = $enrollment->wasRecentlyCreated;
        $enrollment = $progress->enrollments(Enrollment::whereKey($enrollment->id), true)->with(['course', 'student'])->firstOrFail();

        return (new EnrollmentResource($enrollment))->response()->setStatusCode($created ? 201 : 200);
    }

    public function destroy(WithdrawEnrollmentRequest $request, Enrollment $enrollment, EnrollmentActions $actions): Response
    {
        Gate::authorize('delete', $enrollment);
        $actions->withdraw($request->user(), $enrollment->course, $enrollment);

        return response()->noContent();
    }
}
