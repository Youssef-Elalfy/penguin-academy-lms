<?php

namespace App\Queries;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class EnrollmentDirectory
{
    public function forUser(User $user, array $filters): Builder
    {
        $query = Enrollment::query()->whereHas('course')->with(['course:id,title,published_at', 'student:id,name,email']);
        if ($user->hasRole(UserRole::Student)) {
            $query->where('student_id', $user->id);
        } elseif ($user->hasRole(UserRole::Teacher)) {
            $query->whereHas('course', fn (Builder $q) => $q->where('teacher_id', $user->id));
        }
        if (($filters['status'] ?? 'active') === 'active') {
            $query->active();
        } elseif ($filters['status'] === 'withdrawn') {
            $query->whereNotNull('withdrawn_at');
        }
        foreach (['course_id', 'student_id'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return $query->orderByDesc('id');
    }
}
