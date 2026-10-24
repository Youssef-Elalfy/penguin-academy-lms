<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class AssignTeacherRequest extends AcademyRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assignTeacher', $this->route('course'));
    }

    public function rules(): array
    {
        return [...$this->protectedFields(['teacher_id']),
            'teacher_id' => ['present', 'nullable', 'integer', Rule::exists('users', 'id')->where('role', 'teacher')->where('is_active', true)],
        ];
    }
}
