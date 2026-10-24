<?php

namespace App\Actions\Academy;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CourseActions
{
    public function __construct(private Participants $participants) {}

    public function save(User $actor, ?Course $course, array $data): Course
    {
        try {
            return DB::transaction(function () use ($actor, $course, $data): Course {
                $course = $course ? Course::query()->lockForUpdate()->findOrFail($course->id) : new Course;
                Gate::forUser($actor)->authorize($course->exists ? 'update' : 'create', $course->exists ? $course : Course::class);
                $course->fill(collect($data)->only(['title', 'slug', 'summary', 'description'])->all());
                if ($actor->hasRole(UserRole::Teacher)) {
                    if (array_key_exists('teacher_id', $data)) {
                        throw ValidationException::withMessages(['teacher_id' => 'Only an administrator may assign a teacher.']);
                    }
                    if (! $course->exists) {
                        $course->teacher_id = $this->participants->teacher($actor->id)->id;
                    }
                } elseif (array_key_exists('teacher_id', $data)) {
                    $this->setTeacher($course, $data['teacher_id'] === null ? null : (int) $data['teacher_id']);
                }
                $course->save();

                return $course;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['slug' => 'This course slug is already in use, including archived courses.']);
        }
    }

    public function publish(User $actor, Course $course, bool $published): void
    {
        DB::transaction(function () use ($actor, $course, $published): void {
            $course = Course::query()->lockForUpdate()->findOrFail($course->id);
            Gate::forUser($actor)->authorize('publish', $course);
            if ($published) {
                if (! $course->teacher_id) {
                    throw ValidationException::withMessages(['teacher_id' => 'Assign an active teacher before publishing.']);
                }
                $this->participants->teacher($course->teacher_id);
            }
            $course->published_at = $published ? ($course->isPublished() ? $course->published_at : now()) : null;
            $course->save();
        }, 3);
    }

    public function assign(User $actor, Course $course, ?int $teacherId): void
    {
        DB::transaction(function () use ($actor, $course, $teacherId): void {
            $course = Course::query()->lockForUpdate()->findOrFail($course->id);
            Gate::forUser($actor)->authorize('assignTeacher', $course);
            $this->setTeacher($course, $teacherId);
            $course->save();
        }, 3);
    }

    public function delete(User $actor, Course $course): void
    {
        DB::transaction(function () use ($actor, $course): void {
            $course = Course::query()->lockForUpdate()->findOrFail($course->id);
            Gate::forUser($actor)->authorize('delete', $course);
            $course->delete();
        }, 3);
    }

    private function setTeacher(Course $course, ?int $teacherId): void
    {
        if ($teacherId === null && $course->isPublished()) {
            throw ValidationException::withMessages(['teacher_id' => 'Unpublish the course before removing its teacher.']);
        }
        $course->teacher_id = $teacherId === null ? null : $this->participants->teacher($teacherId)->id;
    }
}
