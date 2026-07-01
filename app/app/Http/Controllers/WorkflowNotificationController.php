<?php

namespace App\Http\Controllers;

use App\Models\WorkflowNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowNotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $notifications = $user->workflowNotifications()
            ->with(['actor', 'project', 'task', 'subtask', 'workflowMessage', 'workflowFile'])
            ->latest()
            ->paginate(20);

        $unreadCount = $user->unreadWorkflowNotifications()->count();

        return Inertia::render('Notifications/Index', [
            'pageTitle' => 'Notifications',
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }

    public function markRead(Request $request, WorkflowNotification $workflowNotification): RedirectResponse
    {
        abort_if($workflowNotification->user_id !== $request->user()->id, 403);

        $workflowNotification->update(['read_at' => now()]);

        return back()->with('status', 'Notification marked as read.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()
            ->unreadWorkflowNotifications()
            ->update(['read_at' => now()]);

        return back()->with('status', 'All notifications marked as read.');
    }
}
