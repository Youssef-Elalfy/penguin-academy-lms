<?php

namespace App\Http\Requests;

class PublishLessonRequest extends AcademyRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('lesson'));
    }

    public function rules(): array
    {
        return [...$this->protectedFields(), 'is_published' => ['required', 'boolean']];
    }
}
