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

class WorkItemAssignmentVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_coordinator_assignment_creates_active_record_and_subordinate_sees_work_item(): void
    {
        $admin = $this->makeAdmin();
        $coordinator = $this->makeCoordinator('assigned-work-coordinator@example.com');
        $subordinate = $this->makeSubordinate('assigned-work-subordinate@example.com');
        $otherSubordinate = $this->makeSubordinate('other-work-subordinate@example.com');
        $project = Project::factory()->create(['title' => 'Workflow Simplification Project']);
        $task = Task::factory()->for($project)->create(['title' => 'Coordinator Task']);
        $workItem = Subtask::factory()->for($project)->for($task)->create(['title' => 'Visible Work Item']);
        $hiddenWorkItem = Subtask::factory()->for($project)->for($task)->create(['title' => 'Other User Work Item']);
        $this->assignCoordinator($project, $coordinator, $admin);

        $this->actingAs($coordinator)->post(route('subtasks.assign-subordinate.store', $workItem), [
            'subordinate_id' => $subordinate->id,
        ])->assertRedirect(route('subtasks.show', $workItem));
        $this->assignSubtask($hiddenWorkItem, $otherSubordinate, $admin);

        $this->assertDatabaseHas('subtask_assignments', [
            'subtask_id' => $workItem->id,
            'subordinate_id' => $subordinate->id,
            'assigned_by' => $coordinator->id,
            'revoked_at' => null,
        ]);

        $this->actingAs($subordinate)->get(route('subtasks.mine'))
            ->assertOk()
            ->assertSee('My Work Items')
            ->assertSee('Visible Work Item')
            ->assertSee('Workflow Simplification Project')
            ->assertSee('Coordinator Task')
            ->assertDontSee('Other User Work Item')
            ->assertInertia(fn (Assert $page) => $page
                ->component('MySubtasks/Index')
                ->where('pageTitle', 'My Work Items')
                ->where('subtasks.data.0.title', 'Visible Work Item')
                ->where('subtasks.data.0.project.title', 'Workflow Simplification Project')
                ->where('subtasks.data.0.task.title', 'Coordinator Task'));
    }

    public function test_newly_assigned_old_work_item_is_ordered_by_assignment_time(): void
    {
        $admin = $this->makeAdmin();
        $coordinator = $this->makeCoordinator('fresh-assignment-coordinator@example.com');
        $subordinate = $this->makeSubordinate('fresh-assignment-subordinate@example.com');
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $this->assignCoordinator($project, $coordinator, $admin);
        $olderAssigned = Subtask::factory()->for($project)->for($task)->create([
            'title' => 'Older visible assignment',
            'updated_at' => now(),
        ]);
        $newlyAssigned = Subtask::factory()->for($project)->for($task)->create([
            'title' => 'Fresh coordinator assignment',
            'updated_at' => now()->subYear(),
        ]);
        $this->assignSubtask($olderAssigned, $subordinate, $admin, now()->subDay());

        $this->actingAs($coordinator)->post(route('subtasks.assign-subordinate.store', $newlyAssigned), [
            'subordinate_id' => $subordinate->id,
        ])->assertRedirect(route('subtasks.show', $newlyAssigned));

        $this->actingAs($subordinate)->get(route('subtasks.mine'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('subtasks.data.0.title', 'Fresh coordinator assignment')
                ->where('subtasks.data.1.title', 'Older visible assignment'));
    }

    public function test_revoked_assignment_is_hidden_and_subordinate_detail_is_protected(): void
    {
        $admin = $this->makeAdmin();
        $coordinator = $this->makeCoordinator('revoker-work-coordinator@example.com');
        $subordinate = $this->makeSubordinate('revoked-work-subordinate@example.com');
        $otherSubordinate = $this->makeSubordinate('blocked-work-subordinate@example.com');
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $workItem = Subtask::factory()->for($project)->for($task)->create(['title' => 'Revoked Work Item']);
        $this->assignCoordinator($project, $coordinator, $admin);
        $this->assignSubtask($workItem, $subordinate, $coordinator);

        $this->actingAs($otherSubordinate)->get(route('subtasks.mine.show', $workItem))->assertForbidden();

        $this->actingAs($subordinate)->get(route('my-work-items.show', $workItem))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('MySubtasks/Show')
                ->where('pageTitle', 'Work Item Details'));

        $this->actingAs($coordinator)->post(route('subtasks.assign-subordinate.revoke', [$workItem, $subordinate]))->assertRedirect();

        $this->actingAs($subordinate)->get(route('subtasks.mine'))
            ->assertOk()
            ->assertDontSee('Revoked Work Item');
        $this->actingAs($subordinate)->get(route('subtasks.mine.show', $workItem))->assertForbidden();
    }

    public function test_subordinate_progress_is_visible_only_to_assigned_project_coordinator(): void
    {
        $admin = $this->makeAdmin();
        $assignedCoordinator = $this->makeCoordinator('progress-coordinator@example.com');
        $unassignedCoordinator = $this->makeCoordinator('blocked-progress-coordinator@example.com');
        $subordinate = $this->makeSubordinate('progress-subordinate@example.com');
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $workItem = Subtask::factory()->for($project)->for($task)->create();
        $this->assignCoordinator($project, $assignedCoordinator, $admin);
        $this->assignSubtask($workItem, $subordinate, $admin);

        $this->actingAs($subordinate)->patch(route('subtasks.mine.progress', $workItem), [
            'status' => 'in_progress',
            'progress_note' => 'Coordinator-visible work item progress.',
        ])->assertRedirect();

        $this->actingAs($assignedCoordinator)->get(route('subtasks.show', $workItem))
            ->assertOk()
            ->assertSee('Coordinator-visible work item progress.');
        $this->actingAs($unassignedCoordinator)->get(route('subtasks.show', $workItem))->assertForbidden();
    }

    public function test_assignment_dropdown_and_work_item_labels_are_role_safe(): void
    {
        $admin = $this->makeAdmin('dropdown-admin@example.com');
        $pm = $this->makePm('dropdown-pm@example.com');
        $coordinator = $this->makeCoordinator('dropdown-coordinator@example.com');
        $subordinate = $this->makeSubordinate('dropdown-subordinate@example.com');
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $workItem = Subtask::factory()->for($project)->for($task)->create();
        $this->assignCoordinator($project, $coordinator, $admin);

        $this->actingAs($coordinator)->get(route('subtasks.assign-subordinate.edit', $workItem))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Subtasks/AssignSubordinate')
                ->where('subordinates', fn ($subordinates): bool => collect($subordinates)->pluck('email')->contains('dropdown-subordinate@example.com')
                    && ! collect($subordinates)->pluck('email')->contains('dropdown-admin@example.com')
                    && ! collect($subordinates)->pluck('email')->contains('dropdown-pm@example.com')
                    && ! collect($subordinates)->pluck('email')->contains('dropdown-coordinator@example.com')));

        $this->actingAs($coordinator)->get(route('tasks.show', $task))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('pageTitle', 'Task Details')
                ->where('actions.0', 'Create Work Item')
                ->where('actions.1', 'View Work Items'));

        $this->actingAs($coordinator)->get(route('subtasks.show', $workItem))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Subtasks/Show')
                ->where('pageTitle', 'Work Item Details'));

        $this->actingAs($subordinate)->get(route('my-work-items.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('MySubtasks/Index')
                ->where('pageTitle', 'My Work Items'));

        $this->actingAs($coordinator)->post(route('subtasks.assign-subordinate.store', $workItem), ['subordinate_id' => $admin->id])->assertSessionHasErrors('subordinate_id');
        $this->actingAs($coordinator)->post(route('subtasks.assign-subordinate.store', $workItem), ['subordinate_id' => $pm->id])->assertSessionHasErrors('subordinate_id');
        $this->actingAs($coordinator)->post(route('subtasks.assign-subordinate.store', $workItem), ['subordinate_id' => $coordinator->id])->assertSessionHasErrors('subordinate_id');
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

    protected function assignSubtask(Subtask $subtask, User $subordinate, User $assigner, mixed $assignedAt = null): SubtaskAssignment
    {
        return SubtaskAssignment::create([
            'subtask_id' => $subtask->id,
            'subordinate_id' => $subordinate->id,
            'assigned_by' => $assigner->id,
            'assigned_at' => $assignedAt ?? now(),
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


