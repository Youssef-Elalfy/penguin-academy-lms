<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Validator;

trait RejectsUnknownFields
{
    public function after(): array
    {
        return [function (Validator $validator): void {
            $known = array_unique(array_map(fn (string $key): string => explode('.', $key)[0], array_keys($this->rules())));
            foreach (array_diff(array_keys($this->all()), $known) as $key) {
                $validator->errors()->add($key, 'This field is not accepted.');
            }
        }];
    }
}
