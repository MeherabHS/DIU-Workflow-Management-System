<?php

namespace App\Http\Controllers;

use App\Helpers\CacheHelper;
use App\Http\Controllers\Concerns\ProvidesWorkflowFiles;
use App\Http\Controllers\Concerns\ProvidesWorkflowMessages;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Project;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowFile;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    use ProvidesWorkflowFiles, ProvidesWorkflowMessages;

    public function __construct(protected AuditLogService $audit)
    {
    }

    public function index(Request $request, Project $project): Response
    {
        $this->authorize('viewProjectTasks', [Task::class, $project]);

        return Inertia::render('Tasks/Index', [
            'pageTitle' => 'Project Tasks',
            'pageSubtitle' => 'Tasks under selected project.',
            'project' => $project,
            'primaryAction' => 'Create Task',
            'canCreateTask' => $request->user()->can('createInProject', [Task::class, $project]),
            'tasks' => $project->tasks()
                ->with(['creator', 'assignedUser'])
                ->withCount('subtasks')
                ->latest('updated_at')
                ->paginate(10),
        ]);
    }

    public function create(Request $request, Project $project): Response
    {
        $this->authorize('createInProject', [Task::class, $project]);

        return Inertia::render('Tasks/Form', $this->formData($project, new Task([
            'status' => 'pending',
        ])) + [
            'pageTitle' => 'Create Task',
            'submitLabel' => 'Create Task',
            'method' => 'post',
            'action' => route('project.tasks.store', $project),
            'canAttachOnCreate' => $request->user()->can('create', [WorkflowFile::class, $project]),
            'allowedFileTypes' => '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.txt,.csv,.zip',
            'maxFileSizeMb' => 10,
        ]);
    }

    public function store(StoreTaskRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('createInProject', [Task::class, $project]);

        $validated = $request->validated();
        $uploadedFile = $validated['file'] ?? null;
        unset($validated['file']);

        $task = $project->tasks()->create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        if ($uploadedFile) {
            $this->authorize('create', [WorkflowFile::class, $task]);
            $this->storeInitialTaskFile($request, $task, $uploadedFile);
        }

        $this->audit->logTaskCreated($task);

        // Clear dashboard cache for all role-holding users
        CacheHelper::forgetDashboardForUsers(
            User::where('is_active', true)->whereHas('roles')->pluck('id')->all()
        );

        return redirect()->route('tasks.show', $task)->with('status', 'Task created successfully.');
    }

    public function show(Request $request, Task $task): Response
    {
        $this->authorize('view', $task);

        $task->load([
            'project.department',
            'creator',
            'assignedUser',
            'subtasks.creator',
            'subtasks.assignments.subordinate',
        ]);

        $user = $request->user();

        $canCreateSubtask = $user->can('createForTask', [Subtask::class, $task]);
        $canAssignSubordinate = $task->subtasks->contains(fn (Subtask $subtask): bool => $user->can('assignSubordinate', $subtask));
        $canRevokeSubordinate = $task->subtasks->contains(fn (Subtask $subtask): bool => $user->can('revokeSubordinateAssignment', $subtask));
        $canUpdateTask = $user->can('update', $task);
        $actions = array_values(array_filter([
            $canCreateSubtask ? 'Create Work Item' : null,
            'View Work Items',
            $canUpdateTask ? 'Edit' : null,
        ]));

        return Inertia::render('Tasks/Show', [
            'pageTitle' => 'Task Details',
            'task' => $task,
            'canCreateSubtask' => $canCreateSubtask,
            'canAssignSubordinate' => $canAssignSubordinate,
            'canRevokeSubordinate' => $canRevokeSubordinate,
            'canUpdateTask' => $canUpdateTask,
            'actions' => $actions,
            ...$this->workflowFileProps($task, $user, 'Attachments'),
            ...$this->messageThreadProps($task, $user),
            ...$this->comparisonProps($task, $user),
        ]);
    }

    public function edit(Task $task): Response
    {
        $this->authorize('update', $task);

        return Inertia::render('Tasks/Form', $this->formData($task->project, $task) + [
            'pageTitle' => 'Edit Task',
            'submitLabel' => 'Update Task',
            'method' => 'patch',
            'action' => route('tasks.update', $task),
            'canAttachOnCreate' => false,
        ]);
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $changes = array_keys(array_diff_assoc($request->validated(), $task->getOriginal()));
        $task->update($request->validated());

        $this->audit->logTaskUpdated($task, ['fields' => $changes]);

        // Clear dashboard cache for all role-holding users
        CacheHelper::forgetDashboardForUsers(
            User::where('is_active', true)->whereHas('roles')->pluck('id')->all()
        );

        return redirect()->route('tasks.show', $task)->with('status', 'Task updated successfully.');
    }

    protected function formData(Project $project, Task $task): array
    {
        return [
            'project' => $project,
            'task' => $task,
            'statuses' => $this->statuses(),
        ];
    }

    protected function statuses(): array
    {
        return ['pending', 'in_progress', 'submitted', 'approved', 'revision_required', 'completed', 'cancelled'];
    }

    protected function storeInitialTaskFile(Request $request, Task $task, mixed $uploadedFile): void
    {
        $extension = strtolower($uploadedFile->getClientOriginalExtension());
        $storedName = Str::uuid().($extension ? '.'.$extension : '');
        $directory = 'workflow-files/'.now()->format('Y/m');
        $path = $uploadedFile->storeAs($directory, $storedName, 'local');

        WorkflowFile::create([
            'project_id' => $task->project_id,
            'task_id' => $task->id,
            'uploaded_by' => $request->user()->id,
            'original_name' => $uploadedFile->getClientOriginalName(),
            'stored_name' => $storedName,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $uploadedFile->getMimeType(),
            'size' => $uploadedFile->getSize(),
            'file_category' => 'attachment',
        ]);
    }
}
