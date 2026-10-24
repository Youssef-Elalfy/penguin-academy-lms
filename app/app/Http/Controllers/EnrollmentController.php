<?php

namespace App\Http\Controllers;

use App\Actions\Academy\EnrollmentActions;
use App\Enums\UserRole;
use App\Http\Requests\EnrollmentIndexRequest;
use App\Http\Requests\EnrollStudentRequest;
use App\Http\Requests\WithdrawEnrollmentRequest;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Learning\ProgressService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;

class EnrollmentController extends Controller
{
    public function index(EnrollmentIndexRequest $request, Course $course, ProgressService $progressService): View
    {
        $filters = $request->validated();
        $query = $this->filter($course->enrollments()->getQuery()->with('student'), $filters);
        if ($q = $filters['q'] ?? null) {
            $query->whereHas('student', fn (Builder $query) => $query->where(fn (Builder $q2) => $q2->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%')));
        }
        $enrollments = $progressService->enrollments($query)->latest('id')->paginate(15)->withQueryString();
        $progress = $progressService->map($enrollments);
        $students = User::where('role', UserRole::Student)->where('is_active', true)
            ->when($filters['student_search'] ?? null, fn (Builder $q, string $search) => $q->where(fn (Builder $q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))
            ->orderBy('name')->limit(25)->get(['id', 'name', 'email']);
        $counts = ['active' => $course->enrollments()->active()->count(), 'withdrawn' => $course->enrollments()->whereNotNull('withdrawn_at')->count()];

        return view('enrollments.roster', compact('course', 'enrollments', 'students', 'filters', 'counts', 'progress'));
    }

    public function overview(EnrollmentIndexRequest $request, ProgressService $progressService): View
    {
        $filters = $request->validated();
        $query = $this->filter(Enrollment::query()->whereHas('course')->with(['student', 'course']), $filters);
        if ($courseId = $filters['course_id'] ?? null) {
            $query->where('course_id', $courseId);
        }
        if ($q = $filters['q'] ?? null) {
            $query->whereHas('student', fn (Builder $query) => $query->where(fn (Builder $q2) => $q2->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%')));
        }

        $enrollments = $progressService->enrollments($query)->latest('id')->paginate(15)->withQueryString();

        return view('enrollments.overview', [
            'enrollments' => $enrollments, 'progress' => $progressService->map($enrollments),
            'courses' => Course::orderBy('title')->get(['id', 'title']), 'filters' => $filters,
        ]);
    }

    public function mine(EnrollmentIndexRequest $request, ProgressService $progressService): View
    {
        $filters = $request->validated();
        $query = $this->filter($request->user()->enrollments()->getQuery()->with(['course' => fn ($q) => $q->withTrashed()->with('teacher')]), $filters);
        if ($q = $filters['q'] ?? null) {
            $query->whereHas('course', fn (Builder $query) => $query->withTrashed()->where('title', 'like', '%'.$q.'%'));
        }

        $enrollments = $progressService->enrollments($query, true)->latest('id')->paginate(12)->withQueryString();

        return view('enrollments.mine', ['enrollments' => $enrollments, 'progress' => $progressService->map($enrollments), 'filters' => $filters]);
    }

    public function store(EnrollStudentRequest $request, Course $course, EnrollmentActions $actions): RedirectResponse
    {
        $id = $request->validated('student_id');
        $actions->enroll($request->user(), $course, $id === null ? null : (int) $id);

        return to_route($request->user()->hasRole(UserRole::Student) ? 'courses.show' : 'courses.enrollments.index', $course)
            ->with('status', 'Enrollment is active. Existing learning history is preserved.');
    }

    public function destroy(WithdrawEnrollmentRequest $request, Course $course, Enrollment $enrollment, EnrollmentActions $actions): RedirectResponse
    {
        $actions->withdraw($request->user(), $course, $enrollment);

        return $request->user()->hasRole(UserRole::Student)
            ? to_route('my-courses', ['status' => 'all'])->with('status', 'You have withdrawn. Your learning history is preserved.')
            : to_route('courses.enrollments.index', $course)->with('status', 'Enrollment withdrawn. Learning history is preserved.');
    }

    private function filter(Builder $query, array $filters): Builder
    {
        return match ($filters['status'] ?? 'active') {
            'active' => $query->active(),
            'withdrawn' => $query->whereNotNull('withdrawn_at'),
            default => $query,
        };
    }
}
