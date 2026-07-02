<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\RepositoryEntry;
use App\Models\Subtask;
use App\Models\SubtaskAssignment;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UiVisibilityAndLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_navigation_data_exposes_full_labels_for_each_role(): void
    {
        $this->actingAs($this->makeAdmin())->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Admin Dashboard')
            ->assertSee('Projects')
            ->assertSee('Repository Tracker');

        $this->actingAs($this->makePm())->get('/pm/dashboard')
            ->assertOk()
            ->assertSee('PM Dashboard')
            ->assertSee('Projects')
            ->assertSee('Repository Tracker');

        $this->actingAs($this->makeCoordinator())->get('/coordinator/dashboard')
            ->assertOk()
            ->assertSee('Coordinator Dashboard')
            ->assertSee('My Assigned Projects')
            ->assertDontSee('Repository Tracker');

        $this->actingAs($this->makeSubordinate())->get('/subordinate/dashboard')
            ->assertOk()
            ->assertSee('My Work Items')
            ->assertSee('Profile');
    }

    public function test_rendered_html_does_not_contain_broken_sidebar_or_hidden_action_patterns(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $subtask = Subtask::factory()->for($project)->for($task)->create();
        RepositoryEntry::factory()->create();

        $html = $this->actingAs($admin)->get('/projects')->getContent()
            .$this->actingAs($admin)->get('/repository')->getContent()
            .$this->actingAs($admin)->get('/projects/'.$project->id)->getContent()
            .$this->actingAs($admin)->get('/tasks/'.$task->id)->getContent()
            .$this->actingAs($admin)->get('/subtasks/'.$subtask->id)->getContent();

        foreach (['>D<', '>AD<', '>PD<', '>CD<', '>SD<', '>MA<', '>RT<', 'text-transparent', 'opacity-0', 'invisible', 'blank avatar square'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $html);
        }
    }

    public function test_admin_project_index_uses_react_component_and_action_props(): void
    {
        $admin = $this->makeAdmin();
        Project::factory()->create();

        $this->actingAs($admin)->get('/projects')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Index')
                ->where('pageTitle', 'Projects')
                ->where('primaryAction', 'Create Project')
                ->has('projects.data', 1)
            )
            ->assertSee('projects.create')
            ->assertSee('projects.show')
            ->assertSee('projects.edit')
            ->assertSee('projects.assign-coordinator.edit')
            ->assertSee('project.tasks.index');
    }

    public function test_admin_repository_index_uses_react_component_and_action_props(): void
    {
        $admin = $this->makeAdmin();
        RepositoryEntry::factory()->create();

        $this->actingAs($admin)->get('/repository')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Repository/Index')
                ->where('pageTitle', 'Repository Tracker')
                ->where('primaryAction', 'Create Repository Entry')
                ->has('entries.data', 1)
            )
            ->assertSee('repository.create')
            ->assertSee('repository.show')
            ->assertSee('repository.edit');
    }

    public function test_project_show_page_uses_compact_summary_props_and_actions(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create();

        $this->actingAs($admin)->get('/projects/'.$project->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Show')
                ->where('pageTitle', 'Project Details')
                ->where('project.id', $project->id)
                ->where('actions.0', 'View Tasks')
                ->where('actions.2', 'Assign Coordinator')
            );
    }

    public function test_coordinator_pages_expose_assigned_project_and_task_actions(): void
    {
        $admin = $this->makeAdmin();
        $coordinator = $this->makeCoordinator();
        $project = Project::factory()->create(['title' => 'Coordination Project']);
        $task = Task::factory()->for($project)->create();
        $this->assignCoordinator($project, $coordinator, $admin);

        $this->actingAs($coordinator)->get('/my-projects')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Mine')
                ->where('pageTitle', 'My Assigned Projects')
                ->has('projects.data', 1)
            );

        $this->actingAs($coordinator)->get('/projects/'.$project->id.'/tasks')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tasks/Index')
                ->where('pageTitle', 'Project Tasks')
                ->where('primaryAction', 'Create Task')
            );

        $this->actingAs($coordinator)->get('/tasks/'.$task->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tasks/Show')
                ->where('pageTitle', 'Task Details')
                ->where('actions.0', 'Create Work Item')
            );
    }

    public function test_subtask_show_page_exposes_assignment_controls_and_history(): void
    {
        $admin = $this->makeAdmin();
        $coordinator = $this->makeCoordinator();
        $subordinate = $this->makeSubordinate();
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $subtask = Subtask::factory()->for($project)->for($task)->create();
        $this->assignCoordinator($project, $coordinator, $admin);
        SubtaskAssignment::create([
            'subtask_id' => $subtask->id,
            'subordinate_id' => $subordinate->id,
            'assigned_by' => $coordinator->id,
            'assigned_at' => now(),
            'revoked_at' => null,
        ]);

        $this->actingAs($coordinator)->get('/subtasks/'.$subtask->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Subtasks/Show')
                ->where('pageTitle', 'Work Item Details')
                ->where('actions.0', 'Assign Subordinate')
                ->where('actions.2', 'Revoke Assignment')
                ->has('subtask.assignments', 1)
            );
    }

    public function test_subordinate_pages_expose_assigned_subtask_and_update_progress(): void
    {
        $admin = $this->makeAdmin();
        $subordinate = $this->makeSubordinate();
        $subtask = Subtask::factory()->forTask()->create(['title' => 'Assigned Layout Subtask']);
        SubtaskAssignment::create([
            'subtask_id' => $subtask->id,
            'subordinate_id' => $subordinate->id,
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
            'revoked_at' => null,
        ]);

        $this->actingAs($subordinate)->get('/my-subtasks')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('MySubtasks/Index')
                ->where('pageTitle', 'My Work Items')
                ->has('subtasks.data', 1)
            );

        $this->actingAs($subordinate)->get('/my-subtasks/'.$subtask->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('MySubtasks/Show')
                ->where('pageTitle', 'Work Item Details')
                ->where('action', route('subtasks.mine.progress', $subtask))
            );
    }

    public function test_rendered_html_contains_visible_primary_button_classes_marker(): void
    {
        $admin = $this->makeAdmin();
        Project::factory()->create();

        $this->actingAs($admin)->get('/projects')
            ->assertOk()
            ->assertSee('bg-gray-900', false)
            ->assertSee('text-white', false);
    }

    protected function assignCoordinator(Project $project, User $coordinator, User $assigner): ProjectAssignment
    {
        return ProjectAssignment::create([
            'project_id' => $project->id,
            'coordinator_id' => $coordinator->id,
            'assigned_by' => $assigner->id,
            'assignment_role' => 'primary',
            'assigned_at' => now(),
            'revoked_at' => null,
        ]);
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

