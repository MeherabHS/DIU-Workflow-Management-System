<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\RepositoryEntry;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowFile;

class WorkflowFilePolicy
{
    public function view(User $user, WorkflowFile $workflowFile): bool
    {
        if (! $user->can('view workflow files')) {
            return false;
        }

        if ($workflowFile->subtask) {
            return $this->viewSubtaskContext($user, $workflowFile->subtask);
        }

        if ($workflowFile->task) {
            return $this->viewTaskContext($user, $workflowFile->task);
        }

        if ($workflowFile->repositoryEntry) {
            return $this->viewRepositoryContext($user, $workflowFile->repositoryEntry);
        }

        if ($workflowFile->project) {
            return $this->viewProjectContext($user, $workflowFile->project);
        }

        return false;
    }

    public function download(User $user, WorkflowFile $workflowFile): bool
    {
        return $user->can('download workflow file') && $this->view($user, $workflowFile);
    }

    public function create(User $user, Project|Task|Subtask|RepositoryEntry $context): bool
    {
        if (! $user->can('upload workflow file')) {
            return false;
        }

        return match (true) {
            $context instanceof Project => $this->createProjectContext($user, $context),
            $context instanceof Task => $this->createTaskContext($user, $context),
            $context instanceof Subtask => $this->createSubtaskContext($user, $context),
            $context instanceof RepositoryEntry => $this->createRepositoryContext($user, $context),
            default => false,
        };
    }

    public function delete(User $user, WorkflowFile $workflowFile): bool
    {
        if (! $user->can('delete workflow file')) {
            return false;
        }

        return $user->hasAnyRole(['Admin', 'PM/Manager']);
    }

    protected function viewProjectContext(User $user, Project $project): bool
    {
        if ($user->hasAnyRole(['Admin', 'PM/Manager'])) {
            return true;
        }

        return $this->isAssignedCoordinator($user, $project);
    }

    protected function createProjectContext(User $user, Project $project): bool
    {
        return $this->viewProjectContext($user, $project);
    }

    protected function viewTaskContext(User $user, Task $task): bool
    {
        if ($user->hasAnyRole(['Admin', 'PM/Manager'])) {
            return true;
        }

        return $this->isAssignedCoordinator($user, $task->project);
    }

    protected function createTaskContext(User $user, Task $task): bool
    {
        return $this->viewTaskContext($user, $task);
    }

    protected function viewSubtaskContext(User $user, Subtask $subtask): bool
    {
        if ($user->hasAnyRole(['Admin', 'PM/Manager'])) {
            return true;
        }

        if ($this->isAssignedCoordinator($user, $subtask->project)) {
            return true;
        }

        return $this->isAssignedSubordinate($user, $subtask);
    }

    protected function createSubtaskContext(User $user, Subtask $subtask): bool
    {
        return $this->viewSubtaskContext($user, $subtask);
    }

    protected function viewRepositoryContext(User $user, RepositoryEntry $repositoryEntry): bool
    {
        if (! $user->can('view repository')) {
            return false;
        }

        if ($user->hasAnyRole(['Admin', 'PM/Manager'])) {
            return true;
        }

        return $repositoryEntry->project !== null
            && $this->isAssignedCoordinator($user, $repositoryEntry->project);
    }

    protected function createRepositoryContext(User $user, RepositoryEntry $repositoryEntry): bool
    {
        return $this->viewRepositoryContext($user, $repositoryEntry)
            && ! $user->hasRole('Subordinate');
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


