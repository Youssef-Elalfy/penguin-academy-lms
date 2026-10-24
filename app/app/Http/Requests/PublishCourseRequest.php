<?php

namespace App\Http\Requests;

class PublishCourseRequest extends AcademyRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('publish', $this->route('course'));
    }

    public function rules(): array
    {
        return [...$this->protectedFields(), 'published' => ['required', 'boolean']];
    }
}
