<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_dashboard_redirects_subordinate_to_role_dashboard(): void
    {
        $user = User::factory()->create(['name' => 'General User']);
        $user->syncRoles(['Subordinate']);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('subordinate.dashboard'));
    }

    public function test_admin_sees_only_allowed_sidebar_links_according_to_permissions(): void
    {
        $user = User::factory()->create();
        $user->syncRoles(['Admin']);

        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('navigation.0.label', 'Admin Dashboard')
                ->where('navigation.1.label', 'Users')
                ->where('navigation.2.label', 'Audit Trail')
                ->where('navigation.3.label', 'Reports')
                ->where('navigation.4.label', 'Projects')
                ->where('navigation.5.label', 'Repository Tracker')
                ->has('navigation', 6));
    }

    public function test_pm_manager_sees_pm_dashboard_link(): void
    {
        $user = User::factory()->create();
        $user->syncRoles(['PM/Manager']);

        $this->actingAs($user)
            ->get('/pm/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('navigation.0.label', 'PM Dashboard')
                ->where('navigation.1.label', 'Reports')
                ->where('navigation.2.label', 'Projects')
                ->where('navigation.3.label', 'Repository Tracker')
                ->has('navigation', 4));
    }

    public function test_coordinator_sees_coordinator_dashboard_link(): void
    {
        $user = User::factory()->create();
        $user->syncRoles(['Coordinator']);

        $this->actingAs($user)
            ->get('/coordinator/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('navigation.0.label', 'Coordinator Dashboard')
                ->where('navigation.1.label', 'My Assigned Projects')
                ->has('navigation', 2));
    }

    public function test_subordinate_sees_subordinate_dashboard_link(): void
    {
        $user = User::factory()->create();
        $user->syncRoles(['Subordinate']);

        $this->actingAs($user)
            ->get('/subordinate/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('navigation.0.label', 'My Work Items')
                ->where('navigation.1.label', 'Profile')
                ->has('navigation', 2));
    }

    public function test_pm_manager_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->create();
        $user->syncRoles(['PM/Manager']);

        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_coordinator_cannot_access_pm_dashboard(): void
    {
        $user = User::factory()->create();
        $user->syncRoles(['Coordinator']);

        $this->actingAs($user)
            ->get('/pm/dashboard')
            ->assertForbidden();
    }

    public function test_subordinate_cannot_access_coordinator_dashboard(): void
    {
        $user = User::factory()->create();
        $user->syncRoles(['Subordinate']);

        $this->actingAs($user)
            ->get('/coordinator/dashboard')
            ->assertForbidden();
    }

    public function test_each_role_dashboard_renders_phase_four_placeholder_content(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['Admin']);
        $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->assertSee('Workflow Improvement and Tracking Dashboard of Project')->assertSee('In Progress');
        auth()->logout();

        $pm = User::factory()->create();
        $pm->syncRoles(['PM/Manager']);
        $this->actingAs($pm)->get('/pm/dashboard')->assertOk()->assertSee('Workflow Improvement and Tracking Dashboard of Project')->assertSee('Create and Manage Projects');
        auth()->logout();

        $coordinator = User::factory()->create();
        $coordinator->syncRoles(['Coordinator']);
        $this->actingAs($coordinator)->get('/coordinator/dashboard')->assertOk()->assertSee('Assigned Projects')->assertSee('Assigned Work Items');
        auth()->logout();

        $subordinate = User::factory()->create();
        $subordinate->syncRoles(['Subordinate']);
        $this->actingAs($subordinate)->get('/subordinate/dashboard')->assertOk()->assertSee('My Work Items')->assertSee('Update Progress');
    }

    public function test_admin_dashboard_status_overview_and_total_tasks_payload_use_current_project_workflow_count(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['Admin']);

        Project::factory()->create(['status' => 'completed']);
        $inProgress = Project::factory()->create(['status' => 'in_progress']);
        Project::factory()->create(['status' => 'submitted']);
        Project::factory()->create(['status' => 'active']);
        Project::factory()->create(['status' => 'planned']);
        $task = Task::factory()->for($inProgress)->create();
        Subtask::factory()->for($task)->create(['project_id' => $inProgress->id]);

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboards/Admin')
                ->where('statusData.0.name', 'Completed')
                ->where('statusData.0.value', 1)
                ->where('statusData.1.name', 'In Progress')
                ->where('statusData.1.value', 3)
                ->where('kpis.4.label', 'Total Projects')
                ->where('kpis.4.value', 3)
                ->where('statusData', fn ($statusData) => ! collect($statusData)->contains('name', 'Active')));
    }

    public function test_pm_dashboard_total_tasks_counts_only_managed_workflow_projects_and_ignores_tasks_and_work_items(): void
    {
        $pm = User::factory()->create();
        $pm->syncRoles(['PM/Manager']);

        $managedOne = Project::factory()->create(['created_by' => $pm->id, 'status' => 'in_progress']);
        Project::factory()->create(['created_by' => $pm->id, 'status' => 'completed']);
        Project::factory()->create(['created_by' => $pm->id, 'status' => 'planned']);
        Project::factory()->create(['status' => 'submitted']);
        $task = Task::factory()->for($managedOne)->create();
        Subtask::factory()->for($task)->create(['project_id' => $managedOne->id]);

        $this->actingAs($pm)
            ->get('/pm/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboards/PM')
                ->where('kpis.4.label', 'Total Projects')
                ->where('kpis.4.value', 2));
    }

    public function test_dashboard_redirects_role_aware_for_each_role(): void
    {
        $admin = User::factory()->create(['email' => 'redirect-admin@example.com']);
        $admin->syncRoles(['Admin']);
        $this->actingAs($admin)->get('/dashboard')->assertRedirect(route('admin.dashboard'));
        auth()->logout();

        $pm = User::factory()->create(['email' => 'redirect-pm@example.com']);
        $pm->syncRoles(['PM/Manager']);
        $this->actingAs($pm)->get('/dashboard')->assertRedirect(route('pm.dashboard'));
        auth()->logout();

        $coordinator = User::factory()->create(['email' => 'redirect-coordinator@example.com']);
        $coordinator->syncRoles(['Coordinator']);
        $this->actingAs($coordinator)->get('/dashboard')->assertRedirect(route('coordinator.dashboard'));
        auth()->logout();

        $subordinate = User::factory()->create(['email' => 'redirect-subordinate@example.com']);
        $subordinate->syncRoles(['Subordinate']);
        $this->actingAs($subordinate)->get('/dashboard')->assertRedirect(route('subordinate.dashboard'));
    }
    public function test_admin_dashboard_priority_project_glance_is_limited_after_existing_sort(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['Admin']);

        Project::factory()->create([
            'title' => 'Oldest hidden project',
            'priority' => 'medium',
            'status' => 'in_progress',
            'deadline' => now()->addDays(30)->toDateString(),
            'created_at' => now()->subDays(30),
        ]);

        for ($i = 1; $i <= 11; $i++) {
            Project::factory()->create([
                'title' => sprintf('Priority project %02d', $i),
                'priority' => $i === 11 ? 'urgent' : 'high',
                'status' => 'in_progress',
                'deadline' => now()->addDays($i)->toDateString(),
                'created_at' => now()->subDays($i),
            ]);
        }

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('projectStatuses', 10)
                ->where('projectStatuses.0.title', 'Priority project 11'));
    }
}
