<?php

namespace App\Http\Requests\Api;

use App\Models\LessonCompletion;

class ChangeCompletionRequest extends EmptyRequest
{
    public function authorize(): bool
    {
        $enrollment = $this->route('enrollment');
        $lesson = $this->route('lesson');
        abort_unless($lesson->course_id === $enrollment->course_id, 404);

        return $this->user()->can('create', [LessonCompletion::class, $enrollment, $lesson]);
    }
}
