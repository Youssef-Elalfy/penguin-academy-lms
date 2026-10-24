<?php

namespace App\Actions\Academy;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class Participants
{
    /** Recheck eligibility under a row lock inside the caller's transaction. */
    public function teacher(int $id): User
    {
        return $this->eligible($id, UserRole::Teacher, 'teacher_id');
    }

    public function student(int $id): User
    {
        return $this->eligible($id, UserRole::Student, 'student_id');
    }

    private function eligible(int $id, UserRole $role, string $field): User
    {
        $user = User::query()->lockForUpdate()->find($id);
        if (! $user || ! $user->hasRole($role)) {
            throw ValidationException::withMessages([$field => 'Choose an active '.$role->value.' account.']);
        }

        return $user;
    }
}
