<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Academy\CourseActions;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CourseIndexRequest;
use App\Http\Requests\Api\CourseWriteRequest;
use App\Http\Requests\Api\DeleteCourseRequest;
use App\Http\Requests\Api\PublicationCourseRequest;
use App\Http\Resources\Api\CourseResource;
use App\Models\Course;
use App\Queries\CourseCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class CourseController extends Controller
{
    public function index(CourseIndexRequest $request, CourseCatalog $catalog): AnonymousResourceCollection
    {
        return CourseResource::collection($catalog->forUser($request->user(), $request->validated())
            ->paginate((int) $request->validated('per_page', 20))->appends($request->validated()));
    }

    public function store(CourseWriteRequest $request, CourseActions $actions): JsonResponse
    {
        $course = $actions->save($request->user(), null, $request->validated());

        return (new CourseResource($course->refresh()->load('teacher')))->response()->setStatusCode(201);
    }

    public function show(Request $request, Course $course): CourseResource
    {
        Gate::authorize('view', $course);
        $student = $request->user()->hasRole(UserRole::Student);
        $course->load('teacher')->loadCount(['lessons' => fn (Builder $q) => $q->when($student, fn (Builder $q) => $q->where('is_published', true)),
            'enrollments' => fn (Builder $q) => $q->active()]);

        return new CourseResource($course);
    }

    public function update(CourseWriteRequest $request, Course $course, CourseActions $actions): CourseResource
    {
        return new CourseResource($actions->save($request->user(), $course, $request->validated())->load('teacher'));
    }

    public function publish(PublicationCourseRequest $request, Course $course, CourseActions $actions): CourseResource
    {
        $actions->publish($request->user(), $course, $request->boolean('published'));

        return new CourseResource($course->refresh()->load('teacher'));
    }

    public function destroy(DeleteCourseRequest $request, Course $course, CourseActions $actions): Response
    {
        $actions->delete($request->user(), $course);

        return response()->noContent();
    }
}
