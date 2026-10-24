<?php

namespace App\Http\Requests;

use App\Models\Lesson;

class LessonWriteRequest extends AcademyRequest
{
    public function authorize(): bool
    {
        $lesson = $this->route('lesson');

        return $lesson ? $this->user()->can('update', $lesson)
            : $this->user()->can('create', [Lesson::class, $this->route('course')]);
    }

    public function rules(): array
    {
        return [...$this->protectedFields(),
            'title' => ['required', 'string', 'max:180'],
            'content' => ['required', 'string', 'max:100000'],
            'is_published' => ['missing'],
        ];
    }
}
