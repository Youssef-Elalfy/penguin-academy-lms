<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class AcademyReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Teacher);
    }
}
