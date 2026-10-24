<?php

namespace App\Http\Requests;

class DeleteCourseRequest extends AcademyRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('course'));
    }

    public function rules(): array
    {
        return $this->protectedFields();
    }
}
