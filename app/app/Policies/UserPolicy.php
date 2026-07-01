<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage users');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('manage users');
    }

    public function create(User $user): bool
    {
        return $user->can('manage users');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('manage users');
    }

    public function toggleActive(User $user, User $model): bool
    {
        if (! $user->can('manage users')) {
            return false;
        }

        // Admin cannot deactivate their own account
        return $user->id !== $model->id;
    }

    public function assignRole(User $user, User $model): bool
    {
        if (! $user->can('manage users')) {
            return false;
        }

        // Admin cannot remove their own Admin role
        return $user->id !== $model->id;
    }
}
