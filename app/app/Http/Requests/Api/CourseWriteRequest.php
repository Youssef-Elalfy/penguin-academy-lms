<?php

namespace App\Http\Requests\Api;

class CourseWriteRequest extends \App\Http\Requests\CourseWriteRequest
{
    use RejectsUnknownFields;

    public function rules(): array
    {
        $rules = parent::rules();
        if ($this->isMethod('PATCH')) {
            foreach (['title', 'slug'] as $field) {
                array_unshift($rules[$field], 'sometimes');
            }
        }

        return $rules;
    }
}
