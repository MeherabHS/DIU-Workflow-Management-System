<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\Subtask;
use App\Models\SubtaskAssignment;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RoleBoundaryVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_subordinate_dashboard_and_navigation_only_expose_assigned_subtask_work(): void
    {
        $subordinate = $this->makeSubordinate();

        $this->actingAs($subordinate)->get('/subordinate/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('navigation.0.label', 'My Work Items')
                ->where('navigation.1.label', 'Profile')
                ->has('navigation', 2)
                ->where('modules.0.title', 'My Work Items')
                ->where('modules.1.title', 'Update Progress')
                ->where('modules.2.title', 'Deadline View')
                ->has('modules', 3));
    }

    public function test_subordinate_is_blocked_from_project_repository_and_coordinator_routes(): void
    {
        $subordinate = $this->makeSubordinate();
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();

        $this->actingAs($subordinate)->get('/projects')->assertForbidden();
        $this->actingAs($subordinate)->get(route('projects.show', $project))->assertForbidden();
        $this->actingAs($subordinate)->get('/repository')->assertForbidden();
        $this->actingAs($subordinate)->get('/my-projects')->assertForbidden();
        $this->actingAs($subordinate)->get(route('project.tasks.index', $project))->assertForbidden();
        $this->actingAs($subordinate)->get(route('tasks.show', $task))->assertForbidden();
        $this->actingAs($subordinate)->get(route('subtasks.mine'))->assertOk();
    }

    public function test_subordinate_sees_only_active_assigned_subtasks_and_updates_only_own_work(): void
    {
        $admin = $this->makeAdmin();
        $subordinate = $this->makeSubordinate('assigned-boundary@example.com');
        $otherSubordinate = $this->makeSubordinate('other-boundary@example.com');
        $visible = Subtask::factory()->forTask()->create(['title' => 'Visible active assignment']);
        $other = Subtask::factory()->forTask()->create(['title' => 'Other user assignment']);
        $revoked = Subtask::factory()->forTask()->create(['title' => 'Revoked assignment']);

        $this->assignSubtask($visible, $subordinate, $admin);
        $this->assignSubtask($other, $otherSubordinate, $admin);
        $revokedAssignment = $this->assignSubtask($revoked, $subordinate, $admin);
        $revokedAssignment->update(['revoked_at' => now()]);

        $this->actingAs($subordinate)->get(route('subtasks.mine'))
            ->assertOk()
            ->assertSee('Visible active assignment')
            ->assertDontSee('Other user assignment')
            ->assertDontSee('Revoked assignment');

        $this->actingAs($subordinate)->get(route('subtasks.mine.show', $visible))->assertOk();
        $this->actingAs($subordinate)->get(route('subtasks.mine.show', $other))->assertForbidden();

        $this->actingAs($subordinate)->patch(route('subtasks.mine.progress', $visible), [
            'status' => 'in_progress',
            'progress_note' => 'Boundary progress update.',
        ])->assertRedirect();

        $this->actingAs($subordinate)->patch(route('subtasks.mine.progress', $other), [
            'status' => 'in_progress',
            'progress_note' => 'Blocked update.',
        ])->assertForbidden();
    }

    public function test_coordinator_progress_visibility_is_limited_to_assigned_projects(): void
    {
        $admin = $this->makeAdmin();
        $assignedCoordinator = $this->makeCoordinator('assigned-progress@example.com');
        $unassignedCoordinator = $this->makeCoordinator('unassigned-progress@example.com');
        $subordinate = $this->makeSubordinate();
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $subtask = Subtask::factory()->for($project)->for($task)->create([
            'title' => 'Coordinator visible progress',
            'progress_note' => 'Subordinate submitted progress.',
        ]);

        $this->assignCoordinator($project, $assignedCoordinator, $admin);
        $this->assignSubtask($subtask, $subordinate, $admin);

        $this->actingAs($assignedCoordinator)->get(route('subtasks.show', $subtask))
            ->assertOk()
            ->assertSee('Subordinate submitted progress.');

        $this->actingAs($unassignedCoordinator)->get(route('subtasks.show', $subtask))->assertForbidden();
        $this->actingAs($assignedCoordinator)->get(route('tasks.show', $task))->assertOk();
        $this->actingAs($unassignedCoordinator)->get(route('tasks.show', $task))->assertForbidden();
        $this->actingAs($admin)->get(route('subtasks.show', $subtask))->assertOk();
        $this->actingAs($subordinate)->get(route('subtasks.mine.show', $subtask))->assertOk();
    }

    public function test_task_create_pages_do_not_expose_subordinate_assignment_or_fake_attachment_dropzone(): void
    {
        $admin = $this->makeAdmin('assignment-admin@example.com');
        $pm = $this->makePm('assignment-pm@example.com');
        $coordinator = $this->makeCoordinator('assignment-coordinator@example.com');
        $project = Project::factory()->create();
        $this->assignCoordinator($project, $coordinator, $admin);

        foreach ([$admin, $pm, $coordinator] as $user) {
            $this->actingAs($user)->get(route('project.tasks.create', $project))
                ->assertOk()
                ->assertDontSee('Assign To')
                ->assertDontSee('Select subordinate')
                ->assertDontSee('Attachments dropzone is visual only for this phase')
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Tasks/Form')
                    ->missing('assignableSubordinates'));
        }

        $this->actingAs($coordinator)->post(route('project.tasks.store', $project), $this->taskPayload(['assigned_to' => $this->makeSubordinate('ignored-assignee@example.com')->id]))->assertRedirect();
        $task = Task::query()->latest('id')->first();
        $this->assertNull($task->assigned_to);
    }
    public function test_subtask_assignment_and_project_coordinator_assignment_lists_are_role_specific(): void
    {
        $admin = $this->makeAdmin('list-admin@example.com');
        $pm = $this->makePm('list-pm@example.com');
        $coordinator = $this->makeCoordinator('list-coordinator@example.com');
        $subordinate = $this->makeSubordinate('list-subordinate@example.com');
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $subtask = Subtask::factory()->for($project)->for($task)->create();
        $this->assignCoordinator($project, $coordinator, $admin);

        $this->actingAs($coordinator)->get(route('subtasks.assign-subordinate.edit', $subtask))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('subordinates', fn ($users): bool => collect($users)->pluck('id')->contains($subordinate->id)
                    && ! collect($users)->pluck('id')->intersect([$admin->id, $pm->id, $coordinator->id])->isNotEmpty()));

        $this->actingAs($coordinator)->post(route('subtasks.assign-subordinate.store', $subtask), ['subordinate_id' => $subordinate->id])->assertRedirect();
        $this->actingAs($coordinator)->post(route('subtasks.assign-subordinate.store', $subtask), ['subordinate_id' => $admin->id])->assertSessionHasErrors('subordinate_id');
        $this->actingAs($coordinator)->post(route('subtasks.assign-subordinate.store', $subtask), ['subordinate_id' => $pm->id])->assertSessionHasErrors('subordinate_id');
        $this->actingAs($coordinator)->post(route('subtasks.assign-subordinate.store', $subtask), ['subordinate_id' => $coordinator->id])->assertSessionHasErrors('subordinate_id');

        $this->actingAs($admin)->get(route('projects.assign-coordinator.edit', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('coordinators', fn ($users): bool => collect($users)->pluck('id')->contains($coordinator->id)
                    && ! collect($users)->pluck('id')->contains($subordinate->id)));
    }

    public function test_project_show_close_href_and_assignment_labels_are_role_aware(): void
    {
        $admin = $this->makeAdmin();
        $coordinator = $this->makeCoordinator();
        $project = Project::factory()->create();
        $this->assignCoordinator($project, $coordinator, $admin);

        $this->actingAs($coordinator)->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canAssignCoordinator', false)
                ->where('closeHref', route('projects.mine'))
                ->where('actions.0', 'View Tasks')
                ->where('actions.1', 'Create Task'));

        $this->actingAs($admin)->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canAssignCoordinator', true)
                ->where('closeHref', route('projects.index'))
                ->where('actions.2', 'Assign Coordinator'));
    }

    public function test_work_cards_use_original_sample_structure_without_metadata_grid_props(): void
    {
        $taskCard = file_get_contents(resource_path('js/Components/WorkManagement/TaskCard.tsx'));
        $projectIndex = file_get_contents(resource_path('js/Pages/Projects/Index.tsx'));
        $mySubtasksIndex = file_get_contents(resource_path('js/Pages/MySubtasks/Index.tsx'));

        $this->assertStringContainsString('title', $taskCard);
        $this->assertStringContainsString('description', $taskCard);
        $this->assertStringContainsString('PriorityBadge', $taskCard);
        $this->assertStringContainsString('StatusBadge', $taskCard);
        $this->assertStringContainsString('Clock', $taskCard);
        $this->assertStringContainsString('AssignmentChips', $taskCard);
        $this->assertStringContainsString('Paperclip', $taskCard);
        $this->assertStringNotContainsString('meta?:', $taskCard);
        $this->assertStringNotContainsString('meta.map', $taskCard);
        $this->assertStringNotContainsString('meta={[', $projectIndex);
        $this->assertStringNotContainsString('Department', $projectIndex);
        $this->assertStringNotContainsString('Created By', $projectIndex);
        $this->assertStringNotContainsString('meta={[', $mySubtasksIndex);
    }

    public function test_project_and_subtask_close_hrefs_stay_on_authorized_pages(): void
    {
        $admin = $this->makeAdmin();
        $coordinator = $this->makeCoordinator();
        $subordinate = $this->makeSubordinate();
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $subtask = Subtask::factory()->for($project)->for($task)->create();
        $this->assignCoordinator($project, $coordinator, $admin);
        $this->assignSubtask($subtask, $subordinate, $admin);

        $this->actingAs($coordinator)->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('closeHref', route('projects.mine')));

        $this->actingAs($subordinate)->get(route('subtasks.mine.show', $subtask))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('closeHref', route('my-work-items.index')));
    }

    protected function taskPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Boundary task',
            'description' => 'Task body',
            'assigned_to' => null,
            'status' => 'pending',
            'priority' => 'medium',
            'deadline' => '2026-07-01',
        ], $overrides);
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

    protected function assignSubtask(Subtask $subtask, User $subordinate, User $assigner): SubtaskAssignment
    {
        return SubtaskAssignment::create([
            'subtask_id' => $subtask->id,
            'subordinate_id' => $subordinate->id,
            'assigned_by' => $assigner->id,
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

