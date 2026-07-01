<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignSubordinateRequest;
use App\Models\Subtask;
use App\Models\SubtaskAssignment;
use App\Models\User;
use App\Services\WorkflowNotificationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SubtaskAssignmentController extends Controller
{
    public function edit(Subtask $subtask): Response
    {
        $this->authorize('assignSubordinate', $subtask);

        $subtask->load(['task', 'assignments.subordinate', 'assignments.assigner']);

        return Inertia::render('Subtasks/AssignSubordinate', [
            'pageTitle' => 'Assign Subordinate',
            'subtask' => $subtask,
            'subordinates' => User::role('Subordinate')
                ->where('is_active', true)
                ->select('id', 'name', 'email')
                ->orderBy('name')
                ->get(),
            'action' => route('subtasks.assign-subordinate.store', $subtask),
        ]);
    }

    public function store(AssignSubordinateRequest $request, Subtask $subtask, WorkflowNotificationService $notificationService): RedirectResponse
    {
        $this->authorize('assignSubordinate', $subtask);

        $subordinateId = (int) $request->validated('subordinate_id');

        $activeAssignment = $subtask->assignments()
            ->where('subordinate_id', $subordinateId)
            ->whereNull('revoked_at')
            ->first();

        if ($activeAssignment) {
            return redirect()
                ->route('subtasks.assign-subordinate.edit', $subtask)
                ->with('status', 'Subordinate already assigned.');
        }

        $assignment = SubtaskAssignment::create([
            'subtask_id' => $subtask->id,
            'subordinate_id' => $subordinateId,
            'assigned_by' => $request->user()->id,
            'assigned_at' => now(),
            'revoked_at' => null,
        ]);

        $notificationService->notifySubordinateAssigned(
            $subtask,
            User::findOrFail($subordinateId),
            $request->user()
        );

        return redirect()->route('subtasks.show', $subtask)->with('status', 'Subordinate assigned successfully.');
    }

    public function revoke(Subtask $subtask, User $user, WorkflowNotificationService $notificationService): RedirectResponse
    {
        $this->authorize('revokeSubordinateAssignment', $subtask);

        $assignment = $subtask->assignments()
            ->where('subordinate_id', $user->id)
            ->whereNull('revoked_at')
            ->first();

        if (! $assignment) {
            return redirect()->route('subtasks.show', $subtask)->with('status', 'No active assignment found for this Subordinate.');
        }

        $assignment->update([
            'revoked_at' => now(),
        ]);

        $notificationService->notifySubordinateRevoked(
            $subtask,
            $user,
            request()->user()
        );

        return redirect()->route('subtasks.show', $subtask)->with('status', 'Subordinate assignment revoked successfully.');
    }
}

