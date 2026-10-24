<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Learning\AcademyAnalytics;
use App\Services\Learning\ProgressService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route($request->user()->role->value.'.dashboard');
    }

    public function admin(AcademyAnalytics $analytics): View
    {
        Gate::authorize('viewAdminDashboard', User::class);
        $stats = [
            'teachers' => User::where('role', UserRole::Teacher)->where('is_active', true)->count(),
            'students' => User::where('role', UserRole::Student)->where('is_active', true)->count(),
            'courses' => Course::count(),
            'enrollments' => Enrollment::active()->whereHas('course')->count(),
        ];
        $teachers = User::where('role', UserRole::Teacher)->withCount('taughtCourses')->latest()->limit(5)->get();
        $students = User::where('role', UserRole::Student)->latest()->limit(5)->get();
        $learningStats = $analytics->totals(Course::query());
        $courses = $analytics->courses(Course::query())->latest('courses.id')->paginate(8);

        return view('dashboards.admin', compact('stats', 'teachers', 'students', 'learningStats', 'courses'));
    }

    public function teacher(Request $request, AcademyAnalytics $analytics): View
    {
        Gate::authorize('viewTeacherDashboard', User::class);
        $id = $request->user()->id;
        $assigned = fn (Builder $query) => $query->where('teacher_id', $id);
        $stats = [
            'courses' => $request->user()->taughtCourses()->count(),
            'lessons' => Lesson::whereHas('course', $assigned)->count(),
            'students' => Enrollment::active()->whereHas('course', $assigned)->distinct()->count('student_id'),
        ];
        $scope = Course::where('teacher_id', $id);
        $learningStats = $analytics->totals($scope);
        $courses = $analytics->courses($scope)->latest('courses.id')->paginate(8);

        return view('dashboards.teacher', compact('stats', 'courses', 'learningStats'));
    }

    public function student(Request $request, ProgressService $progressService): View
    {
        Gate::authorize('viewStudentDashboard', User::class);
        $enrollmentsQuery = $request->user()->enrollments()->getQuery()->active()->whereHas('course', fn (Builder $q) => $q->published());
        $learningStats = $progressService->totals($enrollmentsQuery);
        $stats = [
            'enrollments' => $learningStats['enrollments'],
            'completed_lessons' => $learningStats['completed_lessons'],
            'available_courses' => Course::published()->count(),
        ];
        $enrollments = $progressService->enrollments($enrollmentsQuery->with('course.teacher'), true)->latest('enrollments.id')->paginate(8);
        $progress = $progressService->map($enrollments);

        return view('dashboards.student', compact('stats', 'enrollments', 'learningStats', 'progress'));
    }
}
