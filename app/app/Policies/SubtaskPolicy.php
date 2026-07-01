<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;

class SubtaskPolicy
{
    public function createForTask(User $user, Task $task): bool
    {
        if (! $user->can('create project subtask')) {
            return false;
        }

        return $this->isAssignedCoordinator($user, $task->project);
    }

    public function view(User $user, Subtask $subtask): bool
    {
        if (! $user->can('view project tasks')) {
            return false;
        }

        if ($user->hasAnyRole(['Admin', 'PM/Manager'])) {
            return true;
        }

        return $this->isAssignedCoordinator($user, $subtask->project);
    }

    public function update(User $user, Subtask $subtask): bool
    {
        if (! $user->can('update project subtask')) {
            return false;
        }

        if ($user->hasAnyRole(['Admin', 'PM/Manager'])) {
            return true;
        }

        return $this->isAssignedCoordinator($user, $subtask->project);
    }

    public function assignSubordinate(User $user, Subtask $subtask): bool
    {
        if (! $user->can('assign subordinate')) {
            return false;
        }

        return $this->isAssignedCoordinator($user, $subtask->project);
    }

    public function revokeSubordinateAssignment(User $user, Subtask $subtask): bool
    {
        if (! $user->can('revoke subordinate assignment')) {
            return false;
        }

        return $this->isAssignedCoordinator($user, $subtask->project);
    }

    public function viewAssigned(User $user, Subtask $subtask): bool
    {
        return $user->can('view assigned subtasks')
            && $subtask->assignments()
                ->where('subordinate_id', $user->id)
                ->whereNull('revoked_at')
                ->exists();
    }

    public function updateAssignedProgress(User $user, Subtask $subtask): bool
    {
        return $user->can('update assigned subtask progress')
            && $subtask->assignments()
                ->where('subordinate_id', $user->id)
                ->whereNull('revoked_at')
                ->exists();
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



