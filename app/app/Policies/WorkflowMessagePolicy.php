<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowMessage;

class WorkflowMessagePolicy
{
    public function view(User $user, WorkflowMessage $workflowMessage): bool
    {
        if (! $user->can('view workflow messages')) {
            return false;
        }

        if ($workflowMessage->subtask) {
            return $this->viewSubtask($user, $workflowMessage->subtask);
        }

        if ($workflowMessage->task) {
            return $this->viewTask($user, $workflowMessage->task);
        }

        if ($workflowMessage->project) {
            return $this->viewProject($user, $workflowMessage->project);
        }

        return false;
    }

    public function viewProject(User $user, Project $project): bool
    {
        if (! $user->can('view workflow messages')) {
            return false;
        }

        if ($user->hasAnyRole(['Admin', 'PM/Manager'])) {
            return true;
        }

        return $this->isAssignedCoordinator($user, $project);
    }

    public function createProject(User $user, Project $project): bool
    {
        if (! $user->can('create workflow message')) {
            return false;
        }

        if ($user->hasAnyRole(['Admin', 'PM/Manager'])) {
            return true;
        }

        return $this->isAssignedCoordinator($user, $project);
    }

    public function viewTask(User $user, Task $task): bool
    {
        if (! $user->can('view workflow messages')) {
            return false;
        }

        if ($user->hasAnyRole(['Admin', 'PM/Manager'])) {
            return true;
        }

        return $this->isAssignedCoordinator($user, $task->project);
    }

    public function createTask(User $user, Task $task): bool
    {
        if (! $user->can('create workflow message')) {
            return false;
        }

        if ($user->hasAnyRole(['Admin', 'PM/Manager'])) {
            return true;
        }

        return $this->isAssignedCoordinator($user, $task->project);
    }

    public function viewSubtask(User $user, Subtask $subtask): bool
    {
        if (! $user->can('view workflow messages')) {
            return false;
        }

        if ($user->hasAnyRole(['Admin', 'PM/Manager'])) {
            return true;
        }

        if ($this->isAssignedCoordinator($user, $subtask->project)) {
            return true;
        }

        return $this->isAssignedSubordinate($user, $subtask);
    }

    /**
     * Determine if a specific message is visible to a user.
     * For Subordinate viewers, only their own messages and messages from
     * management roles (Admin/PM/Manager/Coordinator) are visible.
     */
    public function viewMessage(User $user, WorkflowMessage $message): bool
    {
        if (! $this->view($user, $message)) {
            return false;
        }

        // Additional check for Subordinate: cannot see messages from other Subordinates
        if ($user->hasRole('Subordinate')) {
            $sender = $message->sender()->firstOrFail();
            $senderRoles = $sender->getRoleNames();

            // Subordinate can see their own messages
            if ((int) $sender->id === (int) $user->id) {
                return true;
            }

            // Subordinate can see messages from management roles
            if ($senderRoles->intersect(['Admin', 'PM/Manager', 'Manager', 'Coordinator'])->isNotEmpty()) {
                return true;
            }

            // Subordinate cannot see messages from other Subordinates
            return false;
        }

        return true;
    }

    public function createSubtask(User $user, Subtask $subtask): bool
    {
        if (! $user->can('create workflow message')) {
            return false;
        }

        if ($user->hasAnyRole(['Admin', 'PM/Manager'])) {
            return true;
        }

        if ($this->isAssignedCoordinator($user, $subtask->project)) {
            return true;
        }

        return $this->isAssignedSubordinate($user, $subtask);
    }

    public function delete(User $user, WorkflowMessage $workflowMessage): bool
    {
        if (! $user->can('delete workflow message')) {
            return false;
        }

        return $user->hasRole('Admin') || (int) $workflowMessage->sender_id === (int) $user->id;
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

    protected function isAssignedSubordinate(User $user, Subtask $subtask): bool
    {
        return $user->hasRole('Subordinate')
            && $subtask->assignments()
                ->where('subordinate_id', $user->id)
                ->whereNull('revoked_at')
                ->exists();
    }
}
