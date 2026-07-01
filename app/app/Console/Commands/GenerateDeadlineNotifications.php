<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\Subtask;
use App\Models\SubtaskAssignment;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Services\WorkflowNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GenerateDeadlineNotifications extends Command
{
    protected $signature = 'workflow:deadline-alerts';
    protected $description = 'Generate deadline reminder and overdue notifications for projects, tasks, and work items';

    public function handle(WorkflowNotificationService $notificationService): int
    {
        $now = now();
        $tomorrow = $now->copy()->addDay();

        $this->info('Checking deadlines...');

        $this->handleProjectDeadlines($now, $tomorrow, $notificationService);
        $this->handleTaskDeadlines($now, $tomorrow, $notificationService);
        $this->handleSubtaskDeadlines($now, $tomorrow, $notificationService);

        $this->info('Deadline alert generation complete.');

        return Command::SUCCESS;
    }

    /**
     * Generate a relative URL path for a named route.
     */
    protected function relativeRoute(string $name, mixed $params = []): string
    {
        return (string) parse_url(route($name, $params), PHP_URL_PATH);
    }

    protected function handleProjectDeadlines($now, $tomorrow, WorkflowNotificationService $notificationService): void
    {
        // Deadline reminders (within next 24 hours)
        Project::query()
            ->whereNotNull('deadline')
            ->where('deadline', '>=', $now->toDateString())
            ->where('deadline', '<=', $tomorrow->toDateString())
            ->whereNotIn('status', ['completed', 'cancelled', 'archived'])
            ->each(function (Project $project) use ($notificationService): void {
                $recipients = $this->resolveProjectRecipients($project);
                $this->notifyManyWithoutDuplicates(
                    $recipients,
                    $project,
                    null,
                    null,
                    'deadline_reminder',
                    "Project deadline approaching: {$project->title}",
                    "The project '{$project->title}' has a deadline within 24 hours.",
                    $this->relativeRoute('projects.show', $project),
                    $notificationService
                );
            });

        // Overdue
        Project::query()
            ->whereNotNull('deadline')
            ->where('deadline', '<', $now->toDateString())
            ->whereNotIn('status', ['completed', 'cancelled', 'archived'])
            ->each(function (Project $project) use ($notificationService): void {
                $recipients = $this->resolveProjectRecipients($project);
                $this->notifyManyWithoutDuplicates(
                    $recipients,
                    $project,
                    null,
                    null,
                    'overdue_alert',
                    "Project overdue: {$project->title}",
                    "The project '{$project->title}' is past its deadline.",
                    $this->relativeRoute('projects.show', $project),
                    $notificationService
                );
            });
    }

    protected function handleTaskDeadlines($now, $tomorrow, WorkflowNotificationService $notificationService): void
    {
        Task::query()
            ->whereNotNull('deadline')
            ->where('deadline', '>=', $now->toDateString())
            ->where('deadline', '<=', $tomorrow->toDateString())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->each(function (Task $task) use ($notificationService): void {
                $recipients = $this->resolveTaskRecipients($task);
                $this->notifyManyWithoutDuplicates(
                    $recipients,
                    $task->project,
                    $task,
                    null,
                    'deadline_reminder',
                    "Task deadline approaching: {$task->title}",
                    "The task '{$task->title}' has a deadline within 24 hours.",
                    $this->relativeRoute('tasks.show', $task),
                    $notificationService
                );
            });

        Task::query()
            ->whereNotNull('deadline')
            ->where('deadline', '<', $now->toDateString())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->each(function (Task $task) use ($notificationService): void {
                $recipients = $this->resolveTaskRecipients($task);
                $this->notifyManyWithoutDuplicates(
                    $recipients,
                    $task->project,
                    $task,
                    null,
                    'overdue_alert',
                    "Task overdue: {$task->title}",
                    "The task '{$task->title}' is past its deadline.",
                    $this->relativeRoute('tasks.show', $task),
                    $notificationService
                );
            });
    }

    protected function handleSubtaskDeadlines($now, $tomorrow, WorkflowNotificationService $notificationService): void
    {
        Subtask::query()
            ->whereNotNull('deadline')
            ->where('deadline', '>=', $now->toDateString())
            ->where('deadline', '<=', $tomorrow->toDateString())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->each(function (Subtask $subtask) use ($notificationService): void {
                $recipients = $this->resolveSubtaskRecipients($subtask);
                $this->notifyManyWithoutDuplicates(
                    $recipients,
                    $subtask->project,
                    null,
                    $subtask,
                    'deadline_reminder',
                    "Work item deadline approaching: {$subtask->title}",
                    "The work item '{$subtask->title}' has a deadline within 24 hours.",
                    $this->relativeRoute('subtasks.mine.show', $subtask),
                    $notificationService
                );
            });

        Subtask::query()
            ->whereNotNull('deadline')
            ->where('deadline', '<', $now->toDateString())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->each(function (Subtask $subtask) use ($notificationService): void {
                $recipients = $this->resolveSubtaskRecipients($subtask);
                $this->notifyManyWithoutDuplicates(
                    $recipients,
                    $subtask->project,
                    null,
                    $subtask,
                    'overdue_alert',
                    "Work item overdue: {$subtask->title}",
                    "The work item '{$subtask->title}' is past its deadline.",
                    $this->relativeRoute('subtasks.mine.show', $subtask),
                    $notificationService
                );
            });
    }

    protected function resolveProjectRecipients(Project $project): Collection
    {
        $recipients = collect();

        // Assigned coordinators
        $coordinatorIds = $project
            ->assignments()
            ->where('assignment_role', 'primary')
            ->whereNull('revoked_at')
            ->pluck('coordinator_id');

        User::whereIn('id', $coordinatorIds)->each(fn (User $u) => $recipients->push($u));

        // Admin/PM oversight
        User::role(['Admin', 'PM/Manager'])->each(fn (User $u) => $recipients->push($u));

        return $recipients->unique('id');
    }

    protected function resolveTaskRecipients(Task $task): Collection
    {
        $recipients = collect();

        // Assigned coordinators for the project
        $coordinatorIds = $task->project
            ->assignments()
            ->where('assignment_role', 'primary')
            ->whereNull('revoked_at')
            ->pluck('coordinator_id');

        User::whereIn('id', $coordinatorIds)->each(fn (User $u) => $recipients->push($u));

        // Assigned user on the task
        if ($task->assigned_to) {
            $user = User::find($task->assigned_to);
            if ($user) {
                $recipients->push($user);
            }
        }

        // Admin/PM oversight
        User::role(['Admin', 'PM/Manager'])->each(fn (User $u) => $recipients->push($u));

        return $recipients->unique('id');
    }

    protected function resolveSubtaskRecipients(Subtask $subtask): Collection
    {
        $recipients = collect();

        // Assigned subordinates
        $subordinateIds = SubtaskAssignment::query()
            ->where('subtask_id', $subtask->id)
            ->whereNull('revoked_at')
            ->pluck('subordinate_id');

        User::whereIn('id', $subordinateIds)->each(fn (User $u) => $recipients->push($u));

        // Assigned coordinators for the project
        $coordinatorIds = $subtask->project
            ->assignments()
            ->where('assignment_role', 'primary')
            ->whereNull('revoked_at')
            ->pluck('coordinator_id');

        User::whereIn('id', $coordinatorIds)->each(fn (User $u) => $recipients->push($u));

        // Admin/PM oversight
        User::role(['Admin', 'PM/Manager'])->each(fn (User $u) => $recipients->push($u));

        return $recipients->unique('id');
    }

    protected function notifyManyWithoutDuplicates(
        Collection $recipients,
        ?Project $project,
        ?Task $task,
        ?Subtask $subtask,
        string $type,
        string $title,
        string $body,
        ?string $actionUrl,
        WorkflowNotificationService $notificationService
    ): void {
        $today = now()->toDateString();

        foreach ($recipients as $user) {
            // Check for existing notification of same type for same user/context today
            $existing = WorkflowNotification::query()
                ->where('user_id', $user->id)
                ->where('type', $type)
                ->where('project_id', $project?->id)
                ->where('task_id', $task?->id)
                ->where('subtask_id', $subtask?->id)
                ->whereDate('created_at', $today)
                ->exists();

            if ($existing) {
                continue;
            }

            $notificationService->notifyUser($user, [
                'project_id' => $project?->id,
                'task_id' => $task?->id,
                'subtask_id' => $subtask?->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'action_url' => $actionUrl,
            ]);
        }
    }
}
