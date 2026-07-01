<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ProvidesWorkflowMessages;
use App\Models\Project;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\WorkflowMessage;
use App\Services\WorkflowNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowMessageController extends Controller
{
    use ProvidesWorkflowMessages;

    public function projectIndex(Project $project): Response
    {
        $this->authorize('viewProject', [WorkflowMessage::class, $project]);

        return Inertia::render('Messages/ProjectMessages', [
            'pageTitle' => 'Project Feedback / Follow-up',
            'contextTitle' => $project->title,
            ...$this->messageThreadProps($project, request()->user()),
        ]);
    }

    public function projectStore(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('createProject', [WorkflowMessage::class, $project]);

        $this->storeMessage($request, ['project_id' => $project->id], app(WorkflowNotificationService::class));

        return back()->with('status', 'Feedback message sent.');
    }

    public function taskIndex(Task $task): Response
    {
        $this->authorize('viewTask', [WorkflowMessage::class, $task]);

        return Inertia::render('Messages/TaskMessages', [
            'pageTitle' => 'Task Feedback / Follow-up',
            'contextTitle' => $task->title,
            ...$this->messageThreadProps($task, request()->user()),
        ]);
    }

    public function taskStore(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('createTask', [WorkflowMessage::class, $task]);

        $this->storeMessage($request, ['project_id' => $task->project_id, 'task_id' => $task->id], app(WorkflowNotificationService::class));

        return back()->with('status', 'Feedback message sent.');
    }

    public function subtaskIndex(Subtask $subtask): Response
    {
        $this->authorize('viewSubtask', [WorkflowMessage::class, $subtask]);

        return Inertia::render('Messages/SubtaskMessages', [
            'pageTitle' => 'Subtask Feedback / Follow-up',
            'contextTitle' => $subtask->title,
            ...$this->messageThreadProps($subtask, request()->user()),
        ]);
    }

    public function subtaskStore(Request $request, Subtask $subtask): RedirectResponse
    {
        $this->authorize('createSubtask', [WorkflowMessage::class, $subtask]);

        $this->storeMessage($request, [
            'project_id' => $subtask->project_id,
            'task_id' => $subtask->task_id,
            'subtask_id' => $subtask->id,
        ], app(WorkflowNotificationService::class));

        return back()->with('status', 'Feedback message sent.');
    }

    protected function storeMessage(Request $request, array $context, WorkflowNotificationService $notificationService): WorkflowMessage
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'message_type' => ['nullable', 'string', Rule::in(WorkflowMessage::TYPES)],
        ]);

        abort_unless($context['project_id'] ?? $context['task_id'] ?? $context['subtask_id'] ?? null, 422);

        $message = WorkflowMessage::create([
            ...$context,
            'sender_id' => $request->user()->id,
            'message_type' => $validated['message_type'] ?? 'message',
            'body' => $validated['body'],
            'visibility' => 'thread',
        ]);

        $notificationService->notifyMessageCreated($message);

        return $message;
    }
}
