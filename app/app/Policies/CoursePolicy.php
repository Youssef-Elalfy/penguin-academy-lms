<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Course $course): bool
    {
        return $user->is_active && ! $course->trashed() && (
            $user->hasRole(UserRole::Admin)
            || ($user->hasRole(UserRole::Teacher) && $course->teacher_id === $user->id)
            || ($user->hasRole(UserRole::Student) && $course->isPublished())
        );
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Teacher);
    }

    public function update(User $user, Course $course): bool
    {
        return ! $course->trashed() && ($user->hasRole(UserRole::Admin)
            || ($user->hasRole(UserRole::Teacher) && $course->teacher_id === $user->id));
    }

    public function delete(User $user, Course $course): bool
    {
        return $user->hasRole(UserRole::Admin) && ! $course->trashed();
    }

    public function assignTeacher(User $user, Course $course): bool
    {
        return $this->delete($user, $course);
    }

    public function publish(User $user, Course $course): bool
    {
        return $this->update($user, $course);
    }
}
