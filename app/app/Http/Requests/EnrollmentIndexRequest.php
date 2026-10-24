<?php

namespace App\Http\Requests;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnrollmentIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($course = $this->route('course')) {
            return $this->user()->can('viewAny', [Enrollment::class, $course]);
        }

        return $this->routeIs('my-courses') ? $this->user()->can('viewStudentDashboard', User::class) : $this->user()->can('viewAny', User::class);
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'student_search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['active', 'withdrawn', 'all'])],
            'course_id' => $this->routeIs('admin.enrollments.index')
                ? ['nullable', 'integer', Rule::exists('courses', 'id')->whereNull('deleted_at')] : ['missing'],
        ];
    }
}
