<?php

namespace App\Http\Controllers;

use App\Actions\Academy\CourseActions;
use App\Enums\UserRole;
use App\Http\Requests\AssignTeacherRequest;
use App\Http\Requests\CourseIndexRequest;
use App\Http\Requests\CourseWriteRequest;
use App\Http\Requests\DeleteCourseRequest;
use App\Http\Requests\PublishCourseRequest;
use App\Models\Course;
use App\Models\User;
use App\Queries\CourseCatalog;
use App\Services\Learning\ProgressService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class CourseController extends Controller
{
    public function index(CourseIndexRequest $request, CourseCatalog $catalog): View
    {
        $filters = $request->validated();
        $student = $request->user()->hasRole(UserRole::Student);
        $query = $catalog->forUser($request->user(), $filters);
        $courses = $query->paginate(12)->withQueryString();
        $teachers = $request->user()->hasRole(UserRole::Admin) ? User::where('role', UserRole::Teacher)->orderBy('name')->get(['id', 'name']) : collect();

        return view('courses.index', compact('courses', 'teachers', 'filters', 'student'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Course::class);

        return view('courses.create', ['course' => new Course, 'teachers' => $this->teachers($request)]);
    }

    public function store(CourseWriteRequest $request, CourseActions $actions): RedirectResponse
    {
        $course = $actions->save($request->user(), null, $request->validated());

        return to_route('courses.show', $course)->with('status', 'Course created as a draft.');
    }

    public function show(Request $request, Course $course, ProgressService $progressService): View
    {
        Gate::authorize('view', $course);
        $manage = $request->user()->can('update', $course);
        $course->load('teacher')->loadCount(['enrollments' => fn (Builder $q) => $q->active()]);
        $enrollment = $request->user()->hasRole(UserRole::Student) ? $course->enrollments()->where('student_id', $request->user()->id)->first() : null;
        $canRead = $manage || ($enrollment && $enrollment->withdrawn_at === null);
        $lessonQuery = $course->lessons()->when(! $manage, fn (Builder $q) => $q->where('is_published', true));
        if ($enrollment) {
            $lessonQuery->withExists(['completions as is_completed' => fn (Builder $q) => $q->where('enrollment_id', $enrollment->id)]);
        }
        $lessons = $lessonQuery->paginate(15);
        $progress = $enrollment ? $progressService->forEnrollment($enrollment) : null;

        return view('courses.show', ['progress' => $progress, 'course' => $course, 'lessons' => $lessons, 'manage' => $manage, 'enrollment' => $enrollment, 'canRead' => $canRead, 'teachers' => $this->teachers($request)]);
    }

    public function edit(Request $request, Course $course): View
    {
        Gate::authorize('update', $course);

        return view('courses.edit', ['course' => $course, 'teachers' => $this->teachers($request)]);
    }

    public function update(CourseWriteRequest $request, Course $course, CourseActions $actions): RedirectResponse
    {
        $actions->save($request->user(), $course, $request->validated());

        return to_route('courses.show', $course)->with('status', 'Course information saved.');
    }

    public function publish(PublishCourseRequest $request, Course $course, CourseActions $actions): RedirectResponse
    {
        $actions->publish($request->user(), $course, $request->boolean('published'));

        return to_route('courses.show', $course)->with('status', $request->boolean('published') ? 'Course published.' : 'Course unpublished. Enrollment history is preserved.');
    }

    public function assign(AssignTeacherRequest $request, Course $course, CourseActions $actions): RedirectResponse
    {
        $id = $request->validated('teacher_id');
        $actions->assign($request->user(), $course, $id === null ? null : (int) $id);

        return to_route('courses.show', $course)->with('status', 'Teacher assignment saved.');
    }

    public function destroy(DeleteCourseRequest $request, Course $course, CourseActions $actions): RedirectResponse
    {
        $actions->delete($request->user(), $course);

        return to_route('courses.index')->with('status', 'Course archived. Lessons and learning history are preserved.');
    }

    private function teachers(Request $request): Collection
    {
        return $request->user()->hasRole(UserRole::Admin)
            ? User::where('role', UserRole::Teacher)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email'])
            : collect();
    }
}
