<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateTeacherRequest extends StoreTeacherRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('teacher')) ?? false;
    }

    public function rules(): array
    {
        return array_replace(parent::rules(), [
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('teacher')->id)],
            'password' => ['nullable', 'string', 'max:72', 'password_bytes', 'confirmed', Password::defaults()],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
