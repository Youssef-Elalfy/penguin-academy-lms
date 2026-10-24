<?php

namespace App\Http\Requests\Api;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class EnrollStudentRequest extends FormRequest
{
    use RejectsUnknownFields;

    public function authorize(): bool
    {
        return $this->user()?->is_active === true;
    }

    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer', 'min:1'],
            'student_id' => $this->user()->hasRole(UserRole::Student) ? ['missing'] :
                ['required', 'integer', Rule::exists('users', 'id')->where('role', 'student')->where('is_active', true)],
        ];
    }

    public function course(): Course
    {
        $course = Course::findOrFail($this->validated('course_id'));
        Gate::authorize('create', [Enrollment::class, $course]);

        return $course;
    }
}
