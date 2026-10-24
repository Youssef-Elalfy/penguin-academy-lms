<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\User;

class LessonCompletionPolicy
{
    public function create(User $user, Enrollment $enrollment, Lesson $lesson): bool
    {
        return $user->hasRole(UserRole::Student)
            && $enrollment->student_id === $user->id
            && $enrollment->withdrawn_at === null
            && $enrollment->course_id === $lesson->course_id
            && (new LessonPolicy)->view($user, $lesson);
    }

    public function delete(User $user, LessonCompletion $completion): bool
    {
        return $completion->lesson !== null && $this->create($user, $completion->enrollment, $completion->lesson);
    }

    public function view(User $user, LessonCompletion $completion): bool
    {
        return (new EnrollmentPolicy)->view($user, $completion->enrollment);
    }
}
