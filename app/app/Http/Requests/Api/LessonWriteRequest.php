<?php

namespace App\Http\Requests\Api;

class LessonWriteRequest extends \App\Http\Requests\LessonWriteRequest
{
    use RejectsUnknownFields;

    public function rules(): array
    {
        $rules = parent::rules();
        if ($this->isMethod('PATCH')) {
            foreach (['title', 'content'] as $field) {
                array_unshift($rules[$field], 'sometimes');
            }
        }

        return $rules;
    }
}
