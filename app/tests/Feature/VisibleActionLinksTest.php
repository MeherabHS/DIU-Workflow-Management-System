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

class VisibleActionLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_sees_visible_project_and_repository_actions(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create();
        $entry = RepositoryEntry::factory()->create();

        $this->actingAs($admin)->get('/projects')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Index')
                ->where('primaryAction', 'Create Project')
                ->where('visibleActions.0', 'Create Project')
                ->where('visibleActions.1', 'View')
                ->where('visibleActions.3', 'Assign Coordinator')
                ->where('visibleActions.4', 'View Tasks'));

        $this->actingAs($admin)->get('/repository')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Repository/Index')
                ->where('primaryAction', 'Create Repository Entry')
                ->where('entries.data.0.title', $entry->title));

        $this->actingAs($admin)->get('/projects/'.$project->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Show')
                ->where('actions.0', 'View Tasks')
                ->where('actions.2', 'Assign Coordinator'));
    }

    public function test_pm_sees_visible_project_and_repository_actions(): void
    {
        $pm = $this->makePm();
        Project::factory()->create();
        RepositoryEntry::factory()->create();

        $this->actingAs($pm)->get('/projects')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Index')
                ->where('primaryAction', 'Create Project'));

        $this->actingAs($pm)->get('/repository')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Repository/Index')
                ->where('primaryAction', 'Create Repository Entry'));
    }

    public function test_coordinator_sees_assigned_project_actions_and_cannot_access_projects_index(): void
    {
        $admin = $this->makeAdmin();
        $coordinator = $this->makeCoordinator();
        $project = Project::factory()->create(['title' => 'Assigned UI Project']);
        $task = Task::factory()->for($project)->create();
        $this->assignCoordinator($project, $coordinator, $admin);

        $this->actingAs($coordinator)->get('/my-projects')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Mine')
                ->where('projects.data.0.title', 'Assigned UI Project'));

        $this->actingAs($coordinator)->get('/projects/'.$project->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Show')
                ->where('actions.0', 'View Tasks'));

        $this->actingAs($coordinator)->get('/projects/'.$project->id.'/tasks')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tasks/Index')
                ->where('primaryAction', 'Create Task'));

        $this->actingAs($coordinator)->get('/projects')->assertForbidden();

        $this->actingAs($coordinator)->get('/tasks/'.$task->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tasks/Show')
                ->where('actions.0', 'Create Work Item'));
    }

    public function test_subordinate_sees_assigned_subtask_actions(): void
    {
        $admin = $this->makeAdmin();
        $subordinate = $this->makeSubordinate();
        $subtask = Subtask::factory()->forTask()->create(['title' => 'Assigned UI Subtask']);
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
                ->where('subtasks.data.0.title', 'Assigned UI Subtask'));

        $this->actingAs($subordinate)->get('/my-subtasks/'.$subtask->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('MySubtasks/Show')
                ->where('action', route('subtasks.mine.progress', $subtask)));
    }

    public function test_rendered_html_contains_non_empty_action_text(): void
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
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
            'revoked_at' => null,
        ]);

        $this->actingAs($admin)->get('/projects')->assertInertia(fn (Assert $page) => $page->where('primaryAction', 'Create Project'));
        $this->actingAs($admin)->get('/repository')->assertInertia(fn (Assert $page) => $page->where('primaryAction', 'Create Repository Entry'));
        $this->actingAs($admin)->get('/projects/'.$project->id)->assertInertia(fn (Assert $page) => $page->where('actions.0', 'View Tasks'));
        $this->actingAs($coordinator)->get('/projects/'.$project->id.'/tasks')->assertInertia(fn (Assert $page) => $page->where('primaryAction', 'Create Task'));
        $this->actingAs($admin)->get('/tasks/'.$task->id)->assertInertia(fn (Assert $page) => $page->where('actions.0', 'View Work Items'));
        $this->actingAs($coordinator)->get('/subtasks/'.$subtask->id)->assertInertia(fn (Assert $page) => $page
            ->where('actions.0', 'Assign Subordinate')
            ->has('subtask.assignments'));
        $this->actingAs($subordinate)->get('/my-subtasks/'.$subtask->id)->assertInertia(fn (Assert $page) => $page->where('action', route('subtasks.mine.progress', $subtask)));
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







