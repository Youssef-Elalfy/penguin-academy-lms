<?php

namespace App\Http\Requests\Api;

use App\Enums\UserRole;
use App\Models\Lesson;
use Illuminate\Validation\Rule;

class LessonIndexRequest extends PaginationRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', [Lesson::class, $this->route('course')]);
    }

    public function rules(): array
    {
        return [...parent::rules(), 'status' => ['nullable', Rule::in($this->user()->hasRole(UserRole::Student) ? ['published'] : ['published', 'draft'])]];
    }
}
