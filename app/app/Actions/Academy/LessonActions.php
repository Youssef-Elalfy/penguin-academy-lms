<?php

namespace App\Actions\Academy;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class LessonActions
{
    public function save(User $actor, Course $course, ?Lesson $lesson, array $data): Lesson
    {
        return DB::transaction(function () use ($actor, $course, $lesson, $data): Lesson {
            $course = $this->lockCourse($actor, $course);
            if ($lesson) {
                $lesson = $course->lessons()->lockForUpdate()->findOrFail($lesson->id);
                Gate::forUser($actor)->authorize('update', $lesson);
            } else {
                $lesson = new Lesson;
                $lesson->course_id = $course->id;
                $maximum = (int) $course->lessons()->withTrashed()->reorder()->max('position');
                $this->assertPositionSpace($maximum, 1);
                $lesson->position = $maximum + 1;
                $lesson->is_published = false;
                $course->increment('lesson_order_version');
            }
            $lesson->fill(collect($data)->only(['title', 'content'])->all());
            $lesson->save();

            return $lesson;
        }, 3);
    }

    public function publish(User $actor, Course $course, Lesson $lesson, bool $published): void
    {
        DB::transaction(function () use ($actor, $course, $lesson, $published): void {
            $course = $this->lockCourse($actor, $course);
            $lesson = $course->lessons()->lockForUpdate()->findOrFail($lesson->id);
            Gate::forUser($actor)->authorize('update', $lesson);
            $lesson->is_published = $published;
            $lesson->save();
        }, 3);
    }

    public function delete(User $actor, Course $course, Lesson $lesson): void
    {
        DB::transaction(function () use ($actor, $course, $lesson): void {
            $course = $this->lockCourse($actor, $course);
            $lesson = $course->lessons()->lockForUpdate()->findOrFail($lesson->id);
            Gate::forUser($actor)->authorize('delete', $lesson);
            $lesson->delete();
            $course->increment('lesson_order_version');
        }, 3);
    }

    public function reorder(User $actor, Course $course, array $ids, int $version): void
    {
        DB::transaction(function () use ($actor, $course, $ids, $version): void {
            $course = $this->lockCourse($actor, $course);
            abort_if($course->lesson_order_version !== $version, 409, 'The lesson list changed. Reload the order page and try again.');
            $lessons = $course->lessons()->withTrashed()->lockForUpdate()->get();
            $active = $lessons->reject(fn (Lesson $lesson) => $lesson->trashed())->keyBy('id');
            $ids = array_map('intval', $ids);
            if (count($ids) !== $active->count() || count(array_unique($ids)) !== count($ids)
                || array_diff($ids, $active->modelKeys())) {
                throw ValidationException::withMessages(['lesson_ids' => 'Choose every current lesson exactly once, from this course only.']);
            }
            $maximum = (int) $lessons->max('position');
            $this->assertPositionSpace($maximum, count($ids));
            $reserved = $lessons->filter(fn (Lesson $lesson) => $lesson->trashed())->pluck('position')->flip();
            // Move above all occupied positions before swapping unique course/position values.
            foreach ($ids as $offset => $id) {
                $active[$id]->update(['position' => $maximum + $offset + 1]);
            }
            $position = 1;
            foreach ($ids as $id) {
                while ($reserved->has($position)) {
                    $position++;
                }
                $active[$id]->update(['position' => $position++]);
            }
            $course->increment('lesson_order_version');
        }, 3);
    }

    private function lockCourse(User $actor, Course $course): Course
    {
        $course = Course::query()->lockForUpdate()->findOrFail($course->id);
        Gate::forUser($actor)->authorize('create', [Lesson::class, $course]);

        return $course;
    }

    private function assertPositionSpace(int $maximum, int $count): void
    {
        if ($maximum + $count > 4294967295) {
            throw ValidationException::withMessages(['lesson_ids' => 'No safe lesson positions remain. Contact your administrator.']);
        }
    }
}
