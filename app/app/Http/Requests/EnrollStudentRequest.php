<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Enrollment;
use Illuminate\Validation\Rule;

class EnrollStudentRequest extends AcademyRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Enrollment::class, $this->route('course')]);
    }

    public function rules(): array
    {
        return [...$this->protectedFields(['student_id']),
            'student_id' => $this->user()->hasRole(UserRole::Student) ? ['missing']
                : ['required', 'integer', Rule::exists('users', 'id')->where('role', 'student')->where('is_active', true)],
        ];
    }
}
