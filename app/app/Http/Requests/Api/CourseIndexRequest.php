<?php

namespace App\Http\Requests\Api;

class CourseIndexRequest extends \App\Http\Requests\CourseIndexRequest
{
    use RejectsUnknownFields;

    public function rules(): array
    {
        return [...parent::rules(), 'page' => ['sometimes', 'integer', 'min:1', 'max:1000000'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']];
    }
}
