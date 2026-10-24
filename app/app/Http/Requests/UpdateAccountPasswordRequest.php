<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateAccountPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('changePassword', $this->user()) ?? false;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'password_bytes', 'current_password:web'],
            'password' => ['required', 'string', 'max:72', 'password_bytes', 'confirmed', 'different:current_password', Password::defaults()],
            'role' => ['prohibited'],
            'user_id' => ['prohibited'],
        ];
    }
}
