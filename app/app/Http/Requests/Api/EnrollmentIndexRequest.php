<?php

namespace App\Http\Requests\Api;

use App\Enums\UserRole;
use Illuminate\Validation\Rule;

class EnrollmentIndexRequest extends PaginationRequest
{
    public function rules(): array
    {
        return [...parent::rules(),
            'status' => ['sometimes', Rule::in(['active', 'withdrawn', 'all'])],
            'course_id' => ['sometimes', 'integer', 'min:1'],
            'student_id' => $this->user()->hasRole(UserRole::Student) ? ['missing'] : ['sometimes', 'integer', 'min:1'],
        ];
    }
}
