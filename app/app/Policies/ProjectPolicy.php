<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view projects');
    }

    public function view(User $user, Project $project): bool
    {
        if ($user->can('view projects')) {
            return true;
        }

        if (! $user->hasRole('Coordinator') || ! $user->can('view assigned projects')) {
            return false;
        }

        return $project->assignments()
            ->where('assignment_role', 'primary')
            ->whereNull('revoked_at')
            ->where('coordinator_id', $user->id)
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('create project');
    }

    public function update(User $user, Project $project): bool
    {
        if (! $user->can('update project')) {
            return false;
        }

        // Admin and PM/Manager can update any project
        if ($user->hasAnyRole(['Admin', 'PM/Manager'])) {
            return true;
        }

        // Coordinator can only update assigned projects
        if ($user->hasRole('Coordinator')) {
            return $project->assignments()
                ->where('assignment_role', 'primary')
                ->whereNull('revoked_at')
                ->where('coordinator_id', $user->id)
                ->exists();
        }

        return false;
    }

    public function assignCoordinator(User $user, Project $project): bool
    {
        return $user->can('assign coordinator');
    }
}
