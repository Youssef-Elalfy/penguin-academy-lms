<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;

class LessonPolicy
{
    public function viewAny(User $user, Course $course): bool
    {
        if (! $user->is_active || $course->trashed()) {
            return false;
        }
        if ((new CoursePolicy)->update($user, $course)) {
            return true;
        }

        return $user->hasRole(UserRole::Student) && $course->isPublished()
            && $course->enrollments()->active()->where('student_id', $user->id)->exists();
    }

    public function view(User $user, Lesson $lesson): bool
    {
        $course = $lesson->course;
        if (! $user->is_active || $lesson->trashed() || ! $course || $course->trashed()) {
            return false;
        }
        if ($user->hasRole(UserRole::Admin) || ($user->hasRole(UserRole::Teacher) && $course->teacher_id === $user->id)) {
            return true;
        }

        return $user->hasRole(UserRole::Student) && $lesson->is_published && $course->isPublished()
            && $course->enrollments()->active()->where('student_id', $user->id)->exists();
    }

    public function create(User $user, Course $course): bool
    {
        return (new CoursePolicy)->update($user, $course);
    }

    public function update(User $user, Lesson $lesson): bool
    {
        return ! $lesson->trashed() && $lesson->course !== null && $this->create($user, $lesson->course);
    }

    public function delete(User $user, Lesson $lesson): bool
    {
        return $this->update($user, $lesson);
    }
}
