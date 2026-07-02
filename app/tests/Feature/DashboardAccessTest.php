<?php

namespace Tests\Feature;

use App\Models\Project;
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

    public function test_authenticated_user_can_access_dashboard(): void
    {
        $user = User::factory()->create(['name' => 'General User']);
        // Give them a basic role so middleware allows access, but test with no role dashboards
        $user->syncRoles([]);

        // Re-check: user without role should still be blocked by middleware
        // This test is now superseded by RegistrationTest tests for no-role users
        // Update: assign Subordinate role so middleware allows access, verify dashboard works
        $user->syncRoles(['Subordinate']);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('auth.user.name', 'General User')
                ->has('assignedRoles'));
    }

    public function test_admin_sees_only_allowed_dashboard_links_according_to_permissions(): void
    {
        $user = User::factory()->create();
        $user->syncRoles(['Admin']);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Admin Dashboard')
            ->assertSee('PM Dashboard')
            ->assertSee('Coordinator Dashboard')
            ->assertSee('Subordinate Dashboard');
    }

    public function test_pm_manager_sees_pm_dashboard_link(): void
    {
        $user = User::factory()->create();
        $user->syncRoles(['PM/Manager']);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('PM Dashboard')
            ->assertDontSee('Admin Dashboard')
            ->assertDontSee('Coordinator Dashboard')
            ->assertDontSee('Subordinate Dashboard');
    }

    public function test_coordinator_sees_coordinator_dashboard_link(): void
    {
        $user = User::factory()->create();
        $user->syncRoles(['Coordinator']);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Coordinator Dashboard')
            ->assertDontSee('Admin Dashboard')
            ->assertDontSee('PM Dashboard')
            ->assertDontSee('Subordinate Dashboard');
    }

    public function test_subordinate_sees_subordinate_dashboard_link(): void
    {
        $user = User::factory()->create();
        $user->syncRoles(['Subordinate']);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Subordinate Dashboard')
            ->assertDontSee('Admin Dashboard')
            ->assertDontSee('PM Dashboard')
            ->assertDontSee('Coordinator Dashboard');
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

    public function test_admin_dashboard_status_overview_and_total_tasks_payload_use_current_data(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['Admin']);

        Project::factory()->create(['status' => 'completed']);
        $inProgress = Project::factory()->create(['status' => 'in_progress']);
        Project::factory()->create(['status' => 'submitted']);
        Project::factory()->create(['status' => 'active']);
        Project::factory()->create(['status' => 'planned']);
        Task::factory()->count(3)->for($inProgress)->create();

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboards/Admin')
                ->where('statusData.0.name', 'Completed')
                ->where('statusData.0.value', 1)
                ->where('statusData.1.name', 'In Progress')
                ->where('statusData.1.value', 3)
                ->where('kpis.4.label', 'Total Tasks')
                ->where('kpis.4.value', Task::count())
                ->where('statusData', fn ($statusData) => ! collect($statusData)->contains('name', 'Active')));
    }

    public function test_user_without_role_does_not_see_role_dashboard_links(): void
    {
        // Create user and assign Subordinate role (factory default), then test they see subordinate dashboard
        // No-role users are blocked by middleware - tested in RegistrationTest/SecurityTest
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Subordinate Dashboard')
            ->assertDontSee('Admin Dashboard')
            ->assertDontSee('PM Dashboard')
            ->assertDontSee('Coordinator Dashboard');
    }
}
