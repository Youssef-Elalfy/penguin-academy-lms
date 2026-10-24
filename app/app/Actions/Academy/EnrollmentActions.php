<?php

namespace App\Actions\Academy;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EnrollmentActions
{
    public function __construct(private Participants $participants) {}

    public function enroll(User $actor, Course $course, ?int $studentId = null): Enrollment
    {
        return DB::transaction(function () use ($actor, $course, $studentId): Enrollment {
            $course = Course::query()->lockForUpdate()->findOrFail($course->id);
            Gate::forUser($actor)->authorize('create', [Enrollment::class, $course]);
            if ($actor->hasRole(UserRole::Student)) {
                if ($studentId !== null) {
                    throw ValidationException::withMessages(['student_id' => 'Self-enrollment always uses your signed-in account.']);
                }
                $studentId = $actor->id;
            }
            $student = $this->participants->student($studentId ?? 0);
            $enrollment = $course->enrollments()->where('student_id', $student->id)->lockForUpdate()->first();
            if (! $enrollment) {
                $enrollment = new Enrollment;
                $enrollment->course_id = $course->id;
                $enrollment->student_id = $student->id;
                $enrollment->enrolled_at = now();
                $enrollment->save();
            } elseif ($enrollment->withdrawn_at !== null) {
                $enrollment->withdrawn_at = null;
                $enrollment->save();
            }

            return $enrollment;
        }, 3);
    }

    public function withdraw(User $actor, Course $course, Enrollment $enrollment): void
    {
        DB::transaction(function () use ($actor, $course, $enrollment): void {
            $course = Course::query()->lockForUpdate()->findOrFail($course->id);
            $enrollment = $course->enrollments()->lockForUpdate()->findOrFail($enrollment->id);
            $enrollment->setRelation('course', $course);
            Gate::forUser($actor)->authorize('delete', $enrollment);
            if ($enrollment->withdrawn_at === null) {
                $enrollment->withdrawn_at = now();
                $enrollment->save();
            }
        }, 3);
    }
}
