<?php

namespace App\Services\Api;

use App\Enums\UserRole;
use App\Models\User;

class TokenAbilities
{
    /** @return list<string> */
    public function forUser(User $user): array
    {
        $common = ['courses:read', 'lessons:read', 'enrollments:read', 'enrollments:write', 'progress:read', 'tokens:read', 'tokens:revoke'];

        return [...$common, ...($user->role === UserRole::Student
            ? ['progress:write']
            : ['courses:write', 'lessons:write', 'reports:read'])];
    }
}
