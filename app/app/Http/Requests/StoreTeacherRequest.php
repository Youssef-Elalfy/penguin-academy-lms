<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
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
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'max:72', 'password_bytes', 'confirmed', Password::defaults()],
            'current_password' => ['required', 'string', 'password_bytes', 'current_password:web'],
            'role' => ['prohibited'],
            'is_active' => ['prohibited'],
            'id' => ['prohibited'],
            'must_change_password' => ['prohibited'],
            'email_verified_at' => ['prohibited'],
        ];
    }
}
