<?php

namespace App\Policies;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenPolicy
{
    public function create(User $user): bool
    {
        return $user->is_active && ! $user->must_change_password;
    }

    public function viewAny(User $user): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, PersonalAccessToken $token): bool
    {
        return $this->create($user) && $token->tokenable_type === $user->getMorphClass()
            && (int) $token->tokenable_id === $user->id;
    }
}
