<?php

namespace App\Http\Controllers;

use App\Helpers\CacheHelper;
use App\Http\Controllers\Concerns\ProvidesWorkflowFiles;
use App\Http\Controllers\Concerns\ProvidesWorkflowMessages;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Project;
use App\Models\Subtask;
use App\Models\SubtaskAssignment;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowFile;
use App\Services\AuditLogService;
use App\Services\WorkflowFileService;
use App\Services\WorkflowNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $taskContext = new Task(['project_id' => $project->id]);
        $canAssignSubordinateOnCreate = $request->user()->can('createForTask', [Subtask::class, $taskContext]);

        return Inertia::render('Tasks/Form', $this->formData($project, new Task([
            'status' => 'pending',
        ])) + [
            'pageTitle' => 'Create Task',
            'submitLabel' => 'Create Task',
            'method' => 'post',
            'action' => route('project.tasks.store', $project),
            'canAttachOnCreate' => $request->user()->can('create', [WorkflowFile::class, $project]),
            'allowedFileTypes' => app(WorkflowFileService::class)->acceptAttribute(),
            'maxFileSizeMb' => app(WorkflowFileService::class)->maxUploadMegabytes(),
            'canAssignSubordinateOnCreate' => $canAssignSubordinateOnCreate,
            'assignableSubordinates' => $canAssignSubordinateOnCreate ? $this->activeSubordinates() : [],
        ]);
    }
    public function store(StoreTaskRequest $request, Project $project, WorkflowNotificationService $notificationService): RedirectResponse
    {
        $this->authorize('createInProject', [Task::class, $project]);

        $validated = $request->validated();
        $uploadedFile = $validated['file'] ?? null;
        $subordinateId = $validated['subordinate_id'] ?? null;
        unset($validated['file'], $validated['subordinate_id']);

        $task = DB::transaction(function () use ($request, $project, $validated, $subordinateId, $notificationService) {
            $task = $project->tasks()->create([
                ...$validated,
                'created_by' => $request->user()->id,
            ]);

            if ($subordinateId) {
                $this->createDefaultWorkItemAssignment($task, (int) $subordinateId, $request->user(), $notificationService);
            }

            return $task;
        });

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
        $canAssignSubordinateOnTask = $canCreateSubtask;
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
            'canAssignSubordinateOnTask' => $canAssignSubordinateOnTask,
            'assignSubordinateAction' => $canAssignSubordinateOnTask ? route('tasks.assign-subordinate.store', $task) : null,
            'assignableSubordinates' => $canAssignSubordinateOnTask ? $this->activeSubordinates() : [],
            'assignedSubordinates' => $this->assignedSubordinatesForTask($task),
            'canRevokeSubordinate' => $canRevokeSubordinate,
            'canUpdateTask' => $canUpdateTask,
            'actions' => $actions,
            ...$this->workflowFileProps($task, $user, 'Attachments'),
            ...$this->messageThreadProps($task, $user),
            'canShowComparison' => $this->canShowComparison($user),
            ...$this->comparisonProps($task, $user),
        ]);
    }
    public function assignSubordinate(Request $request, Task $task, WorkflowNotificationService $notificationService): RedirectResponse
    {
        $this->authorize('createForTask', [Subtask::class, $task]);

        $validated = $request->validate([
            'subordinate_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $subordinate = User::find($value);

                    if (! $subordinate?->is_active || ! $subordinate->hasRole('Subordinate')) {
                        $fail('The selected subordinate is invalid.');
                    }
                },
            ],
        ]);

        DB::transaction(function () use ($request, $task, $validated, $notificationService): void {
            $this->createDefaultWorkItemAssignment($task, (int) $validated['subordinate_id'], $request->user(), $notificationService);
        });

        CacheHelper::forgetDashboardForUsers(
            User::where('is_active', true)->whereHas('roles')->pluck('id')->all()
        );

        return redirect()->route('tasks.show', $task)->with('status', 'Subordinate assigned successfully.');
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

    protected function createDefaultWorkItemAssignment(Task $task, int $subordinateId, User $actor, WorkflowNotificationService $notificationService): Subtask
    {
        $task->loadMissing('project');

        $subtask = $task->subtasks()
            ->whereDoesntHave('assignments', fn ($query) => $query->whereNull('revoked_at'))
            ->oldest()
            ->first();

        if (! $subtask) {
            $subtask = $task->subtasks()->create([
                'project_id' => $task->project_id,
                'title' => $task->title,
                'description' => $task->description,
                'created_by' => $actor->id,
                'status' => 'pending',
                'priority' => $task->priority,
                'deadline' => $task->deadline,
            ]);

            $this->audit->logSubtaskCreated($subtask);
        }

        $this->authorize('assignSubordinate', $subtask);

        $activeAssignment = $subtask->assignments()
            ->where('subordinate_id', $subordinateId)
            ->whereNull('revoked_at')
            ->first();

        if (! $activeAssignment) {
            SubtaskAssignment::create([
                'subtask_id' => $subtask->id,
                'subordinate_id' => $subordinateId,
                'assigned_by' => $actor->id,
                'assigned_at' => now(),
                'revoked_at' => null,
            ]);

            $notificationService->notifySubordinateAssigned(
                $subtask,
                User::findOrFail($subordinateId),
                $actor
            );
        }

        return $subtask;
    }

    protected function activeSubordinates()
    {
        return User::role('Subordinate')
            ->where('is_active', true)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();
    }

    protected function assignedSubordinatesForTask(Task $task): array
    {
        return $task->subtasks
            ->flatMap(fn (Subtask $subtask) => $subtask->assignments->whereNull('revoked_at'))
            ->map(fn (SubtaskAssignment $assignment) => $assignment->subordinate)
            ->filter()
            ->unique('id')
            ->values()
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email])
            ->all();
    }

    protected function canShowComparison(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'PM/Manager']);
    }
    protected function storeInitialTaskFile(Request $request, Task $task, mixed $uploadedFile): void
    {
        $file = app(WorkflowFileService::class)->storeUploadedFile(
            $uploadedFile,
            $task,
            $request->user(),
            'attachment'
        );

        app(WorkflowNotificationService::class)->notifyFileUploaded($file);
    }
}











