<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class AcademyRequest extends FormRequest
{
    protected function protectedFields(array $allowed = []): array
    {
        return array_fill_keys(array_diff([
            'id', 'user_id', 'role', 'teacher_id', 'student_id', 'course_id', 'lesson_id',
            'enrollment_id', 'is_active', 'published_at', 'deleted_at', 'lesson_order_version',
            'position', 'withdrawn_at', 'enrolled_at', 'created_at', 'updated_at',
        ], $allowed), ['missing']);
    }
}
