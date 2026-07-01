<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProjectAssignmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_cannot_access_projects(): void
    {
        $this->get('/projects')->assertRedirect('/login');
    }

    public function test_user_without_project_permission_cannot_access_projects(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/projects')->assertForbidden();
    }

    public function test_admin_can_access_project_index(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get('/projects')->assertOk()->assertSee('Projects');
    }

    public function test_pm_can_access_project_index(): void
    {
        $pm = $this->makePm();

        $this->actingAs($pm)->get('/projects')->assertOk()->assertSee('Projects');
    }

    public function test_coordinator_cannot_access_all_project_index(): void
    {
        $coordinator = $this->makeCoordinator();

        $this->actingAs($coordinator)->get('/projects')->assertForbidden();
    }

    public function test_admin_sees_create_project_and_assign_coordinator_buttons_on_projects(): void
    {
        $admin = $this->makeAdmin();
        Project::factory()->create();

        $this->actingAs($admin)->get('/projects')
            ->assertOk()
            ->assertSee('Create Project')
            ->assertSee('Assign Coordinator');
    }

    public function test_pm_sees_create_project_button_on_projects(): void
    {
        $pm = $this->makePm();
        Project::factory()->create();

        $this->actingAs($pm)->get('/projects')
            ->assertOk()
            ->assertSee('Create Project')
            ->assertSee('Assign Coordinator');
    }

    public function test_coordinator_does_not_see_create_project_button(): void
    {
        $coordinator = $this->makeCoordinator();

        $this->actingAs($coordinator)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Create Project')
            ->assertSee('My Assigned Projects');
    }

    public function test_admin_can_access_projects_create(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get('/projects/create')->assertOk()->assertSee('Create Project');
    }

    public function test_pm_can_access_projects_create(): void
    {
        $pm = $this->makePm();

        $this->actingAs($pm)->get('/projects/create')->assertOk()->assertSee('Create Project');
    }

    public function test_coordinator_cannot_access_projects_create(): void
    {
        $coordinator = $this->makeCoordinator();

        $this->actingAs($coordinator)->get('/projects/create')->assertForbidden();
    }

    public function test_admin_can_create_project(): void
    {
        $admin = $this->makeAdmin();
        $department = Department::factory()->create();

        $this->actingAs($admin)->post('/projects', [
            'title' => 'Admin Project',
            'description' => 'Admin created this project.',
            'department_id' => $department->id,
            'status' => 'planned',
            'priority' => 'high',
            'start_date' => '2026-06-25',
            'deadline' => '2026-06-30',
        ])->assertRedirect();

        $this->assertDatabaseHas('projects', ['title' => 'Admin Project', 'created_by' => $admin->id]);
    }

    public function test_pm_can_create_project(): void
    {
        $pm = $this->makePm();

        $this->actingAs($pm)->post('/projects', [
            'title' => 'PM Project',
            'status' => 'in_progress',
        ])->assertRedirect();

        $this->assertDatabaseHas('projects', ['title' => 'PM Project', 'created_by' => $pm->id]);
    }

    public function test_project_validation_requires_title_and_valid_status(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post('/projects', [
            'title' => '',
            'status' => 'invalid',
        ])->assertSessionHasErrors(['title', 'status']);
    }

    public function test_admin_can_update_project(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create(['title' => 'Original Project']);

        $this->actingAs($admin)->patch('/projects/'.$project->id, [
            'title' => 'Updated Project',
            'status' => 'in_progress',
            'description' => 'Updated description',
        ])->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'title' => 'Updated Project', 'status' => 'in_progress']);
    }

    public function test_pm_can_update_project(): void
    {
        $pm = $this->makePm();
        $project = Project::factory()->create(['title' => 'PM Original']);

        $this->actingAs($pm)->patch('/projects/'.$project->id, [
            'title' => 'PM Updated',
            'status' => 'submitted',
        ])->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'title' => 'PM Updated', 'status' => 'submitted']);
    }

    public function test_admin_can_access_assign_coordinator_page(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create();

        $this->actingAs($admin)->get('/projects/'.$project->id.'/assign-coordinator')->assertOk()->assertSee('Assign Coordinator');
    }

    public function test_pm_can_access_assign_coordinator_page(): void
    {
        $pm = $this->makePm();
        $project = Project::factory()->create();

        $this->actingAs($pm)->get('/projects/'.$project->id.'/assign-coordinator')->assertOk()->assertSee('Assign Coordinator');
    }

    public function test_coordinator_cannot_access_assign_coordinator_page(): void
    {
        $coordinator = $this->makeCoordinator();
        $project = Project::factory()->create();

        $this->actingAs($coordinator)->get('/projects/'.$project->id.'/assign-coordinator')->assertForbidden();
    }

    public function test_admin_can_assign_coordinator_to_project(): void
    {
        $admin = $this->makeAdmin();
        $coordinator = $this->makeCoordinator();
        $project = Project::factory()->create();

        $this->actingAs($admin)->post('/projects/'.$project->id.'/assign-coordinator', [
            'coordinator_id' => $coordinator->id,
        ])->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('project_assignments', [
            'project_id' => $project->id,
            'coordinator_id' => $coordinator->id,
            'assigned_by' => $admin->id,
            'assignment_role' => 'primary',
        ]);
    }

    public function test_pm_can_assign_coordinator_to_project(): void
    {
        $pm = $this->makePm();
        $coordinator = $this->makeCoordinator();
        $project = Project::factory()->create();

        $this->actingAs($pm)->post('/projects/'.$project->id.'/assign-coordinator', [
            'coordinator_id' => $coordinator->id,
        ])->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('project_assignments', [
            'project_id' => $project->id,
            'coordinator_id' => $coordinator->id,
            'assigned_by' => $pm->id,
        ]);
    }

    public function test_assignment_creates_project_assignments_row(): void
    {
        $admin = $this->makeAdmin();
        $coordinator = $this->makeCoordinator();
        $project = Project::factory()->create();

        $this->actingAs($admin)->post('/projects/'.$project->id.'/assign-coordinator', [
            'coordinator_id' => $coordinator->id,
        ]);

        $this->assertSame(1, ProjectAssignment::query()->where('project_id', $project->id)->count());
    }

    public function test_reassigning_coordinator_revokes_old_assignment_instead_of_deleting_it(): void
    {
        $admin = $this->makeAdmin();
        $firstCoordinator = $this->makeCoordinator('first-coordinator@example.com');
        $secondCoordinator = $this->makeCoordinator('second-coordinator@example.com');
        $project = Project::factory()->create();

        $this->actingAs($admin)->post('/projects/'.$project->id.'/assign-coordinator', [
            'coordinator_id' => $firstCoordinator->id,
        ]);

        $this->actingAs($admin)->post('/projects/'.$project->id.'/assign-coordinator', [
            'coordinator_id' => $secondCoordinator->id,
        ]);

        $oldAssignment = ProjectAssignment::query()
            ->where('project_id', $project->id)
            ->where('coordinator_id', $firstCoordinator->id)
            ->first();

        $newAssignment = ProjectAssignment::query()
            ->where('project_id', $project->id)
            ->where('coordinator_id', $secondCoordinator->id)
            ->whereNull('revoked_at')
            ->first();

        $this->assertNotNull($oldAssignment);
        $this->assertNotNull($oldAssignment->revoked_at);
        $this->assertNotNull($newAssignment);
        $this->assertSame(2, ProjectAssignment::query()->where('project_id', $project->id)->count());
    }

    public function test_same_coordinator_assignment_does_not_create_duplicate_active_assignment(): void
    {
        $pm = $this->makePm();
        $coordinator = $this->makeCoordinator();
        $project = Project::factory()->create();

        $this->actingAs($pm)->post('/projects/'.$project->id.'/assign-coordinator', [
            'coordinator_id' => $coordinator->id,
        ]);

        $this->actingAs($pm)->post('/projects/'.$project->id.'/assign-coordinator', [
            'coordinator_id' => $coordinator->id,
        ])->assertRedirect(route('projects.assign-coordinator.edit', $project));

        $this->assertSame(1, ProjectAssignment::query()->where('project_id', $project->id)->count());
        $this->assertSame(1, ProjectAssignment::query()->where('project_id', $project->id)->whereNull('revoked_at')->count());
    }

    public function test_assigned_coordinator_can_see_project_on_my_projects(): void
    {
        $admin = $this->makeAdmin();
        $coordinator = $this->makeCoordinator();
        $project = Project::factory()->create(['title' => 'Assigned Coordination Project']);

        $this->actingAs($admin)->post('/projects/'.$project->id.'/assign-coordinator', [
            'coordinator_id' => $coordinator->id,
        ]);

        $this->actingAs($coordinator)->get('/my-projects')
            ->assertOk()
            ->assertSee('My Assigned Projects')
            ->assertSee('Assigned Coordination Project');
    }

    public function test_unassigned_coordinator_cannot_see_another_coordinators_project_on_my_projects(): void
    {
        $admin = $this->makeAdmin();
        $assignedCoordinator = $this->makeCoordinator('assigned-coordinator@example.com');
        $unassignedCoordinator = $this->makeCoordinator('unassigned-coordinator@example.com');
        $project = Project::factory()->create(['title' => 'Restricted Coordination Project']);

        $this->actingAs($admin)->post('/projects/'.$project->id.'/assign-coordinator', [
            'coordinator_id' => $assignedCoordinator->id,
        ]);

        $this->actingAs($unassignedCoordinator)->get('/my-projects')
            ->assertOk()
            ->assertDontSee('Restricted Coordination Project');
    }

    public function test_assigned_coordinator_can_view_assigned_project_show_page(): void
    {
        $admin = $this->makeAdmin();
        $coordinator = $this->makeCoordinator();
        $project = Project::factory()->create(['title' => 'Coordinator Visible Project']);

        $this->actingAs($admin)->post('/projects/'.$project->id.'/assign-coordinator', [
            'coordinator_id' => $coordinator->id,
        ]);

        $this->actingAs($coordinator)->get('/projects/'.$project->id)
            ->assertOk()
            ->assertSee('Coordinator Visible Project');
    }

    public function test_unassigned_coordinator_cannot_view_project_show_page(): void
    {
        $coordinator = $this->makeCoordinator();
        $project = Project::factory()->create();

        $this->actingAs($coordinator)->get('/projects/'.$project->id)->assertForbidden();
    }

    public function test_subordinate_cannot_access_project_index(): void
    {
        $subordinate = $this->makeSubordinate();

        $this->actingAs($subordinate)->get('/projects')->assertForbidden();
    }

    public function test_navigation_shows_projects_link_only_to_permitted_admin_and_pm(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('navigation.5.label', 'Projects')
                ->where('navigation.5.href', route('projects.index'))
            );

        $pm = $this->makePm();
        $this->actingAs($pm)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('navigation.3.label', 'Projects')
                ->where('navigation.3.href', route('projects.index'))
            );

        $subordinate = $this->makeSubordinate();
        $this->actingAs($subordinate)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('navigation.2.label', 'My Work Items')
            );
    }

    public function test_navigation_shows_my_assigned_projects_link_only_to_coordinator(): void
    {
        $coordinator = $this->makeCoordinator();
        $this->actingAs($coordinator)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('navigation.2.label', 'My Assigned Projects')
                ->where('navigation.2.href', route('projects.mine'))
            );

        $admin = $this->makeAdmin('nav-admin@example.com');
        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('navigation.5.href', route('projects.index'))
            );

        $pm = $this->makePm('nav-pm@example.com');
        $this->actingAs($pm)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('navigation.3.href', route('projects.index'))
            );
    }

    public function test_project_show_page_displays_assignment_history(): void
    {
        $admin = $this->makeAdmin();
        $firstCoordinator = $this->makeCoordinator('history-first@example.com');
        $secondCoordinator = $this->makeCoordinator('history-second@example.com');
        $project = Project::factory()->create(['title' => 'Assignment History Project']);

        $this->actingAs($admin)->post('/projects/'.$project->id.'/assign-coordinator', [
            'coordinator_id' => $firstCoordinator->id,
        ]);

        $this->actingAs($admin)->post('/projects/'.$project->id.'/assign-coordinator', [
            'coordinator_id' => $secondCoordinator->id,
        ]);

        $this->actingAs($admin)->get('/projects/'.$project->id)
            ->assertOk()
            ->assertSee('Assignment History')
            ->assertSee($firstCoordinator->name)
            ->assertSee($secondCoordinator->name);
    }

    public function test_project_show_page_displays_phase_seven_task_actions(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create();

        $this->actingAs($admin)->get('/projects/'.$project->id)
            ->assertOk()
            ->assertSee('View Tasks')
            ->assertSee('Create Task');
    }

    public function test_assigned_coordinator_project_show_exposes_task_workflow_without_assign_coordinator(): void
    {
        $admin = $this->makeAdmin();
        $coordinator = $this->makeCoordinator();
        $project = Project::factory()->create(['title' => 'Coordinator Workflow Project']);
        $this->actingAs($admin)->post('/projects/'.$project->id.'/assign-coordinator', [
            'coordinator_id' => $coordinator->id,
        ]);

        $this->actingAs($coordinator)->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Show')
                ->where('canViewTasks', true)
                ->where('canCreateTask', true)
                ->where('canAssignCoordinator', false)
                ->has('actions', 2)
                ->where('actions.0', 'View Tasks')
                ->where('actions.1', 'Create Task'));
    }

    public function test_admin_project_show_still_exposes_assign_coordinator(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create();

        $this->actingAs($admin)->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Show')
                ->where('canAssignCoordinator', true)
                ->where('actions.2', 'Assign Coordinator'));
    }
    protected function makeAdmin(?string $email = null): User
    {
        $user = User::factory()->create(['email' => $email ?? fake()->unique()->safeEmail()]);
        $user->syncRoles(['Admin']);

        return $user;
    }

    protected function makePm(?string $email = null): User
    {
        $user = User::factory()->create(['email' => $email ?? fake()->unique()->safeEmail()]);
        $user->syncRoles(['PM/Manager']);

        return $user;
    }

    protected function makeCoordinator(?string $email = null): User
    {
        $user = User::factory()->create(['email' => $email ?? fake()->unique()->safeEmail()]);
        $user->syncRoles(['Coordinator']);

        return $user;
    }

    protected function makeSubordinate(?string $email = null): User
    {
        $user = User::factory()->create(['email' => $email ?? fake()->unique()->safeEmail()]);
        $user->syncRoles(['Subordinate']);

        return $user;
    }
}






