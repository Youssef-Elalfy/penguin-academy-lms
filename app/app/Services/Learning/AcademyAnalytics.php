<?php

namespace App\Services\Learning;

use App\Enums\UserRole;
use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Builder;

class AcademyAnalytics
{
    public function __construct(private ProgressService $progress) {}

    public function enrollments(Builder $courses): Builder
    {
        return Enrollment::query()->active()->whereIn('enrollments.course_id', (clone $courses)->select('courses.id')->reorder()->toBase());
    }

    public function totals(Builder $courses): array
    {
        $enrollments = $this->enrollments($courses);

        return [
            ...$this->progress->totals($enrollments),
            'active_students' => (clone $enrollments)->whereHas('student', fn (Builder $q) => $q->where('role', UserRole::Student)->where('is_active', true))->distinct()->count('student_id'),
            'published_courses' => (clone $courses)->published()->count(),
            'draft_courses' => (clone $courses)->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '>', now()))->count(),
        ];
    }

    public function courses(Builder $courses): Builder
    {
        $metrics = $this->progress->aggregate($this->enrollments($courses), true);

        return $courses->select('courses.*')->leftJoinSub($metrics, 'course_learning', 'course_learning.course_id', '=', 'courses.id')
            ->withCount(['lessons', 'lessons as eligible_lessons_count' => fn (Builder $q) => $q->where('is_published', true)])
            ->selectRaw('COALESCE(course_learning.current_enrollments, 0) AS enrollments_count')
            ->selectRaw('COALESCE(course_learning.completed_lessons, 0) AS completed_lessons_count')
            ->selectRaw('COALESCE(course_learning.completed_enrollments, 0) AS completed_enrollments_count')
            ->selectRaw('COALESCE(course_learning.average_progress, 0) AS average_progress')
            ->selectRaw('CASE WHEN course_learning.current_enrollments > 0 THEN ROUND(course_learning.completed_enrollments * 100.0 / course_learning.current_enrollments, 2) ELSE 0 END AS completion_rate')
            ->addSelect('course_learning.last_completed_at');
    }
}
