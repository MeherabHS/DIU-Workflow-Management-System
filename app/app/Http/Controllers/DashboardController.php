<?php

namespace App\Http\Controllers;

use App\Helpers\CacheHelper;
use App\Models\Department;
use App\Models\Project;
use App\Models\Subtask;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): RedirectResponse|Response
    {
        $user = auth()->user();

        if ($redirect = $this->roleHomeRoute($user)) {
            return redirect()->route($redirect);
        }

        $dashboardLinks = collect($this->dashboardLinks())
            ->filter(fn (array $dashboardLink): bool => $user->can($dashboardLink['permission']))
            ->values()
            ->all();

        return Inertia::render('Dashboard', [
            'pageTitle' => 'Dashboard',
            'pageSubtitle' => 'Project, task, repository, and team workflow',
            'dashboardLinks' => $dashboardLinks,
            'dashboardModules' => $this->dashboardModulesFor($user),
            'assignedRoles' => $user->getRoleNames(),
            'visibilityText' => $this->visibilityText($user->getRoleNames()),
        ]);
    }

    public static function homeRouteFor($user): string
    {
        if ($user->hasRole('Admin') && $user->can('access admin dashboard')) {
            return 'admin.dashboard';
        }

        if (($user->hasRole('PM/Manager') || $user->hasRole('Manager')) && $user->can('access pm dashboard')) {
            return 'pm.dashboard';
        }

        if ($user->hasRole('Coordinator') && $user->can('access coordinator dashboard')) {
            return 'coordinator.dashboard';
        }

        if ($user->hasRole('Subordinate') && $user->can('access subordinate dashboard')) {
            return 'subordinate.dashboard';
        }

        return 'dashboard';
    }

    protected function roleHomeRoute($user): ?string
    {
        $route = self::homeRouteFor($user);

        return $route === 'dashboard' ? null : $route;
    }
    public function admin(): Response
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $userId = auth()->id();
        $data = CacheHelper::rememberDashboard(
            fn () => $this->computeDashboardData(null),
            $userId
        );

        return Inertia::render('Dashboards/Admin', [
            'pageTitle' => 'Workflow Improvement and Tracking Dashboard of Project',
            'pageSubtitle' => 'This page shows graphical presentation of project workflow status including in-progress, completed, due, and overdue records.',
            ...$data,
            'modules' => [
                ['title' => 'Projects', 'description' => 'Open the main project command center.', 'href' => route('projects.index'), 'actionLabel' => 'Open Projects'],
                ['title' => 'Repository Tracker', 'description' => 'Review repository records and timeline updates.', 'href' => route('repository.index'), 'actionLabel' => 'Open Repository'],
                ['title' => 'Users', 'description' => 'Manage user accounts and roles.', 'href' => route('admin.users.index'), 'actionLabel' => 'Manage Users'],
                ['title' => 'Audit Trail', 'description' => 'System activity log for governance.', 'href' => route('admin.audit-logs.index'), 'actionLabel' => 'View Audit Log'],
            ],
        ]);
    }

    public function pm(): Response
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $userId = auth()->id();
        $projectIds = Project::query()
            ->where('created_by', $userId)
            ->pluck('id');
        $data = CacheHelper::rememberDashboard(
            fn () => $this->computeDashboardData($projectIds),
            $userId,
            $projectIds->all()
        );

        return Inertia::render('Dashboards/PM', [
            'pageTitle' => 'Workflow Improvement and Tracking Dashboard of Project',
            'pageSubtitle' => 'This page shows graphical presentation of project workflow status including in-progress, completed, due, and overdue records.',
            ...$data,
            'modules' => [
                ['title' => 'Create and Manage Projects', 'description' => 'Create projects, assign Coordinators, and review ownership.', 'href' => route('projects.index'), 'actionLabel' => 'Open Projects'],
                ['title' => 'Repository Overview', 'description' => 'Manage permanent institutional repository records.', 'href' => route('repository.index'), 'actionLabel' => 'Open Repository'],
            ],
        ]);
    }

    protected function computeDashboardData(?Collection $scopedProjectIds): array
    {
        $now = now()->toDateString();
        $doneStatuses = ['completed', 'archived', 'cancelled'];

        // KPI: In Progress - projects with status in_progress or submitted
        $inProgress = Project::query()
            ->whereIn('status', ['in_progress', 'submitted'])
            ->when($scopedProjectIds, fn ($q) => $q->whereIn('id', $scopedProjectIds))
            ->count();

        // KPI: Completed - projects with status completed
        $completed = Project::query()
            ->where('status', 'completed')
            ->when($scopedProjectIds, fn ($q) => $q->whereIn('id', $scopedProjectIds))
            ->count();

        // KPI: Due - projects not done, deadline is today or future
        $due = Project::query()
            ->whereNotNull('deadline')
            ->where('deadline', '>=', $now)
            ->whereNotIn('status', $doneStatuses)
            ->when($scopedProjectIds, fn ($q) => $q->whereIn('id', $scopedProjectIds))
            ->count();

        // KPI: Overdue - projects not done, deadline is before today
        $overdue = Project::query()
            ->whereNotNull('deadline')
            ->where('deadline', '<', $now)
            ->whereNotIn('status', $doneStatuses)
            ->when($scopedProjectIds, fn ($q) => $q->whereIn('id', $scopedProjectIds))
            ->count();

        // KPI: Total Projects - project-tracking count for active workflow projects
        $totalTasks = Project::query()
            ->whereIn('status', ['in_progress', 'submitted', 'completed'])
            ->when($scopedProjectIds, fn ($q) => $q->whereIn('id', $scopedProjectIds))
            ->count();

        $kpis = [
            ['label' => 'In Progress', 'value' => $inProgress, 'color' => 'blue'],
            ['label' => 'Completed', 'value' => $completed, 'color' => 'green'],
            ['label' => 'Due', 'value' => $due, 'color' => 'amber'],
            ['label' => 'Overdue', 'value' => $overdue, 'color' => 'red'],
            ['label' => 'Total Projects', 'value' => $totalTasks, 'color' => 'purple'],
        ];

        // Project glance: priority-filtered (urgent/high/medium), sorted by overdue -> priority -> deadline
        $priorityOrder = ['urgent' => 0, 'high' => 1, 'medium' => 2];

        $projectQuery = Project::query()
            ->with(['department', 'activePrimaryAssignment.coordinator'])
            ->whereIn('priority', ['urgent', 'high', 'medium']);

        if ($scopedProjectIds !== null) {
            $projectQuery->whereIn('id', $scopedProjectIds);
        }

        $projects = $projectQuery->orderBy('deadline')->orderBy('created_at', 'desc')->get();

        $projectStatuses = $projects->map(function ($p) use ($now, $priorityOrder, $doneStatuses) {
            $isOverdue = $p->deadline && $p->deadline->toDateString() < $now && ! in_array($p->status, $doneStatuses, true);

            return [
                'id' => $p->id,
                'title' => $p->title,
                'status' => $p->status,
                'priority' => $p->priority,
                'coordinator' => $p->activePrimaryAssignment?->coordinator?->name,
                'department' => $p->department?->name,
                'deadline' => $p->deadline?->format('Y-m-d'),
                'is_overdue' => $isOverdue,
                'priority_rank' => $priorityOrder[$p->priority] ?? 99,
            ];
        });

        // Sort: overdue first, then priority rank (urgent > high > medium), then nearest deadline
        $projectStatuses = $projectStatuses->sort(function ($a, $b) {
            // Overdue first
            if ($a['is_overdue'] !== $b['is_overdue']) {
                return $b['is_overdue'] <=> $a['is_overdue'];
            }
            // Priority rank
            if ($a['priority_rank'] !== $b['priority_rank']) {
                return $a['priority_rank'] <=> $b['priority_rank'];
            }
            // Nearest deadline
            return ($a['deadline'] ?? '9999-12-31') <=> ($b['deadline'] ?? '9999-12-31');
        })->values()->all();

        // Status donut: completed vs in-progress project counts
        $inProgressProjects = Project::query()
            ->whereIn('status', ['in_progress', 'submitted', 'active'])
            ->when($scopedProjectIds, fn ($q) => $q->whereIn('id', $scopedProjectIds))
            ->count();

        $statusData = [
            ['name' => 'Completed', 'value' => $completed, 'color' => '#22c55e'],
            ['name' => 'In Progress', 'value' => $inProgressProjects, 'color' => '#f59e0b'],
        ];

        // Completion over last 3 months
        $months = [];
        for ($i = 2; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $months[] = [
                'month' => $month->format('M Y'),
                'year' => $month->year,
                'month_num' => $month->month,
            ];
        }

        $completionByMonth = [];
        foreach ($months as $m) {
            $count = Subtask::query()
                ->whereIn('status', ['completed', 'approved'])
                ->whereYear('updated_at', $m['year'])
                ->whereMonth('updated_at', $m['month_num']);
            if ($scopedProjectIds !== null) {
                $count->whereIn('project_id', $scopedProjectIds);
            }
            $completionByMonth[] = [
                'month' => $m['month'],
                'completed' => $count->count(),
            ];
        }

        return [
            'kpis' => $kpis,
            'projectStatuses' => $projectStatuses,
            'statusData' => $statusData,
            'completionByMonth' => $completionByMonth,
        ];
    }

    protected function dashboardLinks(): array
    {
        return [
            ['permission' => 'access admin dashboard', 'route' => 'admin.dashboard', 'label' => 'Admin Dashboard'],
            ['permission' => 'access pm dashboard', 'route' => 'pm.dashboard', 'label' => 'PM Dashboard'],
            ['permission' => 'access coordinator dashboard', 'route' => 'coordinator.dashboard', 'label' => 'Coordinator Dashboard'],
            ['permission' => 'access subordinate dashboard', 'route' => 'subordinate.dashboard', 'label' => 'Subordinate Dashboard'],
        ];
    }

    protected function dashboardModulesFor($user): array
    {
        if ($user->hasRole('Subordinate')) {
            return [
                ['title' => 'My Work Items', 'description' => 'Subordinate workspace for active assigned work.', 'href' => route('my-work-items.index'), 'actionLabel' => 'My Work Items'],
                ['title' => 'Update Progress', 'description' => 'Open assigned work items and submit progress updates.', 'href' => route('my-work-items.index'), 'actionLabel' => 'Update Progress'],
                ['title' => 'Deadline View', 'description' => 'Review work item deadlines inside assigned work pages.', 'href' => route('my-work-items.index'), 'actionLabel' => 'View Deadlines'],
            ];
        }

        return array_values(array_filter([
            $user->can('view projects') ? ['title' => 'Projects', 'description' => 'Create projects, assign coordinators, and track ownership.', 'href' => route('projects.index'), 'actionLabel' => 'Open Projects'] : null,
            $user->can('view repository') ? ['title' => 'Repository Tracker', 'description' => 'Permanent institutional repository records and timeline tracking.', 'href' => route('repository.index'), 'actionLabel' => 'Open Repository'] : null,
            $user->hasRole('Coordinator') ? ['title' => 'My Assigned Projects', 'description' => 'Coordinator workspace for assigned project delivery.', 'href' => route('projects.mine'), 'actionLabel' => 'My Assigned Projects'] : null,
            $user->can('view assigned subtasks') ? ['title' => 'My Work Items', 'description' => 'Subordinate workspace for active assigned work.', 'href' => route('my-work-items.index'), 'actionLabel' => 'My Work Items'] : null,
        ]));
    }

    protected function visibilityText(Collection $roles): string
    {
        if ($roles->isEmpty()) {
            return 'No role dashboards assigned';
        }

        return 'Assigned roles: '.$roles->implode(', ');
    }

    public function coordinator(): Response
    {
        return $this->roleDashboard('Dashboards/RoleDashboard', [
            'title' => 'Coordinator Dashboard',
            'description' => 'Assigned-project workspace for task management and subordinate coordination.',
            'modules' => [
                ['title' => 'Assigned Projects', 'description' => 'Open only the projects currently assigned to you.', 'href' => route('projects.mine'), 'actionLabel' => 'My Assigned Projects'],
                ['title' => 'Tasks', 'description' => 'Create and manage tasks from assigned projects.', 'href' => route('projects.mine'), 'actionLabel' => 'View Tasks'],
                ['title' => 'Assigned Work Items', 'description' => 'Create work items and assign them to Subordinates.', 'href' => route('projects.mine'), 'actionLabel' => 'View Work Items'],
                ['title' => 'Subordinate Progress', 'description' => 'Review progress on work items under your assigned projects.'],
            ],
            'primaryAction' => ['label' => 'My Assigned Projects', 'href' => route('projects.mine')],
        ]);
    }

    public function subordinate(): Response
    {
        return $this->roleDashboard('Dashboards/RoleDashboard', [
            'title' => 'Subordinate Dashboard',
            'description' => 'Assigned-work workspace for active work item updates and deadline review.',
            'modules' => [
                ['title' => 'My Work Items', 'description' => 'Open the work items currently assigned to you.', 'href' => route('my-work-items.index'), 'actionLabel' => 'My Work Items'],
                ['title' => 'Update Progress', 'description' => 'Open assigned work items and submit progress updates.', 'href' => route('my-work-items.index'), 'actionLabel' => 'Update Progress'],
                ['title' => 'Deadline View', 'description' => 'Review work item deadlines inside assigned work pages.', 'href' => route('my-work-items.index'), 'actionLabel' => 'View Deadlines'],
            ],
            'primaryAction' => ['label' => 'My Work Items', 'href' => route('my-work-items.index')],
        ]);
    }

    protected function roleDashboard(string $component, array $props): Response
    {
        return Inertia::render($component, $props + [
            'pageTitle' => $props['title'],
            'pageSubtitle' => $props['description'],
        ]);
    }
}

