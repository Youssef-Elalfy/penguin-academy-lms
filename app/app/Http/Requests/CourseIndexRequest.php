<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CourseIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Course::class);
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in($this->user()->hasRole(UserRole::Student) ? ['published'] : ['published', 'draft'])],
            'sort' => ['nullable', Rule::in(['newest', 'title'])],
            'teacher_id' => $this->user()->hasRole(UserRole::Admin)
                ? ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'teacher')]
                : ['missing'],
        ];
    }
}
