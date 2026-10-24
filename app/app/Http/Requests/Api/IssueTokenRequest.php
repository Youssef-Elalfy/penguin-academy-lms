<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class IssueTokenRequest extends FormRequest
{
    use RejectsUnknownFields {
        after as private rejectUnknown;
    }

    public function after(): array
    {
        return [...$this->rejectUnknown(), function (Validator $validator): void {
            if ($this->query->count() > 0) {
                $validator->errors()->add('request', 'Send token credentials in the request body, never in the URL.');
            }
        }];
    }

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string', 'password_bytes'],
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['required', 'array', 'min:1', 'max:11'],
            'abilities.*' => ['required', 'string', 'max:40', 'distinct:strict'],
            'expires_in_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
        ];
    }
}
