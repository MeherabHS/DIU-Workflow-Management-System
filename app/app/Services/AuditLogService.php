<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\RepositoryEntry;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowFile;
use App\Models\WorkflowMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    public function __construct(protected Request $request)
    {
    }

    protected function createContext(): array
    {
        return [
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ];
    }

    protected function log(string $action, ?string $entityType = null, ?int $entityId = null, array $metadata = [], ?Project $project = null, ?Task $task = null, ?Subtask $subtask = null, ?RepositoryEntry $repositoryEntry = null): AuditLog
    {
        return AuditLog::create([
            'actor_id' => Auth::id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'project_id' => $project?->id,
            'task_id' => $task?->id,
            'subtask_id' => $subtask?->id,
            'repository_entry_id' => $repositoryEntry?->id,
            ...$this->createContext(),
            'metadata' => $metadata,
        ]);
    }

    // ── User actions ───────────────────────────────────────────────
    public function logUserCreated(User $user, string $role): AuditLog
    {
        return $this->log('user_created', User::class, $user->id, ['email' => $user->email, 'role' => $role]);
    }

    public function logUserUpdated(User $user, array $changes): AuditLog
    {
        return $this->log('user_updated', User::class, $user->id, ['changes' => $changes, 'email' => $user->email]);
    }

    public function logUserToggled(User $user, bool $isActive): AuditLog
    {
        return $this->log($isActive ? 'user_activated' : 'user_deactivated', User::class, $user->id, ['email' => $user->email]);
    }

    public function logUserPasswordReset(User $user): AuditLog
    {
        return $this->log('user_password_reset', User::class, $user->id, ['email' => $user->email]);
    }

    // ── Project actions ────────────────────────────────────────────
    public function logProjectCreated(Project $project): AuditLog
    {
        return $this->log('project_created', Project::class, $project->id, ['title' => $project->title], $project);
    }

    public function logProjectUpdated(Project $project, array $changes): AuditLog
    {
        return $this->log('project_updated', Project::class, $project->id, ['changes' => $changes, 'title' => $project->title], $project);
    }

    public function logCoordinatorAssigned(Project $project, User $coordinator, User $assigner): AuditLog
    {
        return $this->log('coordinator_assigned', Project::class, $project->id, [
            'coordinator_id' => $coordinator->id,
            'coordinator_name' => $coordinator->name,
            'assigner_name' => $assigner->name,
            'project_title' => $project->title,
        ], $project);
    }

    public function logCoordinatorRevoked(Project $project, User $coordinator, User $actor): AuditLog
    {
        return $this->log('coordinator_revoked', Project::class, $project->id, [
            'coordinator_id' => $coordinator->id,
            'coordinator_name' => $coordinator->name,
            'actor_name' => $actor->name,
            'project_title' => $project->title,
        ], $project);
    }

    public function logProjectFinalized(Project $project, RepositoryEntry $entry): AuditLog
    {
        return $this->log('project_finalized', Project::class, $project->id, [
            'repository_entry_id' => $entry->id,
            'project_title' => $project->title,
        ], $project);
    }

    // ── Task actions ───────────────────────────────────────────────
    public function logTaskCreated(Task $task): AuditLog
    {
        return $this->log('task_created', Task::class, $task->id, ['title' => $task->title], $task->project, $task);
    }

    public function logTaskUpdated(Task $task, array $changes): AuditLog
    {
        return $this->log('task_updated', Task::class, $task->id, ['changes' => $changes, 'title' => $task->title], $task->project, $task);
    }

    // ── Subtask actions ────────────────────────────────────────────
    public function logSubtaskCreated(Subtask $subtask): AuditLog
    {
        return $this->log('subtask_created', Subtask::class, $subtask->id, ['title' => $subtask->title], $subtask->project, $subtask->task, $subtask);
    }

    public function logSubtaskUpdated(Subtask $subtask, array $changes): AuditLog
    {
        return $this->log('subtask_updated', Subtask::class, $subtask->id, ['changes' => $changes, 'title' => $subtask->title], $subtask->project, $subtask->task, $subtask);
    }

    public function logSubtaskProgressUpdated(Subtask $subtask, array $changes): AuditLog
    {
        return $this->log('subtask_progress_updated', Subtask::class, $subtask->id, ['changes' => $changes, 'title' => $subtask->title], $subtask->project, $subtask->task, $subtask);
    }

    // ── Subordinate assignment actions ─────────────────────────────
    public function logSubordinateAssigned(Subtask $subtask, User $subordinate, User $assigner): AuditLog
    {
        return $this->log('subordinate_assigned', Subtask::class, $subtask->id, [
            'subordinate_id' => $subordinate->id,
            'subordinate_name' => $subordinate->name,
            'assigner_name' => $assigner->name,
            'subtask_title' => $subtask->title,
        ], $subtask->project, $subtask->task, $subtask);
    }

    public function logSubordinateRevoked(Subtask $subtask, User $subordinate, User $actor): AuditLog
    {
        return $this->log('subordinate_revoked', Subtask::class, $subtask->id, [
            'subordinate_id' => $subordinate->id,
            'subordinate_name' => $subordinate->name,
            'actor_name' => $actor->name,
            'subtask_title' => $subtask->title,
        ], $subtask->project, $subtask->task, $subtask);
    }

    // ── File actions ───────────────────────────────────────────────
    public function logFileUploaded(WorkflowFile $file): AuditLog
    {
        return $this->log('file_uploaded', WorkflowFile::class, $file->id, [
            'file_name' => $file->original_name,
            'category' => $file->file_category,
        ], $file->project, $file->task, $file->subtask, $file->repositoryEntry);
    }

    public function logFileDownloaded(WorkflowFile $file): AuditLog
    {
        return $this->log('file_downloaded', WorkflowFile::class, $file->id, [
            'file_name' => $file->original_name,
        ], $file->project, $file->task, $file->subtask, $file->repositoryEntry);
    }

    public function logFileDeleted(WorkflowFile $file): AuditLog
    {
        return $this->log('file_deleted', WorkflowFile::class, $file->id, [
            'file_name' => $file->original_name,
        ], $file->project, $file->task, $file->subtask, $file->repositoryEntry);
    }

    // ── Message actions ────────────────────────────────────────────
    public function logMessageSent(WorkflowMessage $message): AuditLog
    {
        return $this->log('message_sent', WorkflowMessage::class, $message->id, [
            'message_type' => $message->message_type,
        ], $message->project, $message->task, $message->subtask);
    }

    // ── Comparison actions ─────────────────────────────────────────
    public function logComparisonRun(string $contextType, int $contextId, string $summary): AuditLog
    {
        $metadata = ['context_type' => $contextType, 'context_id' => $contextId, 'summary' => $summary];

        return match ($contextType) {
            'project' => $this->log('comparison_run', 'WorkflowComparison', null, $metadata, Project::find($contextId)),
            'task' => $this->log('comparison_run', 'WorkflowComparison', null, $metadata, task: Task::find($contextId)),
            'subtask' => $this->log('comparison_run', 'WorkflowComparison', null, $metadata, subtask: Subtask::find($contextId)),
            default => $this->log('comparison_run', 'WorkflowComparison', null, $metadata),
        };
    }

    // ── Repository actions ─────────────────────────────────────────
    public function logRepositoryEntryCreated(RepositoryEntry $entry): AuditLog
    {
        return $this->log('repository_entry_created', RepositoryEntry::class, $entry->id, [
            'title' => $entry->title,
            'status' => $entry->status,
        ], $entry->project, repositoryEntry: $entry);
    }

    public function logRepositoryEntryUpdated(RepositoryEntry $entry, array $changes): AuditLog
    {
        return $this->log('repository_entry_updated', RepositoryEntry::class, $entry->id, [
            'changes' => $changes,
            'title' => $entry->title,
        ], $entry->project, repositoryEntry: $entry);
    }

    public function logRepositoryUpdateAdded(RepositoryEntry $entry, string $note, ?string $newStatus): AuditLog
    {
        return $this->log('repository_update_added', RepositoryEntry::class, $entry->id, [
            'note' => $note,
            'new_status' => $newStatus,
            'title' => $entry->title,
        ], $entry->project, repositoryEntry: $entry);
    }
}
