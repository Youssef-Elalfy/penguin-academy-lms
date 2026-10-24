<?php

namespace App\Queries;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class CourseCatalog
{
    public function forUser(User $user, array $filters): Builder
    {
        $student = $user->hasRole(UserRole::Student);
        $query = Course::query()->with('teacher');
        if ($student) {
            $query->published()->withExists(['enrollments as is_enrolled' => fn (Builder $q) => $q->active()->where('student_id', $user->id)]);
        } elseif ($user->hasRole(UserRole::Teacher)) {
            $query->where('teacher_id', $user->id);
        }
        if ($q = $filters['q'] ?? null) {
            $query->where(fn (Builder $query) => $query->where('title', 'like', '%'.$q.'%')->orWhere('summary', 'like', '%'.$q.'%'));
        }
        if (($filters['status'] ?? null) === 'published') {
            $query->published();
        }
        if (($filters['status'] ?? null) === 'draft') {
            $query->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '>', now()));
        }
        if ($teacherId = $filters['teacher_id'] ?? null) {
            $query->where('teacher_id', $teacherId);
        }
        $query->withCount(['lessons' => fn (Builder $q) => $q->when($student, fn (Builder $q) => $q->where('is_published', true)),
            'enrollments' => fn (Builder $q) => $q->active()]);
        ($filters['sort'] ?? 'newest') === 'title' ? $query->orderBy('title')->orderBy('id') : $query->latest('id');

        return $query;
    }
}
