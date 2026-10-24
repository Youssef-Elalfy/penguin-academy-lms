<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Academy\LessonActions;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DeleteLessonRequest;
use App\Http\Requests\Api\LessonIndexRequest;
use App\Http\Requests\Api\LessonWriteRequest;
use App\Http\Requests\Api\OrderLessonsRequest;
use App\Http\Requests\Api\PublicationLessonRequest;
use App\Http\Resources\Api\LessonResource;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class LessonController extends Controller
{
    public function index(LessonIndexRequest $request, Course $course): AnonymousResourceCollection
    {
        $lessons = $course->lessons()->select(['id', 'course_id', 'title', 'position', 'is_published', 'created_at', 'updated_at']);
        if ($request->user()->hasRole(UserRole::Student) || $request->validated('status') === 'published') {
            $lessons->where('is_published', true);
        } elseif ($request->validated('status') === 'draft') {
            $lessons->where('is_published', false);
        }

        return LessonResource::collection($lessons->paginate($request->pageSize())->appends($request->validated()));
    }

    public function store(LessonWriteRequest $request, Course $course, LessonActions $actions): JsonResponse
    {
        return (new LessonResource($actions->save($request->user(), $course, null, $request->validated())))->response()->setStatusCode(201);
    }

    public function show(Course $course, Lesson $lesson): LessonResource
    {
        $lesson->setRelation('course', $course);
        Gate::authorize('view', $lesson);

        return new LessonResource($lesson);
    }

    public function update(LessonWriteRequest $request, Course $course, Lesson $lesson, LessonActions $actions): LessonResource
    {
        return new LessonResource($actions->save($request->user(), $course, $lesson, $request->validated()));
    }

    public function publish(PublicationLessonRequest $request, Course $course, Lesson $lesson, LessonActions $actions): LessonResource
    {
        $actions->publish($request->user(), $course, $lesson, $request->boolean('is_published'));

        return new LessonResource($lesson->refresh());
    }

    public function reorder(OrderLessonsRequest $request, Course $course, LessonActions $actions): JsonResponse
    {
        $actions->reorder($request->user(), $course, $request->validated('lesson_ids'), (int) $request->validated('order_version'));

        return response()->json(['data' => ['lesson_order_version' => $course->refresh()->lesson_order_version]]);
    }

    public function destroy(DeleteLessonRequest $request, Course $course, Lesson $lesson, LessonActions $actions): Response
    {
        $actions->delete($request->user(), $course, $lesson);

        return response()->noContent();
    }
}
