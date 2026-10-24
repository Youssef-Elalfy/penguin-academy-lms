<?php

namespace App\Http\Requests;

use App\Models\Lesson;

class ReorderLessonsRequest extends AcademyRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Lesson::class, $this->route('course')]);
    }

    public function rules(): array
    {
        return [...$this->protectedFields(),
            'order_version' => ['required', 'integer', 'min:0'],
            'lesson_ids' => ['required', 'array', 'min:1'],
            'lesson_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ];
    }
}
