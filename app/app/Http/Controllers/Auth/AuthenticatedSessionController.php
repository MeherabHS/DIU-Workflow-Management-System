<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Subtask;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $this->flashLoginWorkSummary($request);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Flash a work summary message for Coordinator/Subordinate after login.
     */
    protected function flashLoginWorkSummary(Request $request): void
    {
        $user = $request->user();
        if (! $user) {
            return;
        }

        $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->map(fn ($r) => strtolower($r))->all() : [];

        if (in_array('coordinator', $roles, true)) {
            $this->flashCoordinatorSummary($user);
        } elseif (in_array('subordinate', $roles, true)) {
            $this->flashSubordinateSummary($user);
        }
    }

    protected function flashCoordinatorSummary($user): void
    {
        $projects = $user->activeAssignedProjects()
            ->withCount(['tasks'])
            ->get();

        $projectCount = $projects->count();
        if ($projectCount === 0) {
            return;
        }

        $ongoingTaskCount = 0;
        $pendingWorkItemCount = 0;

        foreach ($projects as $project) {
            $ongoingTaskCount += $project->tasks()
                ->whereIn('status', ['pending', 'in_progress'])
                ->count();
            $pendingWorkItemCount += $project->subtasks()
                ->whereIn('status', ['pending'])
                ->count();
        }

        $parts = [];
        if ($ongoingTaskCount > 0) {
            $parts[] = "{$ongoingTaskCount} ongoing task".($ongoingTaskCount > 1 ? 's' : '');
        }
        if ($pendingWorkItemCount > 0) {
            $parts[] = "{$pendingWorkItemCount} pending work item".($pendingWorkItemCount > 1 ? 's' : '');
        }

        if (empty($parts)) {
            $message = "You have no pending tasks across {$projectCount} assigned project".($projectCount > 1 ? 's' : '').".";
        } else {
            $message = 'You have '.implode(' and ', $parts)." across {$projectCount} assigned project".($projectCount > 1 ? 's' : '').'.';
        }

        session()->flash('login_work_summary', $message);
    }

    protected function flashSubordinateSummary($user): void
    {
        $subtasks = $user->activeAssignedSubtasks()
            ->with(['assignments' => fn ($q) => $q->whereNull('revoked_at')->with('assigner')])
            ->get();

        $totalCount = $subtasks->count();
        if ($totalCount === 0) {
            return;
        }

        $pendingCount = $subtasks->where('status', 'pending')->count();
        $ongoingCount = $subtasks->where('status', 'in_progress')->count();

        // Find the most common assigner name
        $assignerCounts = [];
        foreach ($subtasks as $st) {
            foreach ($st->assignments as $assignment) {
                if ($assignment->assigner) {
                    $name = $assignment->assigner->name;
                    $assignerCounts[$name] = ($assignerCounts[$name] ?? 0) + 1;
                }
            }
        }

        $topAssigner = '';
        if (! empty($assignerCounts)) {
            arsort($assignerCounts);
            $topAssigner = array_key_first($assignerCounts);
        }

        $parts = [];
        if ($pendingCount > 0) {
            $parts[] = "{$pendingCount} pending work item".($pendingCount > 1 ? 's' : '');
        }
        if ($ongoingCount > 0) {
            $parts[] = "{$ongoingCount} ongoing work item".($ongoingCount > 1 ? 's' : '');
        }

        if (empty($parts)) {
            $parts[] = "{$totalCount} work item".($totalCount > 1 ? 's' : '');
        }

        $message = 'You have '.implode(' and ', $parts).' assigned';
        if ($topAssigner) {
            $message .= " by {$topAssigner}";
        }
        $message .= '.';

        session()->flash('login_work_summary', $message);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
