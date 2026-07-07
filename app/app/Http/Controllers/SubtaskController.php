<?php

namespace App\Http\Controllers;

use App\Helpers\CacheHelper;
use App\Http\Controllers\Concerns\ProvidesWorkflowFiles;
use App\Http\Controllers\Concerns\ProvidesWorkflowMessages;
use App\Http\Requests\StoreSubtaskRequest;
use App\Http\Requests\UpdateSubtaskRequest;
use App\Models\Subtask;
use App\Models\SubtaskAssignment;
use App\Models\Task;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\WorkflowNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SubtaskController extends Controller
{
    use ProvidesWorkflowFiles, ProvidesWorkflowMessages;

    public function __construct(protected AuditLogService $audit)
    {
    }

    public function create(Task $task): Response
    {
        $this->authorize('createForTask', [Subtask::class, $task]);

        return Inertia::render('Subtasks/Form', $this->formData($task, new Subtask([
            'status' => 'pending',
        ])) + [
            'pageTitle' => 'Create Work Item',
            'submitLabel' => 'Create Work Item',
            'method' => 'post',
            'action' => route('tasks.subtasks.store', $task),
        ]);
    }

    public function store(StoreSubtaskRequest $request, Task $task, WorkflowNotificationService $notificationService): RedirectResponse
    {
        $this->authorize('createForTask', [Subtask::class, $task]);

        $validated = $request->validated();
        $subordinateId = $validated['subordinate_id'] ?? null;
        unset($validated['subordinate_id']);

        $subtask = DB::transaction(function () use ($request, $task, $validated, $subordinateId, $notificationService) {
            $subtask = $task->subtasks()->create([
                ...$validated,
                'project_id' => $task->project_id,
                'created_by' => $request->user()->id,
            ]);

            if ($subordinateId) {
                $this->authorize('assignSubordinate', $subtask);

                SubtaskAssignment::create([
                    'subtask_id' => $subtask->id,
                    'subordinate_id' => (int) $subordinateId,
                    'assigned_by' => $request->user()->id,
                    'assigned_at' => now(),
                    'revoked_at' => null,
                ]);

                $notificationService->notifySubordinateAssigned(
                    $subtask,
                    User::findOrFail((int) $subordinateId),
                    $request->user()
                );
            }

            return $subtask;
        });

        $this->audit->logSubtaskCreated($subtask);

        // Clear dashboard cache for all role-holding users
        CacheHelper::forgetDashboardForUsers(
            User::where('is_active', true)->whereHas('roles')->pluck('id')->all()
        );

        return redirect()->route('subtasks.show', $subtask)->with('status', 'Work item created successfully.');
    }

    public function show(Request $request, Subtask $subtask): Response
    {
        $this->authorize('view', $subtask);

        $subtask->load([
            'project',
            'task',
            'creator',
            'assignments.subordinate',
            'assignments.assigner',
        ]);

        $user = $request->user();

        $canAssignSubordinate = $user->can('assignSubordinate', $subtask);
        $canRevokeSubordinate = $user->can('revokeSubordinateAssignment', $subtask);
        $canUpdateSubtask = $user->can('update', $subtask);
        $actions = array_values(array_filter([
            $canAssignSubordinate ? 'Assign Subordinate' : null,
            $canUpdateSubtask ? 'Edit' : null,
            $canRevokeSubordinate ? 'Revoke Assignment' : null,
        ]));

        return Inertia::render('Subtasks/Show', [
            'pageTitle' => 'Work Item Details',
            'subtask' => $subtask,
            'canAssignSubordinate' => $canAssignSubordinate,
            'canRevokeSubordinate' => $canRevokeSubordinate,
            'canUpdateSubtask' => $canUpdateSubtask,
            'actions' => $actions,
            ...$this->workflowFileProps($subtask, $user, 'Evidence / Attachments'),
            ...$this->messageThreadProps($subtask, $user),
            'canShowComparison' => $user->hasAnyRole(['Admin', 'PM/Manager']),
            ...$this->comparisonProps($subtask, $user),
        ]);
    }

    public function edit(Subtask $subtask): Response
    {
        $this->authorize('update', $subtask);

        $subtask->loadMissing('task.project');

        return Inertia::render('Subtasks/Form', $this->formData($subtask->task, $subtask) + [
            'pageTitle' => 'Edit Work Item',
            'submitLabel' => 'Update Work Item',
            'method' => 'patch',
            'action' => route('subtasks.update', $subtask),
        ]);
    }

    public function update(UpdateSubtaskRequest $request, Subtask $subtask): RedirectResponse
    {
        $this->authorize('update', $subtask);

        $changes = array_keys(array_diff_assoc($request->validated(), $subtask->getOriginal()));
        $subtask->update($request->validated());

        $this->audit->logSubtaskUpdated($subtask, ['fields' => $changes]);

        return redirect()->route('subtasks.show', $subtask)->with('status', 'Work item updated successfully.');
    }

    protected function formData(Task $task, Subtask $subtask): array
    {
        $subordinateUsers = User::role('Subordinate')
            ->where('is_active', true)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return [
            'task' => $task,
            'project' => $task->project,
            'subtask' => $subtask,
            'subordinateUsers' => $subordinateUsers,
            'assignableSubordinates' => $subordinateUsers,
            'statuses' => $this->statuses(),
        ];
    }

    protected function statuses(): array
    {
        return ['pending', 'in_progress', 'submitted', 'approved', 'revision_required', 'completed', 'cancelled'];
    }
}




