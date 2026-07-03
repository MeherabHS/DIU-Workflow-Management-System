<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Subtask;
use App\Models\SubtaskAssignment;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowFile;
use App\Models\WorkflowMessage;
use App\Models\WorkflowNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WorkflowNotificationService
{
    /**
     * Generate a relative URL path for a named route.
     * Returns only the path component (e.g. /projects/5), never an absolute URL.
     */
    protected function relativeRoute(string $name, mixed $params = []): string
    {
        return (string) parse_url(route($name, $params), PHP_URL_PATH);
    }
    protected function normalizeActionUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '/')) {
            return preg_match('/[\r\n]/', $url) ? null : $url;
        }

        if (! preg_match('#^https?://#i', $url)) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '' || ! str_starts_with($path, '/')) {
            return null;
        }

        $query = parse_url($url, PHP_URL_QUERY);

        if (is_string($query) && $query !== '') {
            $path .= '?'.$query;
        }

        return preg_match('/[\r\n]/', $path) ? null : $path;
    }

    public function notifyUser(User $user, array $data): WorkflowNotification
    {
        return WorkflowNotification::create([
            'user_id' => $user->id,
            'actor_id' => $data['actor_id'] ?? null,
            'project_id' => $data['project_id'] ?? null,
            'task_id' => $data['task_id'] ?? null,
            'subtask_id' => $data['subtask_id'] ?? null,
            'workflow_message_id' => $data['workflow_message_id'] ?? null,
            'workflow_file_id' => $data['workflow_file_id'] ?? null,
            'type' => $data['type'],
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'action_url' => $this->normalizeActionUrl($data['action_url'] ?? null),
        ]);
    }

    /**
     * @param  Collection|array  $users
     */
    public function notifyMany(Collection|array $users, array $data, ?int $excludeUserId = null): void
    {
        $collection = $users instanceof Collection ? $users : collect($users);
        $collection = $collection
            ->filter(fn (User $u) => $u->id !== $excludeUserId)
            ->unique('id')
            ->values();

        foreach ($collection as $user) {
            $this->notifyUser($user, $data);
        }
    }

    public function notifyCoordinatorAssigned(Project $project, User $coordinator, User $actor): void
    {
        $this->notifyUser($coordinator, [
            'actor_id' => $actor->id,
            'project_id' => $project->id,
            'type' => 'coordinator_assigned',
            'title' => 'New Project Assigned',
            'body' => "You have been assigned to project: {$project->title}",
            'action_url' => $this->relativeRoute('projects.show', $project),
        ]);
    }

    public function notifyCoordinatorRevoked(Project $project, User $coordinator, User $actor): void
    {
        $this->notifyUser($coordinator, [
            'actor_id' => $actor->id,
            'project_id' => $project->id,
            'type' => 'coordinator_revoked',
            'title' => 'Coordinator Assignment Revoked',
            'body' => "Your coordinator assignment for project '{$project->title}' has been revoked.",
            'action_url' => $this->relativeRoute('projects.index'),
        ]);
    }

    public function notifySubordinateAssigned(Subtask $subtask, User $subordinate, User $actor): void
    {
        $this->notifyUser($subordinate, [
            'actor_id' => $actor->id,
            'project_id' => $subtask->project_id,
            'task_id' => $subtask->task_id,
            'subtask_id' => $subtask->id,
            'type' => 'subordinate_assigned',
            'title' => 'Assigned to Work Item',
            'body' => "You have been assigned to work item: {$subtask->title}",
            'action_url' => $this->relativeRoute('subtasks.mine.show', $subtask),
        ]);
    }

    public function notifySubordinateRevoked(Subtask $subtask, User $subordinate, User $actor): void
    {
        $this->notifyUser($subordinate, [
            'actor_id' => $actor->id,
            'project_id' => $subtask->project_id,
            'task_id' => $subtask->task_id,
            'subtask_id' => $subtask->id,
            'type' => 'subordinate_revoked',
            'title' => 'Work Item Assignment Revoked',
            'body' => "Your assignment to work item '{$subtask->title}' has been revoked.",
            'action_url' => $this->relativeRoute('my-work-items.index'),
        ]);
    }

    public function notifyMessageCreated(WorkflowMessage $message): void
    {
        $project = $message->project;
        $task = $message->task;
        $subtask = $message->subtask;
        $senderId = $message->sender_id;

        if (! $project) {
            return;
        }

        $recipients = $this->resolveMessageRecipients($project, $task, $subtask);

        $title = match ($message->message_type) {
            'feedback' => 'New Feedback',
            'follow_up' => 'New Follow-up',
            'progress_note' => 'Progress Note Added',
            'clarification' => 'Clarification Request',
            default => 'New Message',
        };

        $actionUrl = $subtask
            ? $this->relativeRoute('subtasks.messages.index', $subtask)
            : ($task ? $this->relativeRoute('tasks.messages.index', $task) : $this->relativeRoute('projects.messages.index', $project));

        $this->notifyMany($recipients, [
            'actor_id' => $senderId,
            'project_id' => $project->id,
            'task_id' => $task?->id,
            'subtask_id' => $subtask?->id,
            'workflow_message_id' => $message->id,
            'type' => 'message_created',
            'title' => $title,
            'body' => $message->body,
            'action_url' => $actionUrl,
        ], $senderId);
    }

    public function notifyFileUploaded(WorkflowFile $file): void
    {
        $project = $file->project;
        $task = $file->task;
        $subtask = $file->subtask;
        $repositoryEntry = $file->repositoryEntry;
        $uploaderId = $file->uploaded_by;

        if ($repositoryEntry) {
            $project = $repositoryEntry->project;
            $this->notifyMany(
                $this->resolveRepositoryRecipients($repositoryEntry),
                [
                    'actor_id' => $uploaderId,
                    'project_id' => $project?->id,
                    'repository_entry_id' => $repositoryEntry->id,
                    'workflow_file_id' => $file->id,
                    'type' => 'file_uploaded',
                    'title' => 'File Uploaded to Repository',
                    'body' => "A new file '{$file->original_name}' was uploaded to repository entry.",
                    'action_url' => $project ? $this->relativeRoute('repository.show', $repositoryEntry) : null,
                ],
                $uploaderId
            );

            return;
        }

        if (! $project) {
            return;
        }

        $recipients = $this->resolveFileRecipients($project, $task, $subtask);

        $contextLabel = $subtask ? 'work item' : ($task ? 'task' : 'project');

        $this->notifyMany($recipients, [
            'actor_id' => $uploaderId,
            'project_id' => $project->id,
            'task_id' => $task?->id,
            'subtask_id' => $subtask?->id,
            'workflow_file_id' => $file->id,
            'type' => 'file_uploaded',
            'title' => 'File Uploaded',
            'body' => "A new file '{$file->original_name}' was uploaded to {$contextLabel}.",
            'action_url' => $subtask
                ? $this->relativeRoute('subtasks.show', $subtask)
                : ($task ? $this->relativeRoute('tasks.show', $task) : $this->relativeRoute('projects.show', $project)),
        ], $uploaderId);
    }

    public function notifyProgressUpdated(Subtask $subtask, User $actor): void
    {
        $project = $subtask->project;

        $recipients = collect();

        // Notify assigned coordinators for the project
        $coordinatorIds = $project
            ->assignments()
            ->where('assignment_role', 'primary')
            ->whereNull('revoked_at')
            ->pluck('coordinator_id');

        User::whereIn('id', $coordinatorIds)->each(fn (User $u) => $recipients->push($u));

        // Notify Admin/PM users who have project access
        User::role(['Admin', 'PM/Manager'])->each(fn (User $u) => $recipients->push($u));

        // Remove the actor
        $recipients = $recipients->unique('id')->filter(fn (User $u) => $u->id !== $actor->id);

        $this->notifyMany($recipients, [
            'actor_id' => $actor->id,
            'project_id' => $project->id,
            'task_id' => $subtask->task_id,
            'subtask_id' => $subtask->id,
            'type' => 'progress_updated',
            'title' => 'Progress Updated',
            'body' => "Progress was updated on work item: {$subtask->title}",
            'action_url' => $this->relativeRoute('subtasks.show', $subtask),
        ]);
    }

    protected function resolveMessageRecipients(Project $project, ?Task $task = null, ?Subtask $subtask = null): Collection
    {
        $recipients = collect();

        // Admin/PM users
        User::role(['Admin', 'PM/Manager'])->each(fn (User $u) => $recipients->push($u));

        // Assigned coordinators
        $coordinatorIds = $project
            ->assignments()
            ->where('assignment_role', 'primary')
            ->whereNull('revoked_at')
            ->pluck('coordinator_id');

        User::whereIn('id', $coordinatorIds)->each(fn (User $u) => $recipients->push($u));

        // If subtask context, also include assigned subordinates
        if ($subtask) {
            $subordinateIds = SubtaskAssignment::query()
                ->where('subtask_id', $subtask->id)
                ->whereNull('revoked_at')
                ->pluck('subordinate_id');

            User::whereIn('id', $subordinateIds)->each(fn (User $u) => $recipients->push($u));
        }

        return $recipients->unique('id');
    }

    protected function resolveFileRecipients(Project $project, ?Task $task = null, ?Subtask $subtask = null): Collection
    {
        return $this->resolveMessageRecipients($project, $task, $subtask);
    }

    protected function resolveRepositoryRecipients(mixed $repositoryEntry): Collection
    {
        $recipients = collect();

        // Admin/PM users
        User::role(['Admin', 'PM/Manager'])->each(fn (User $u) => $recipients->push($u));

        // Assigned coordinators for the project
        $project = $repositoryEntry->project;
        if ($project) {
            $coordinatorIds = $project
                ->assignments()
                ->where('assignment_role', 'primary')
                ->whereNull('revoked_at')
                ->pluck('coordinator_id');

            User::whereIn('id', $coordinatorIds)->each(fn (User $u) => $recipients->push($u));
        }

        return $recipients->unique('id');
    }
}


