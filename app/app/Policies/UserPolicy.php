<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function update(User $user, User $teacher): bool
    {
        return $user->hasRole(UserRole::Admin) && $teacher->role === UserRole::Teacher;
    }

    public function viewAdminDashboard(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function viewTeacherDashboard(User $user): bool
    {
        return $user->hasRole(UserRole::Teacher);
    }

    public function viewStudentDashboard(User $user): bool
    {
        return $user->hasRole(UserRole::Student);
    }

    public function changePassword(User $user, User $target): bool
    {
        return $user->is_active && $user->is($target);
    }
}
