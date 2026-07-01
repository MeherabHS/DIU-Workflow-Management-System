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
            'navigation' => $user ? $this->navigationFor($roles->all()) : [],
            'ui' => [
                'primaryButtonClass' => 'bg-gray-900 text-white',
            ],
            'notifications' => [
                'unreadCount' => $user ? $user->unreadWorkflowNotifications()->count() : 0,
            ],
        ];
    }

    protected function navigationFor(array $roles): array
    {
        $lowerRoles = array_map(fn (string $role): string => strtolower($role), $roles);
        $hasRole = fn (string $role): bool => in_array(strtolower($role), $lowerRoles, true);

        if ($hasRole('Admin')) {
            return [
                ['label' => 'Dashboard', 'href' => route('dashboard')],
                ['label' => 'Admin Dashboard', 'href' => route('admin.dashboard')],
                ['label' => 'Users', 'href' => route('admin.users.index')],
                ['label' => 'Audit Trail', 'href' => route('admin.audit-logs.index')],
                ['label' => 'Reports', 'href' => route('reports.index')],
                ['label' => 'Projects', 'href' => route('projects.index')],
                ['label' => 'Repository Tracker', 'href' => route('repository.index')],
            ];
        }

        if ($hasRole('PM/Manager') || $hasRole('Manager')) {
            return [
                ['label' => 'Dashboard', 'href' => route('dashboard')],
                ['label' => 'PM Dashboard', 'href' => route('pm.dashboard')],
                ['label' => 'Reports', 'href' => route('reports.index')],
                ['label' => 'Projects', 'href' => route('projects.index')],
                ['label' => 'Repository Tracker', 'href' => route('repository.index')],
            ];
        }

        if ($hasRole('Coordinator')) {
            return [
                ['label' => 'Dashboard', 'href' => route('dashboard')],
                ['label' => 'Coordinator Dashboard', 'href' => route('coordinator.dashboard')],
                ['label' => 'My Assigned Projects', 'href' => route('projects.mine')],
                ['label' => 'Repository Tracker', 'href' => route('repository.index')],
            ];
        }

        if ($hasRole('Subordinate')) {
            return [
                ['label' => 'Dashboard', 'href' => route('dashboard')],
                ['label' => 'Subordinate Dashboard', 'href' => route('subordinate.dashboard')],
                ['label' => 'My Work Items', 'href' => route('my-work-items.index')],
            ];
        }

        return [
            ['label' => 'Dashboard', 'href' => route('dashboard')],
        ];
    }
}
