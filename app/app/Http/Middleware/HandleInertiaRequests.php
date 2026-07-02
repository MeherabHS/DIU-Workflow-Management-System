<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $roles = $user && method_exists($user, 'getRoleNames') ? $user->getRoleNames()->values() : collect();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_active' => $user->is_active ?? true,
                    'profile_photo_url' => $user->profile_photo_url ?? '',
                    'initials' => $user->initials ?? '',
                    'roles' => $roles,
                    'permissions' => method_exists($user, 'getAllPermissions') ? $user->getAllPermissions()->pluck('name')->values() : [],
                ] : null,
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
            'loginWorkSummary' => fn () => $request->session()->pull('login_work_summary'),
            'homeUrl' => $user ? $this->homeUrlFor($user) : route('login'),
            'navigation' => $user ? $this->navigationFor($user) : [],
            'ui' => [
                'primaryButtonClass' => 'bg-gray-900 text-white',
            ],
            'notifications' => [
                'unreadCount' => $user ? $user->unreadWorkflowNotifications()->count() : 0,
            ],
        ];
    }

    protected function navigationFor($user): array
    {
        $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->all() : [];
        $lowerRoles = array_map(fn (string $role): string => strtolower($role), $roles);
        $hasRole = fn (string $role): bool => in_array(strtolower($role), $lowerRoles, true);
        $can = fn (string $permission): bool => method_exists($user, 'can') && $user->can($permission);
        $links = [];

        $add = function (bool $allowed, string $label, string $routeName) use (&$links): void {
            if ($allowed) {
                $links[] = ['label' => $label, 'href' => route($routeName)];
            }
        };

        if ($hasRole('Admin')) {
            $add($can('access admin dashboard'), 'Admin Dashboard', 'admin.dashboard');
            $add($can('manage users'), 'Users', 'admin.users.index');
            $add($can('view audit trail'), 'Audit Trail', 'admin.audit-logs.index');
            $add($can('view reports'), 'Reports', 'reports.index');
            $add($can('view projects'), 'Projects', 'projects.index');
            $add($can('view repository'), 'Repository Tracker', 'repository.index');

            return $links;
        }

        if ($hasRole('PM/Manager') || $hasRole('Manager')) {
            $add($can('access pm dashboard'), 'PM Dashboard', 'pm.dashboard');
            $add($can('view reports'), 'Reports', 'reports.index');
            $add($can('view projects'), 'Projects', 'projects.index');
            $add($can('view repository'), 'Repository Tracker', 'repository.index');

            return $links;
        }

        if ($hasRole('Coordinator')) {
            $add($can('access coordinator dashboard'), 'Coordinator Dashboard', 'coordinator.dashboard');
            $add($can('view assigned projects'), 'My Assigned Projects', 'projects.mine');

            return $links;
        }

        if ($hasRole('Subordinate')) {
            $add($can('view assigned subtasks'), 'My Work Items', 'my-work-items.index');
            $add($can('view own profile'), 'Profile', 'profile.edit');

            return $links;
        }

        return [];
    }

    protected function homeUrlFor($user): string
    {
        if ($user->hasRole('Admin') && $user->can('access admin dashboard')) {
            return route('admin.dashboard');
        }

        if (($user->hasRole('PM/Manager') || $user->hasRole('Manager')) && $user->can('access pm dashboard')) {
            return route('pm.dashboard');
        }

        if ($user->hasRole('Coordinator') && $user->can('access coordinator dashboard')) {
            return route('coordinator.dashboard');
        }

        if ($user->hasRole('Subordinate') && $user->can('access subordinate dashboard')) {
            return route('subordinate.dashboard');
        }

        return route('dashboard');
    }
}

