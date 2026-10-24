<?php

namespace App\Actions\Academy;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CompletionActions
{
    public function __construct(private Participants $participants) {}

    public function setCompleted(User $actor, Course $course, Lesson $lesson, bool $completed): ?LessonCompletion
    {
        return DB::transaction(function () use ($actor, $course, $lesson, $completed): ?LessonCompletion {
            $course = Course::query()->lockForUpdate()->findOrFail($course->id);
            $lesson = $course->lessons()->lockForUpdate()->findOrFail($lesson->id);
            $lesson->setRelation('course', $course);
            $actor = $this->participants->student($actor->id);
            $enrollment = $course->enrollments()->where('student_id', $actor->id)->active()->lockForUpdate()->first();
            abort_unless($enrollment, 403, 'An active enrollment is required to change learning progress.');
            $enrollment->setRelation('course', $course);
            Gate::forUser($actor)->authorize('create', [LessonCompletion::class, $enrollment, $lesson]);
            $completion = $enrollment->completions()->where('lesson_id', $lesson->id)->lockForUpdate()->first();
            if ($completed) {
                if (! $completion) {
                    $completion = new LessonCompletion;
                    $completion->enrollment_id = $enrollment->id;
                    $completion->course_id = $course->id;
                    $completion->lesson_id = $lesson->id;
                    $completion->completed_at = now();
                    $completion->save();
                }

                return $completion;
            }
            if ($completion) {
                $completion->setRelation('enrollment', $enrollment)->setRelation('lesson', $lesson);
                Gate::forUser($actor)->authorize('delete', $completion);
                $completion->delete();
            }

            return null;
        }, 3);
    }
}
