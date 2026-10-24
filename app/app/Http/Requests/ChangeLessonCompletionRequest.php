<?php

namespace App\Http\Requests;

use App\Models\LessonCompletion;

class ChangeLessonCompletionRequest extends AcademyRequest
{
    public function authorize(): bool
    {
        $course = $this->route('course');
        $lesson = $this->route('lesson');
        $enrollment = $course->enrollments()->active()->where('student_id', $this->user()->id)->first();

        return $enrollment !== null && $this->user()->can('create', [LessonCompletion::class, $enrollment, $lesson]);
    }

    public function rules(): array
    {
        return [...$this->protectedFields(), 'completed_at' => ['missing'], 'completion_id' => ['missing'],
            'completed' => ['missing'], 'progress' => ['missing'], 'percentage' => ['missing']];
    }
}
