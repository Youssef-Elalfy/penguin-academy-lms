<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;

class EnrollmentPolicy
{
    public function viewAny(User $user, Course $course): bool
    {
        return (new CoursePolicy)->update($user, $course);
    }

    public function view(User $user, Enrollment $enrollment): bool
    {
        return $user->is_active && $enrollment->course !== null && ! $enrollment->course->trashed() && (
            $this->viewAny($user, $enrollment->course)
            || ($user->hasRole(UserRole::Student) && $enrollment->student_id === $user->id)
        );
    }

    public function create(User $user, Course $course): bool
    {
        return $course->isPublished() && ($user->hasRole(UserRole::Student) || $this->viewAny($user, $course));
    }

    public function delete(User $user, Enrollment $enrollment): bool
    {
        return $this->view($user, $enrollment);
    }
}
