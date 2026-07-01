<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewProjectTasks(User $user, Project $project): bool
    {
        if (! $user->can('view project tasks')) {
            return false;
        }

        if ($user->hasAnyRole(['Admin', 'PM/Manager'])) {
            return true;
        }

        return $this->isAssignedCoordinator($user, $project);
    }

    public function createInProject(User $user, Project $project): bool
    {
        if (! $user->can('create project task')) {
            return false;
        }

        if ($user->hasAnyRole(['Admin', 'PM/Manager'])) {
            return true;
        }

        return $this->isAssignedCoordinator($user, $project);
    }

    public function view(User $user, Task $task): bool
    {
        return $this->viewProjectTasks($user, $task->project);
    }

    public function update(User $user, Task $task): bool
    {
        if (! $user->can('update project task')) {
            return false;
        }

        if ($user->hasAnyRole(['Admin', 'PM/Manager'])) {
            return true;
        }

        return $this->isAssignedCoordinator($user, $task->project);
    }

    protected function isAssignedCoordinator(User $user, Project $project): bool
    {
        return $user->hasRole('Coordinator')
            && $project->assignments()
                ->where('assignment_role', 'primary')
                ->whereNull('revoked_at')
                ->where('coordinator_id', $user->id)
                ->exists();
    }
}
