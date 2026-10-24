<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProgressReportRequest;
use App\Models\Course;
use App\Models\Enrollment;
use App\Services\Learning\AcademyAnalytics;
use App\Services\Learning\ProgressService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class LearningReportController extends Controller
{
    public function index(ProgressReportRequest $request, Course $course, ProgressService $progress, AcademyAnalytics $analytics): View
    {
        $filters = $request->validated();
        $query = $course->enrollments()->getQuery()->with('student');
        match ($filters['status'] ?? 'active') {
            'active' => $query->active(),
            'withdrawn' => $query->whereNotNull('withdrawn_at'),
            default => $query,
        };
        if ($q = $filters['q'] ?? null) {
            $query->whereHas('student', fn (Builder $query) => $query->where(fn (Builder $search) => $search->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%')));
        }
        $enrollments = $progress->enrollments($query)->latest('enrollments.id')->paginate(15)->withQueryString();

        return view('learning.course-report', [
            'course' => $course, 'filters' => $filters, 'enrollments' => $enrollments,
            'progress' => $progress->map($enrollments), 'metrics' => $analytics->totals(Course::whereKey($course->id)),
        ]);
    }

    public function show(ProgressReportRequest $request, Course $course, Enrollment $enrollment, ProgressService $progress): View
    {
        $enrollment->load('student');
        $lessons = $course->lessons()->where('is_published', true)
            ->withMax(['completions as completed_at' => fn (Builder $q) => $q->where('enrollment_id', $enrollment->id)], 'completed_at')->paginate(20);

        return view('learning.student-report', [
            'course' => $course, 'enrollment' => $enrollment, 'lessons' => $lessons, 'progress' => $progress->forEnrollment($enrollment),
        ]);
    }
}
