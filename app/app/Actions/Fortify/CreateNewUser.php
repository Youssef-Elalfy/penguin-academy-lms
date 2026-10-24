<?php

namespace App\Actions\Fortify;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    public function create(array $input): User
    {
        if (isset($input['email']) && is_string($input['email'])) {
            $input['email'] = Str::lower(trim($input['email']));
        }
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'max:72', 'password_bytes', 'confirmed', Password::defaults()],
            'role' => ['prohibited'],
            'is_active' => ['prohibited'],
            'must_change_password' => ['prohibited'],
            'email_verified_at' => ['prohibited'],
            'id' => ['prohibited'],
        ])->validate();

        try {
            return DB::transaction(function () use ($validated): User {
                $user = new User(collect($validated)->only(['name', 'email', 'password'])->all());
                $user->role = UserRole::Student;
                $user->is_active = true;
                $user->must_change_password = false;
                $user->save();

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'An account with this email already exists.']);
        }
    }
}
