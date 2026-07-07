<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ProvidesWorkflowFiles;
use App\Http\Controllers\Concerns\ProvidesWorkflowMessages;
use App\Http\Requests\UpdateSubtaskProgressRequest;
use App\Models\Subtask;
use App\Services\WorkflowNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MySubtaskController extends Controller
{
    use ProvidesWorkflowFiles, ProvidesWorkflowMessages;
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasRole('Subordinate') && $request->user()->can('view assigned subtasks'), 403);

        $subtasks = Subtask::query()
            ->with(['project', 'task'])
            ->withActiveAssignmentFor($request->user())
            ->whereHas('assignments', function ($query) use ($request): void {
                $query->where('subordinate_id', $request->user()->id)
                    ->whereNull('revoked_at');
            })
            ->orderByDesc('current_assigned_at')
            ->latest('subtasks.updated_at')
            ->paginate(10);

        return Inertia::render('MySubtasks/Index', [
            'pageTitle' => 'My Work Items',
            'pageSubtitle' => 'Work items currently assigned to you.',
            'subtasks' => $subtasks,
        ]);
    }

    public function show(Request $request, Subtask $subtask): Response
    {
        $this->authorize('viewAssigned', $subtask);

        $subtask->load(['project', 'task']);
        $subtask->loadActiveAssignmentFor($request->user());

        return Inertia::render('MySubtasks/Show', [
            'pageTitle' => 'Work Item Details',
            'subtask' => $subtask,
            'statuses' => ['pending', 'in_progress', 'submitted'],
            'action' => route('subtasks.mine.progress', $subtask),
            'closeHref' => route('my-work-items.index'),
            ...$this->workflowFileProps($subtask, $request->user(), 'Evidence / Attachments'),
            ...$this->messageThreadProps($subtask, $request->user()),
        ]);
    }

    public function updateProgress(UpdateSubtaskProgressRequest $request, Subtask $subtask): RedirectResponse
    {
        $this->authorize('updateAssignedProgress', $subtask);

        $updates = $request->validated();

        if ($updates['status'] === 'submitted' && $subtask->submitted_at === null) {
            $updates['submitted_at'] = now();
        }

        $subtask->update($updates);

        app(WorkflowNotificationService::class)->notifyProgressUpdated($subtask, $request->user());

        return redirect()->route('subtasks.mine.show', $subtask)->with('status', 'Progress updated successfully.');
    }
}









