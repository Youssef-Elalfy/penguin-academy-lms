<?php

namespace App\Http\Requests;

class DeleteLessonRequest extends AcademyRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('lesson'));
    }

    public function rules(): array
    {
        return $this->protectedFields();
    }
}
