<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Course;
use Illuminate\Validation\Rule;

class CourseWriteRequest extends AcademyRequest
{
    public function authorize(): bool
    {
        $course = $this->route('course');

        return $course ? $this->user()->can('update', $course) : $this->user()->can('create', Course::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('slug'))) {
            $this->merge(['slug' => mb_strtolower(trim($this->input('slug')))]);
        }
    }

    public function rules(): array
    {
        return [
            ...$this->protectedFields(['teacher_id']),
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['required', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('courses', 'slug')->ignore($this->route('course'))],
            'summary' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:20000'],
            'teacher_id' => $this->user()->hasRole(UserRole::Admin)
                ? ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'teacher')->where('is_active', true)]
                : ['missing'],
        ];
    }
}
