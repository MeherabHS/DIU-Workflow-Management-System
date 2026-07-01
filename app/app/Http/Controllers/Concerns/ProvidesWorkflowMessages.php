<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Project;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowMessage;
use Illuminate\Database\Eloquent\Builder;

trait ProvidesWorkflowMessages
{
    protected function messageThreadProps(Project|Task|Subtask $context, User $user): array
    {
        [$ability, $storeUrl] = match (true) {
            $context instanceof Project => ['createProject', route('projects.messages.store', $context)],
            $context instanceof Task => ['createTask', route('tasks.messages.store', $context)],
            $context instanceof Subtask => ['createSubtask', route('subtasks.messages.store', $context)],
        };

        $canCreateMessage = $user->can($ability, [WorkflowMessage::class, $context]);

        return [
            'messageSectionTitle' => 'Feedback / Follow-up',
            'messages' => $this->formatWorkflowMessages($this->workflowMessageQuery($context, $user)->get()),
            'canCreateMessage' => $canCreateMessage,
            'messageStoreUrl' => $canCreateMessage ? $storeUrl : null,
            'allowedMessageTypes' => $this->allowedMessageTypes(),
            'defaultMessageType' => $this->defaultMessageType($context, $user),
        ];
    }

    protected function workflowMessageQuery(Project|Task|Subtask $context, User $user): Builder
    {
        $query = WorkflowMessage::query()->with('sender')->oldest();

        if ($context instanceof Project) {
            $query->where('project_id', $context->id)->whereNull('task_id')->whereNull('subtask_id');
        } elseif ($context instanceof Task) {
            $query->where('task_id', $context->id)->whereNull('subtask_id');
        } else {
            $query->where('subtask_id', $context->id);
        }

        // Subordinate cross-visibility filter:
        // Subordinates can only see their own messages + messages from Admin/PM/Manager/Coordinator.
        // They must NOT see messages from other Subordinates on the same work item.
        if ($user->hasRole('Subordinate')) {
            $managementRoles = ['Admin', 'PM/Manager', 'Manager', 'Coordinator'];
            $managementRoleIds = User::whereHas('roles', function ($q) use ($managementRoles): void {
                $q->whereIn('name', $managementRoles);
            })->pluck('id');

            $query->where(function (Builder $q) use ($user, $managementRoleIds): void {
                $q->where('sender_id', $user->id)
                    ->orWhereIn('sender_id', $managementRoleIds);
            });
        }

        return $query;
    }

    /**
     * Apply Subordinate cross-visibility filter to an existing query.
     * Subordinates can only see their own messages + messages from management roles.
     */
    protected function applySubordinateVisibilityFilter(Builder $query, User $user): Builder
    {
        if (! $user->hasRole('Subordinate')) {
            return $query;
        }

        $managementRoleIds = User::whereHas('roles', function ($q): void {
            $q->whereIn('name', ['Admin', 'PM/Manager', 'Manager', 'Coordinator']);
        })->pluck('id');

        return $query->where(function (Builder $q) use ($user, $managementRoleIds): void {
            $q->where('sender_id', $user->id)
                ->orWhereIn('sender_id', $managementRoleIds);
        });
    }

    protected function formatWorkflowMessages($messages): array
    {
        return $messages->map(function (WorkflowMessage $message): array {
            $sender = $message->sender;
            $name = $sender?->name ?? 'Unknown User';

            return [
                'id' => $message->id,
                'body' => $message->body,
                'message_type' => $message->message_type,
                'message_type_label' => $this->messageTypeLabel($message->message_type),
                'sender_id' => $sender?->id,
                'sender_name' => $name,
                'sender_role' => $sender?->getRoleNames()->first() ?? 'User',
                'sender_initials' => $this->initials($name),
                'created_at' => $message->created_at?->toISOString(),
                'created_at_human' => $message->created_at?->diffForHumans(),
            ];
        })->all();
    }

    protected function allowedMessageTypes(): array
    {
        return collect(WorkflowMessage::TYPES)
            ->map(fn (string $type): array => ['value' => $type, 'label' => $this->messageTypeLabel($type)])
            ->all();
    }

    protected function messageTypeLabel(string $type): string
    {
        return match ($type) {
            'follow_up' => 'Follow-up',
            'progress_note' => 'Progress Note',
            default => str_replace(' ', ' ', ucwords(str_replace('_', ' ', $type))),
        };
    }

    protected function defaultMessageType(Project|Task|Subtask $context, User $user): string
    {
        return $context instanceof Subtask && $user->hasRole('Subordinate')
            ? 'progress_note'
            : 'message';
    }

    protected function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = collect($parts)->filter()->take(2)->map(fn (string $part): string => strtoupper(substr($part, 0, 1)))->implode('');

        return $letters !== '' ? $letters : 'U';
    }
}