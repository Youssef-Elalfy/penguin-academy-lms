<?php

namespace App\Http\Controllers;

use App\Actions\Academy\LessonActions;
use App\Enums\UserRole;
use App\Http\Requests\DeleteLessonRequest;
use App\Http\Requests\LessonWriteRequest;
use App\Http\Requests\PublishLessonRequest;
use App\Http\Requests\ReorderLessonsRequest;
use App\Models\Course;
use App\Models\Lesson;
use App\Services\Learning\ProgressService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LessonController extends Controller
{
    public function create(Course $course): View
    {
        Gate::authorize('create', [Lesson::class, $course]);

        return view('lessons.create', ['course' => $course, 'lesson' => new Lesson]);
    }

    public function store(LessonWriteRequest $request, Course $course, LessonActions $actions): RedirectResponse
    {
        $lesson = $actions->save($request->user(), $course, null, $request->validated());

        return to_route('courses.lessons.show', [$course, $lesson])->with('status', 'Lesson created as a draft.');
    }

    public function show(Request $request, Course $course, Lesson $lesson, ProgressService $progressService): View
    {
        Gate::authorize('view', $lesson);
        $siblings = $course->lessons()->when($request->user()->hasRole(UserRole::Student), fn ($q) => $q->where('is_published', true));
        $previous = (clone $siblings)->where('position', '<', $lesson->position)->reorder('position', 'desc')->first();
        $next = (clone $siblings)->where('position', '>', $lesson->position)->first();

        $progress = null;
        $completed = false;
        if ($request->user()->hasRole(UserRole::Student)) {
            $enrollment = $course->enrollments()->active()->where('student_id', $request->user()->id)->firstOrFail();
            $progress = $progressService->forEnrollment($enrollment);
            $completed = $enrollment->completions()->where('lesson_id', $lesson->id)->exists();
        }

        return view('lessons.show', compact('course', 'lesson', 'previous', 'next', 'progress', 'completed'));
    }

    public function edit(Course $course, Lesson $lesson): View
    {
        Gate::authorize('update', $lesson);

        return view('lessons.edit', compact('course', 'lesson'));
    }

    public function update(LessonWriteRequest $request, Course $course, Lesson $lesson, LessonActions $actions): RedirectResponse
    {
        $actions->save($request->user(), $course, $lesson, $request->validated());

        return to_route('courses.lessons.show', [$course, $lesson])->with('status', 'Lesson saved.');
    }

    public function publish(PublishLessonRequest $request, Course $course, Lesson $lesson, LessonActions $actions): RedirectResponse
    {
        $actions->publish($request->user(), $course, $lesson, $request->boolean('is_published'));

        return to_route('courses.show', $course)->with('status', $request->boolean('is_published') ? 'Lesson published. Students can access it when the course is published.' : 'Lesson unpublished.');
    }

    public function destroy(DeleteLessonRequest $request, Course $course, Lesson $lesson, LessonActions $actions): RedirectResponse
    {
        $actions->delete($request->user(), $course, $lesson);

        return to_route('courses.show', $course)->with('status', 'Lesson archived. Existing completion records are preserved.');
    }

    public function order(Course $course): View
    {
        Gate::authorize('create', [Lesson::class, $course]);

        return view('lessons.order', ['course' => $course, 'lessons' => $course->lessons()->get()]);
    }

    public function reorder(ReorderLessonsRequest $request, Course $course, LessonActions $actions): RedirectResponse
    {
        $actions->reorder($request->user(), $course, $request->validated('lesson_ids'), (int) $request->validated('order_version'));

        return to_route('courses.show', $course)->with('status', 'Lesson order saved.');
    }
}
