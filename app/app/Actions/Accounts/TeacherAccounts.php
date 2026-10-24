<?php

namespace App\Actions\Accounts;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeacherAccounts
{
    public function create(array $data): User
    {
        Gate::authorize('create', User::class);
        try {
            return DB::transaction(function () use ($data): User {
                $teacher = new User(collect($data)->only(['name', 'email', 'password'])->all());
                $teacher->role = UserRole::Teacher;
                $teacher->is_active = true;
                $teacher->must_change_password = true;
                $teacher->save();

                return $teacher;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'This email is already in use.']);
        }
    }

    public function update(User $teacher, array $data): User
    {
        try {
            return DB::transaction(function () use ($teacher, $data): User {
                $teacher = User::query()->lockForUpdate()->findOrFail($teacher->id);
                Gate::authorize('update', $teacher);
                $emailChanged = $teacher->email !== $data['email'];
                $teacher->fill(collect($data)->only(['name', 'email'])->all());
                $teacher->is_active = (bool) $data['is_active'];
                $passwordChanged = ! empty($data['password']);
                if ($passwordChanged) {
                    $teacher->password = $data['password'];
                    $teacher->must_change_password = true;
                }
                if ($emailChanged) {
                    $teacher->email_verified_at = null;
                }
                if (! $teacher->is_active || $passwordChanged || $emailChanged) {
                    $teacher->remember_token = Str::random(60);
                    DB::table('sessions')->where('user_id', $teacher->id)->delete();
                    $teacher->tokens()->delete();
                }
                $teacher->save();

                return $teacher;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'This email is already in use.']);
        }
    }
}
