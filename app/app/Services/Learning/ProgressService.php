<?php

namespace App\Services\Learning;

use App\Models\Enrollment;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class ProgressService
{
    /** One database row per enrollment; completion rows never enter PHP memory. */
    public function enrollments(Builder $query, bool $navigation = false): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select('enrollments.*');
        }
        $eligible = Lesson::query()->where('lessons.is_published', true)
            ->whereColumn('lessons.course_id', 'enrollments.course_id');
        $completed = DB::table('lesson_completions as progress_completion')->selectRaw('1')
            ->whereColumn('progress_completion.enrollment_id', 'enrollments.id')
            ->whereColumn('progress_completion.lesson_id', 'lessons.id')
            ->whereColumn('progress_completion.course_id', 'lessons.course_id');
        $query->addSelect([
            'eligible_lessons_count' => (clone $eligible)->selectRaw('COUNT(*)'),
            'completed_lessons_count' => (clone $eligible)->whereExists(clone $completed)->selectRaw('COUNT(*)'),
            'last_completed_at' => DB::table('lesson_completions as activity')
                ->join('lessons as activity_lesson', function ($join): void {
                    $join->on('activity_lesson.id', '=', 'activity.lesson_id')->on('activity_lesson.course_id', '=', 'activity.course_id');
                })
                ->whereColumn('activity.enrollment_id', 'enrollments.id')->whereColumn('activity.course_id', 'enrollments.course_id')
                ->where('activity_lesson.is_published', true)->whereNull('activity_lesson.deleted_at')->selectRaw('MAX(activity.completed_at)'),
        ]);
        if ($navigation) {
            $query->addSelect(['next_lesson_id' => (clone $eligible)->whereNotExists(clone $completed)
                ->whereNull('enrollments.withdrawn_at')->whereExists(DB::table('courses as available_course')->selectRaw('1')
                ->whereColumn('available_course.id', 'enrollments.course_id')->whereNull('available_course.deleted_at')
                ->whereNotNull('available_course.published_at')->where('available_course.published_at', '<=', now()))
                ->select('lessons.id')->orderBy('lessons.position')->orderBy('lessons.id')->limit(1)]);
        }

        return $query;
    }

    public function forEnrollment(Enrollment $enrollment): ProgressSummary
    {
        return $this->summary($this->enrollments(Enrollment::whereKey($enrollment->id), true)->firstOrFail());
    }

    public function summary(Enrollment $enrollment): ProgressSummary
    {
        return new ProgressSummary((int) $enrollment->completed_lessons_count, (int) $enrollment->eligible_lessons_count,
            $enrollment->next_lesson_id === null ? null : (int) $enrollment->next_lesson_id);
    }

    /** @return array<int, ProgressSummary> */
    public function map(iterable $enrollments): array
    {
        $summaries = [];
        foreach ($enrollments as $enrollment) {
            $summaries[$enrollment->id] = $this->summary($enrollment);
        }

        return $summaries;
    }

    public function aggregate(Builder $enrollments, bool $byCourse = false): QueryBuilder
    {
        $rows = $this->enrollments((clone $enrollments)->select(['enrollments.id', 'enrollments.course_id'])->reorder());
        $query = DB::query()->fromSub($rows->toBase(), 'learning_rows')
            ->selectRaw('COUNT(*) AS current_enrollments')
            ->selectRaw('COALESCE(SUM(eligible_lessons_count), 0) AS eligible_assignments')
            ->selectRaw('COALESCE(SUM(completed_lessons_count), 0) AS completed_lessons')
            ->selectRaw('COALESCE(SUM(CASE WHEN eligible_lessons_count > 0 AND completed_lessons_count = eligible_lessons_count THEN 1 ELSE 0 END), 0) AS completed_enrollments')
            ->selectRaw('COALESCE(ROUND(AVG(CASE WHEN eligible_lessons_count > 0 THEN LEAST(100, completed_lessons_count * 100.0 / eligible_lessons_count) ELSE 0 END), 2), 0) AS average_progress')
            ->selectRaw('MAX(last_completed_at) AS last_completed_at');
        if ($byCourse) {
            $query->addSelect('course_id')->groupBy('course_id');
        }

        return $query;
    }

    /** Statistics describe the explicitly supplied enrollment scope, including its zero-lesson rows. */
    public function totals(Builder $enrollments): array
    {
        $row = $this->aggregate($enrollments)->first();

        return [
            'enrollments' => (int) $row->current_enrollments,
            'eligible_assignments' => (int) $row->eligible_assignments,
            'completed_lessons' => (int) $row->completed_lessons,
            'completed_enrollments' => (int) $row->completed_enrollments,
            'average_progress' => (float) $row->average_progress,
            'completion_rate' => ProgressSummary::percentage((int) $row->completed_enrollments, (int) $row->current_enrollments),
            'last_completed_at' => $row->last_completed_at,
        ];
    }
}
