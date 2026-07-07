<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\Subtask;
use App\Models\SubtaskAssignment;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TaskSubtaskWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_cannot_access_project_tasks(): void
    {
        $project = Project::factory()->create();

        $this->get(route('project.tasks.index', $project))->assertRedirect('/login');
    }

    public function test_subordinate_cannot_access_project_tasks(): void
    {
        $subordinate = $this->makeSubordinate();
        $project = Project::factory()->create();

        $this->actingAs($subordinate)->get(route('project.tasks.index', $project))->assertForbidden();
    }

    public function test_admin_pm_and_assigned_coordinator_can_view_project_tasks(): void
    {
        $project = Project::factory()->create(['title' => 'Workflow Project']);
        $admin = $this->makeAdmin();
        $pm = $this->makePm();
        $coordinator = $this->makeCoordinator();
        $this->assignCoordinator($project, $coordinator, $admin);

        $this->actingAs($admin)->get(route('project.tasks.index', $project))
            ->assertOk()->assertSee('Project Tasks')->assertSee('Workflow Project');
        $this->actingAs($pm)->get(route('project.tasks.index', $project))
            ->assertOk()->assertSee('Project Tasks')->assertSee('Workflow Project');
        $this->actingAs($coordinator)->get(route('project.tasks.index', $project))
            ->assertOk()->assertSee('Project Tasks')->assertSee('Workflow Project');
    }

    public function test_unassigned_coordinator_cannot_view_project_tasks(): void
    {
        $coordinator = $this->makeCoordinator();
        $project = Project::factory()->create();

        $this->actingAs($coordinator)->get(route('project.tasks.index', $project))->assertForbidden();
    }

    public function test_admin_pm_and_assigned_coordinator_see_create_task_on_project_tasks_page(): void
    {
        $project = Project::factory()->create();
        $admin = $this->makeAdmin();
        $pm = $this->makePm();
        $coordinator = $this->makeCoordinator();
        $this->assignCoordinator($project, $coordinator, $admin);

        $this->actingAs($admin)->get(route('project.tasks.index', $project))->assertSee('Create Task');
        $this->actingAs($pm)->get(route('project.tasks.index', $project))->assertSee('Create Task');
        $this->actingAs($coordinator)->get(route('project.tasks.index', $project))->assertSee('Create Task');
    }

    public function test_subordinate_does_not_see_create_task(): void
    {
        $subordinate = $this->makeSubordinate();

        $this->actingAs($subordinate)->get('/subordinate/dashboard')
            ->assertOk()
            ->assertDontSee('Create Task');
    }

    public function test_admin_pm_and_assigned_coordinator_can_create_task(): void
    {
        $project = Project::factory()->create();
        $admin = $this->makeAdmin();
        $pm = $this->makePm();
        $coordinator = $this->makeCoordinator();
        $this->assignCoordinator($project, $coordinator, $admin);

        $this->actingAs($admin)->post(route('project.tasks.store', $project), $this->taskPayload(['title' => 'Admin task']))->assertRedirect();
        $this->actingAs($pm)->post(route('project.tasks.store', $project), $this->taskPayload(['title' => 'PM task']))->assertRedirect();
        $this->actingAs($coordinator)->post(route('project.tasks.store', $project), $this->taskPayload(['title' => 'Coordinator task']))->assertRedirect();

        $this->assertDatabaseHas('tasks', ['project_id' => $project->id, 'title' => 'Admin task']);
        $this->assertDatabaseHas('tasks', ['project_id' => $project->id, 'title' => 'PM task']);
        $this->assertDatabaseHas('tasks', ['project_id' => $project->id, 'title' => 'Coordinator task']);
    }

    public function test_unassigned_coordinator_cannot_create_task_under_another_project(): void
    {
        $coordinator = $this->makeCoordinator();
        $project = Project::factory()->create();

        $this->actingAs($coordinator)->post(route('project.tasks.store', $project), $this->taskPayload())->assertForbidden();
    }

    public function test_task_validation_requires_title_and_valid_status(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create();

        $this->actingAs($admin)->post(route('project.tasks.store', $project), ['title' => '', 'status' => 'invalid'])
            ->assertSessionHasErrors(['title', 'status']);
    }

    public function test_admin_pm_and_assigned_coordinator_can_update_task_but_subordinate_cannot(): void
    {
        $admin = $this->makeAdmin();
        $pm = $this->makePm();
        $coordinator = $this->makeCoordinator();
        $subordinate = $this->makeSubordinate();
        $project = Project::factory()->create();
        $this->assignCoordinator($project, $coordinator, $admin);
        $task = Task::factory()->for($project)->create(['title' => 'Original']);

        $this->actingAs($admin)->patch(route('tasks.update', $task), $this->taskPayload(['title' => 'Admin updated']))->assertRedirect();
        $this->actingAs($pm)->patch(route('tasks.update', $task), $this->taskPayload(['title' => 'PM updated']))->assertRedirect();
        $this->actingAs($coordinator)->patch(route('tasks.update', $task), $this->taskPayload(['title' => 'Coordinator updated']))->assertRedirect();
        $this->actingAs($subordinate)->patch(route('tasks.update', $task), $this->taskPayload(['title' => 'Blocked']))->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Coordinator updated']);
    }

    public function test_only_assigned_coordinator_can_create_work_item_but_admin_pm_and_unassigned_coordinator_cannot(): void
    {
        $admin = $this->makeAdmin();
        $pm = $this->makePm();
        $coordinator = $this->makeCoordinator();
        $blockedCoordinator = $this->makeCoordinator('blocked-coordinator@example.com');
        $project = Project::factory()->create();
        $this->assignCoordinator($project, $coordinator, $admin);
        $task = Task::factory()->for($project)->create();

        $this->actingAs($admin)->post(route('tasks.subtasks.store', $task), $this->subtaskPayload(['title' => 'Admin subtask']))->assertForbidden();
        $this->actingAs($pm)->post(route('tasks.subtasks.store', $task), $this->subtaskPayload(['title' => 'PM subtask']))->assertForbidden();
        $this->actingAs($coordinator)->post(route('tasks.subtasks.store', $task), $this->subtaskPayload(['title' => 'Coordinator subtask']))->assertRedirect();
        $this->actingAs($blockedCoordinator)->post(route('tasks.subtasks.store', $task), $this->subtaskPayload(['title' => 'Blocked subtask']))->assertForbidden();

        $this->assertDatabaseMissing('subtasks', ['task_id' => $task->id, 'title' => 'Admin subtask']);
        $this->assertDatabaseMissing('subtasks', ['task_id' => $task->id, 'title' => 'PM subtask']);
        $this->assertDatabaseHas('subtasks', ['task_id' => $task->id, 'title' => 'Coordinator subtask']);
        $this->assertDatabaseMissing('subtasks', ['task_id' => $task->id, 'title' => 'Blocked subtask']);
    }

    public function test_coordinator_can_assign_subordinate_while_creating_work_item(): void
    {
        $admin = $this->makeAdmin();
        $coordinator = $this->makeCoordinator('create-work-item-coordinator@example.com');
        $subordinate = $this->makeSubordinate('create-work-item-subordinate@example.com');
        $inactiveSubordinate = $this->makeSubordinate('inactive-subordinate-list@example.com');
        $inactiveSubordinate->update(['is_active' => false]);
        $pmOnly = $this->makePm('pm-not-subordinate-create-work-item@example.com');
        $adminOnly = $this->makeAdmin('admin-not-subordinate-create-work-item@example.com');
        $coordinatorOnly = $this->makeCoordinator('coord-not-subordinate-create-work-item@example.com');
        $noRole = User::factory()->create(['email' => 'pending-no-role-create-work-item@example.com', 'is_active' => true]);
        $noRole->syncRoles([]);
        $project = Project::factory()->create();
        $this->assignCoordinator($project, $coordinator, $admin);
        $task = Task::factory()->for($project)->create();

        $this->actingAs($coordinator)->get(route('tasks.subtasks.create', $task))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Subtasks/Form')
                ->where('subordinateUsers', fn ($users): bool => collect($users)->pluck('id')->contains($subordinate->id)
                    && ! collect($users)->pluck('id')->contains($inactiveSubordinate->id)
                    && ! collect($users)->pluck('id')->contains($pmOnly->id)
                    && ! collect($users)->pluck('id')->contains($adminOnly->id)
                    && ! collect($users)->pluck('id')->contains($coordinatorOnly->id)
                    && ! collect($users)->pluck('id')->contains($noRole->id))
                ->where('assignableSubordinates', fn ($users): bool => collect($users)->pluck('id')->contains($subordinate->id)
                    && ! collect($users)->pluck('id')->contains($inactiveSubordinate->id)
                    && ! collect($users)->pluck('id')->contains($pmOnly->id)
                    && ! collect($users)->pluck('id')->contains($adminOnly->id)
                    && ! collect($users)->pluck('id')->contains($coordinatorOnly->id)
                    && ! collect($users)->pluck('id')->contains($noRole->id)));

        $this->actingAs($coordinator)->post(route('tasks.subtasks.store', $task), $this->subtaskPayload([
            'title' => 'Assigned during creation',
            'subordinate_id' => $subordinate->id,
        ]))->assertRedirect();

        $subtask = Subtask::query()->where('title', 'Assigned during creation')->firstOrFail();
        $this->assertDatabaseHas('subtask_assignments', [
            'subtask_id' => $subtask->id,
            'subordinate_id' => $subordinate->id,
            'assigned_by' => $coordinator->id,
        ]);
    }
    public function test_subtask_validation_requires_title_and_valid_status(): void
    {
        $admin = $this->makeAdmin();
        $coordinator = $this->makeCoordinator();
        $project = Project::factory()->create();
        $this->assignCoordinator($project, $coordinator, $admin);
        $task = Task::factory()->for($project)->create();

        $this->actingAs($coordinator)->post(route('tasks.subtasks.store', $task), ['title' => '', 'status' => 'invalid'])
            ->assertSessionHasErrors(['title', 'status']);
    }

    public function test_only_assigned_coordinator_can_assign_subordinate_but_admin_pm_and_unassigned_coordinator_cannot(): void
    {
        $admin = $this->makeAdmin();
        $pm = $this->makePm();
        $coordinator = $this->makeCoordinator();
        $blockedCoordinator = $this->makeCoordinator('blocked-assign@example.com');
        $subordinate = $this->makeSubordinate();
        $inactiveSubordinate = $this->makeSubordinate('inactive-assignment@example.com');
        $inactiveSubordinate->update(['is_active' => false]);
        $project = Project::factory()->create();
        $this->assignCoordinator($project, $coordinator, $admin);
        $task = Task::factory()->for($project)->create();
        $subtask = Subtask::factory()->for($project)->for($task)->create();
        $otherSubtask = Subtask::factory()->for($project)->for($task)->create();
        $thirdSubtask = Subtask::factory()->for($project)->for($task)->create();

        $this->actingAs($admin)->post(route('subtasks.assign-subordinate.store', $subtask), ['subordinate_id' => $subordinate->id])->assertForbidden();
        $this->actingAs($pm)->post(route('subtasks.assign-subordinate.store', $otherSubtask), ['subordinate_id' => $subordinate->id])->assertForbidden();
        $this->actingAs($coordinator)->post(route('subtasks.assign-subordinate.store', $thirdSubtask), ['subordinate_id' => $inactiveSubordinate->id])->assertSessionHasErrors('subordinate_id');
        $this->actingAs($coordinator)->post(route('subtasks.assign-subordinate.store', $thirdSubtask), ['subordinate_id' => $subordinate->id])->assertRedirect();
        $this->actingAs($blockedCoordinator)->post(route('subtasks.assign-subordinate.store', $subtask), ['subordinate_id' => $subordinate->id])->assertForbidden();

        $this->assertDatabaseMissing('subtask_assignments', ['subtask_id' => $subtask->id, 'subordinate_id' => $subordinate->id]);
        $this->assertDatabaseMissing('subtask_assignments', ['subtask_id' => $otherSubtask->id, 'subordinate_id' => $subordinate->id]);
        $this->assertDatabaseHas('subtask_assignments', ['subtask_id' => $thirdSubtask->id, 'subordinate_id' => $subordinate->id]);
    }

    public function test_same_active_subordinate_assignment_does_not_create_duplicate_and_reassign_after_revoke_creates_new_row(): void
    {
        $admin = $this->makeAdmin();
        $coordinator = $this->makeCoordinator();
        $subordinate = $this->makeSubordinate();
        $project = Project::factory()->create();
        $this->assignCoordinator($project, $coordinator, $admin);
        $task = Task::factory()->for($project)->create();
        $subtask = Subtask::factory()->for($project)->for($task)->create();

        $this->actingAs($coordinator)->post(route('subtasks.assign-subordinate.store', $subtask), ['subordinate_id' => $subordinate->id])->assertRedirect();
        $this->actingAs($coordinator)->post(route('subtasks.assign-subordinate.store', $subtask), ['subordinate_id' => $subordinate->id])
            ->assertRedirect(route('subtasks.assign-subordinate.edit', $subtask));
        $this->assertSame(1, SubtaskAssignment::query()->where('subtask_id', $subtask->id)->count());

        $this->actingAs($coordinator)->post(route('subtasks.assign-subordinate.revoke', [$subtask, $subordinate]))->assertRedirect();
        $assignment = SubtaskAssignment::query()->where('subtask_id', $subtask->id)->first();
        $this->assertNotNull($assignment?->revoked_at);

        $this->actingAs($coordinator)->post(route('subtasks.assign-subordinate.store', $subtask), ['subordinate_id' => $subordinate->id])->assertRedirect();
        $this->assertSame(2, SubtaskAssignment::query()->where('subtask_id', $subtask->id)->count());
        $this->assertSame(1, SubtaskAssignment::query()->where('subtask_id', $subtask->id)->whereNull('revoked_at')->count());
    }

    public function test_subordinate_visibility_is_limited_to_active_assignments_and_other_subordinates_are_blocked(): void
    {
        $admin = $this->makeAdmin();
        $assigned = $this->makeSubordinate('assigned@example.com');
        $other = $this->makeSubordinate('other@example.com');
        $visible = Subtask::factory()->forTask()->create(['title' => 'Visible assignment']);
        $hidden = Subtask::factory()->forTask()->create(['title' => 'Hidden assignment']);
        $this->assignSubtask($visible, $assigned, $admin);

        $this->actingAs($assigned)->get(route('subtasks.mine'))
            ->assertOk()
            ->assertSee('My Work Items')
            ->assertSee('Visible assignment')
            ->assertDontSee('Hidden assignment');

        $this->actingAs($other)->get(route('subtasks.mine.show', $visible))->assertForbidden();
    }

    public function test_revoked_subordinate_no_longer_sees_subtask_in_my_subtasks(): void
    {
        $admin = $this->makeAdmin();
        $coordinator = $this->makeCoordinator();
        $subordinate = $this->makeSubordinate();
        $project = Project::factory()->create();
        $this->assignCoordinator($project, $coordinator, $admin);
        $task = Task::factory()->for($project)->create();
        $subtask = Subtask::factory()->for($project)->for($task)->create(['title' => 'Revoked subtask']);
        $this->assignSubtask($subtask, $subordinate, $coordinator);
        $this->actingAs($coordinator)->post(route('subtasks.assign-subordinate.revoke', [$subtask, $subordinate]));

        $this->actingAs($subordinate)->get(route('subtasks.mine'))
            ->assertOk()
            ->assertDontSee('Revoked subtask');
    }

    public function test_subordinate_can_update_progress_for_assigned_subtask_but_not_unassigned_subtask(): void
    {
        $admin = $this->makeAdmin();
        $subordinate = $this->makeSubordinate();
        $subtask = Subtask::factory()->forTask()->create();
        $unassigned = Subtask::factory()->forTask()->create();
        $this->assignSubtask($subtask, $subordinate, $admin);

        $this->actingAs($subordinate)->patch(route('subtasks.mine.progress', $subtask), [
            'status' => 'in_progress',
            'progress_note' => 'Started work.',
        ])->assertRedirect();

        $this->actingAs($subordinate)->patch(route('subtasks.mine.progress', $unassigned), [
            'status' => 'in_progress',
            'progress_note' => 'Blocked.',
        ])->assertForbidden();

        $this->assertDatabaseHas('subtasks', ['id' => $subtask->id, 'status' => 'in_progress', 'progress_note' => 'Started work.']);
    }

    public function test_assigned_coordinator_can_submit_project_for_review(): void
    {
        $admin = $this->makeAdmin();
        $coordinator = $this->makeCoordinator('submit-project-coordinator@example.com');
        $project = Project::factory()->create(['status' => 'in_progress', 'submitted_at' => null]);
        $this->assignCoordinator($project, $coordinator, $admin);

        $this->actingAs($coordinator)
            ->post(route('projects.submit-for-review', $project))
            ->assertRedirect(route('projects.show', $project));

        $project->refresh();
        $this->assertSame('submitted', $project->status);
        $this->assertNotNull($project->submitted_at);
        $this->assertNull($project->completed_at);
    }

    public function test_unassigned_coordinator_and_subordinate_cannot_submit_project_for_review(): void
    {
        $coordinator = $this->makeCoordinator('blocked-submit-project@example.com');
        $subordinate = $this->makeSubordinate('blocked-submit-subordinate@example.com');
        $project = Project::factory()->create(['status' => 'in_progress']);

        $this->actingAs($coordinator)
            ->post(route('projects.submit-for-review', $project))
            ->assertForbidden();

        $this->actingAs($subordinate)
            ->post(route('projects.submit-for-review', $project))
            ->assertForbidden();

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'status' => 'in_progress']);
    }

    public function test_pm_can_mark_submitted_project_completed_without_auto_completion(): void
    {
        $pm = $this->makePm('pm-complete-submitted@example.com');
        $project = Project::factory()->create(['created_by' => $pm->id, 'status' => 'submitted', 'submitted_at' => now(), 'completed_at' => null]);

        $this->actingAs($pm)
            ->patch(route('projects.update', $project), [
                'title' => $project->title,
                'description' => $project->description,
                'department_id' => $project->department_id,
                'status' => 'completed',
                'priority' => $project->priority,
                'start_date' => $project->start_date?->format('Y-m-d'),
                'deadline' => $project->deadline?->format('Y-m-d'),
            ])
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'status' => 'completed']);
    }
    public function test_required_visible_task_and_subtask_ui_texts_are_present(): void
    {
        $admin = $this->makeAdmin();
        $subordinate = $this->makeSubordinate();
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $subtask = Subtask::factory()->for($project)->for($task)->create();
        $this->assignSubtask($subtask, $subordinate, $admin);

        $this->actingAs($admin)->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Show')
                ->where('actions.0', 'View Tasks')
                ->where('actions.3', 'Create Task'));

        $this->actingAs($admin)->get(route('tasks.show', $task))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tasks/Show')
                ->where('pageTitle', 'Task Details')
                ->where('actions.0', 'View Work Items')
                ->has('task.subtasks'));

        $this->actingAs($admin)->get(route('subtasks.show', $subtask))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Subtasks/Show')
                ->where('pageTitle', 'Work Item Details')
                ->where('canAssignSubordinate', false)
                ->where('canRevokeSubordinate', false)
                ->has('subtask.assignments'));

        $this->actingAs($subordinate)->get(route('subtasks.mine.show', $subtask))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('MySubtasks/Show')
                ->where('pageTitle', 'Work Item Details')
                ->where('action', route('subtasks.mine.progress', $subtask)));
        $taskShow = file_get_contents(resource_path('js/Pages/Tasks/Show.tsx'));
        $subtaskForm = file_get_contents(resource_path('js/Pages/Subtasks/Form.tsx'));

        $this->assertStringContainsString('Assigned Subordinate', $taskShow);
        $this->assertStringContainsString('canAssignSubordinateOnTask', $taskShow);
        $this->assertStringContainsString('Assign Subordinate', $taskShow);
        $this->assertStringContainsString("{method === 'post' && (", $subtaskForm);
        $this->assertStringContainsString('No active Subordinate users available.', $subtaskForm);
    }

    public function test_navigation_and_dashboard_show_my_assigned_subtasks_only_for_subordinate_and_task_workflow_links_for_coordinator(): void
    {
        $subordinate = $this->makeSubordinate();
        $coordinator = $this->makeCoordinator();

        $this->actingAs($subordinate)->get('/subordinate/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('navigation.0.label', 'My Work Items')
                ->where('navigation.0.href', route('my-work-items.index')));
        $this->actingAs($subordinate)->get(route('subordinate.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboards/RoleDashboard')
                ->where('modules.0.title', 'My Work Items')
                ->where('modules.0.href', route('my-work-items.index')));

        $this->actingAs($coordinator)->get('/coordinator/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('navigation.1.label', 'My Assigned Projects')
                ->where('navigation.1.href', route('projects.mine')));
        $this->actingAs($coordinator)->get(route('coordinator.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboards/RoleDashboard')
                ->where('modules.0.actionLabel', 'My Assigned Projects')
                ->where('modules.1.actionLabel', 'View Tasks')
                ->where('primaryAction.href', route('projects.mine')));
    }

    public function test_assigned_coordinator_sees_create_task_create_subtask_and_assign_subordinate_props(): void
    {
        $admin = $this->makeAdmin();
        $coordinator = $this->makeCoordinator();
        $project = Project::factory()->create(['title' => 'Assigned Task Workflow']);
        $this->assignCoordinator($project, $coordinator, $admin);
        $task = Task::factory()->for($project)->create(['title' => 'Coordinator Task']);
        $subtask = Subtask::factory()->for($project)->for($task)->create(['title' => 'Coordinator Subtask']);

        $this->actingAs($coordinator)->get(route('project.tasks.index', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tasks/Index')
                ->where('canCreateTask', true));

        $this->actingAs($coordinator)->get(route('tasks.show', $task))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tasks/Show')
                ->where('canCreateSubtask', true)
                ->where('canAssignSubordinate', true)
                ->where('actions.0', 'Create Work Item')
                ->where('actions.1', 'View Work Items'));

        $this->actingAs($coordinator)->get(route('subtasks.show', $subtask))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Subtasks/Show')
                ->where('canAssignSubordinate', true)
                ->where('canRevokeSubordinate', true)
                ->where('actions.0', 'Assign Subordinate'));
    }

    public function test_coordinator_create_task_form_includes_active_subordinate_dropdown_only(): void
    {
        $admin = $this->makeAdmin('admin-task-shortcut-form@example.com');
        $coordinator = $this->makeCoordinator('coord-task-shortcut-form@example.com');
        $subordinate = $this->makeSubordinate('active-task-shortcut-sub@example.com');
        $inactiveSubordinate = $this->makeSubordinate('inactive-task-shortcut-sub@example.com');
        $inactiveSubordinate->update(['is_active' => false]);
        $pending = User::factory()->create(['email' => 'pending-task-shortcut@example.com', 'is_active' => true]);
        $pending->syncRoles([]);
        $project = Project::factory()->create();
        $this->assignCoordinator($project, $coordinator, $admin);

        $this->actingAs($coordinator)->get(route('project.tasks.create', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Tasks/Form')
                ->where('canAssignSubordinateOnCreate', true)
                ->where('assignableSubordinates', fn ($users): bool => collect($users)->pluck('id')->contains($subordinate->id)
                    && ! collect($users)->pluck('id')->contains($inactiveSubordinate->id)
                    && ! collect($users)->pluck('id')->contains($pending->id)));
    }

    public function test_coordinator_create_task_with_subordinate_creates_default_work_item_assignment_and_notification(): void
    {
        $admin = $this->makeAdmin('admin-task-shortcut-create@example.com');
        $coordinator = $this->makeCoordinator('coord-task-shortcut-create@example.com');
        $subordinate = $this->makeSubordinate('sub-task-shortcut-create@example.com');
        $project = Project::factory()->create(['title' => 'Shortcut Project']);
        $this->assignCoordinator($project, $coordinator, $admin);

        $this->actingAs($coordinator)->post(route('project.tasks.store', $project), $this->taskPayload([
            'title' => 'Shortcut Task',
            'description' => 'Shortcut description',
            'subordinate_id' => $subordinate->id,
        ]))->assertRedirect();

        $task = Task::where('title', 'Shortcut Task')->firstOrFail();
        $subtask = Subtask::where('task_id', $task->id)->firstOrFail();

        $this->assertSame('Shortcut Task', $subtask->title);
        $this->assertSame('Shortcut description', $subtask->description);
        $this->assertDatabaseHas('subtask_assignments', [
            'subtask_id' => $subtask->id,
            'subordinate_id' => $subordinate->id,
            'assigned_by' => $coordinator->id,
            'revoked_at' => null,
        ]);

        $this->actingAs($subordinate)->get(route('subtasks.mine'))
            ->assertOk()
            ->assertSee('Shortcut Task');

        $this->assertDatabaseHas('workflow_notifications', [
            'user_id' => $subordinate->id,
            'actor_id' => $coordinator->id,
            'type' => 'subordinate_assigned',
            'subtask_id' => $subtask->id,
            'title' => 'Work Item assigned',
            'body' => 'You have been assigned work under Shortcut Project: Shortcut Task',
            'action_url' => '/my-subtasks/'.$subtask->id,
        ]);
        $this->assertDatabaseMissing('workflow_notifications', [
            'user_id' => $coordinator->id,
            'type' => 'subordinate_assigned',
            'subtask_id' => $subtask->id,
        ]);
    }

    public function test_coordinator_create_task_assign_later_does_not_create_work_item(): void
    {
        $admin = $this->makeAdmin('admin-task-assign-later@example.com');
        $coordinator = $this->makeCoordinator('coord-task-assign-later@example.com');
        $project = Project::factory()->create();
        $this->assignCoordinator($project, $coordinator, $admin);

        $this->actingAs($coordinator)->post(route('project.tasks.store', $project), $this->taskPayload([
            'title' => 'Assign Later Task',
            'subordinate_id' => null,
        ]))->assertRedirect();

        $task = Task::where('title', 'Assign Later Task')->firstOrFail();
        $this->assertSame(0, Subtask::where('task_id', $task->id)->count());
    }

    public function test_task_detail_assign_subordinate_creates_default_work_item_when_needed(): void
    {
        $admin = $this->makeAdmin('admin-task-detail-assign@example.com');
        $coordinator = $this->makeCoordinator('coord-task-detail-assign@example.com');
        $subordinate = $this->makeSubordinate('sub-task-detail-assign@example.com');
        $project = Project::factory()->create();
        $this->assignCoordinator($project, $coordinator, $admin);
        $task = Task::factory()->for($project)->create(['title' => 'Detail Assign Task']);

        $this->actingAs($coordinator)->post(route('tasks.assign-subordinate.store', $task), [
            'subordinate_id' => $subordinate->id,
        ])->assertRedirect(route('tasks.show', $task));

        $subtask = Subtask::where('task_id', $task->id)->firstOrFail();
        $this->assertSame('Detail Assign Task', $subtask->title);
        $this->assertDatabaseHas('subtask_assignments', [
            'subtask_id' => $subtask->id,
            'subordinate_id' => $subordinate->id,
            'assigned_by' => $coordinator->id,
            'revoked_at' => null,
        ]);
    }

    public function test_pm_cannot_assign_subordinate_through_task_creation_shortcut(): void
    {
        $pm = $this->makePm('pm-task-shortcut-blocked@example.com');
        $subordinate = $this->makeSubordinate('sub-pm-task-shortcut-blocked@example.com');
        $project = Project::factory()->create();

        $this->actingAs($pm)->post(route('project.tasks.store', $project), $this->taskPayload([
            'title' => 'PM Shortcut Attempt',
            'subordinate_id' => $subordinate->id,
        ]))->assertForbidden();

        $this->assertDatabaseMissing('tasks', ['title' => 'PM Shortcut Attempt']);
        $this->assertSame(0, SubtaskAssignment::count());
    }

    public function test_lower_roles_do_not_receive_or_render_ai_comparison_or_generic_progress(): void
    {
        $admin = $this->makeAdmin('admin-ai-hidden@example.com');
        $coordinator = $this->makeCoordinator('coord-ai-hidden@example.com');
        $subordinate = $this->makeSubordinate('sub-ai-hidden@example.com');
        $project = Project::factory()->create();
        $this->assignCoordinator($project, $coordinator, $admin);
        $task = Task::factory()->for($project)->create();
        $subtask = Subtask::factory()->for($project)->for($task)->create();
        $this->assignSubtask($subtask, $subordinate, $coordinator);

        $this->actingAs($coordinator)->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canShowComparison', false)
                ->where('comparisonResult', null)
                ->where('comparisonRunUrl', null));

        $this->actingAs($coordinator)->get(route('tasks.show', $task))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canShowComparison', false)
                ->where('comparisonResult', null)
                ->where('comparisonRunUrl', null));

        $this->actingAs($subordinate)->get(route('subtasks.mine.show', $subtask))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('comparisonResult')
                ->missing('comparisonRunUrl'));

        $taskShow = file_get_contents(resource_path('js/Pages/Tasks/Show.tsx'));
        $mySubtaskShow = file_get_contents(resource_path('js/Pages/MySubtasks/Show.tsx'));
        $subtaskShow = file_get_contents(resource_path('js/Pages/Subtasks/Show.tsx'));

        $this->assertStringNotContainsString("expected={['Assignment'", $subtaskShow);
        $this->assertStringNotContainsString("expected={['Review assignment'", $mySubtaskShow);
        $this->assertStringContainsString('canShowComparison && <RequirementDeliverableComparison', $taskShow);
        $this->assertStringNotContainsString('RequirementDeliverableComparison', $mySubtaskShow);
        $this->assertStringNotContainsString('ProgressComparison', $mySubtaskShow);
    }
    protected function taskPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Draft task',
            'description' => 'Task body',
            'assigned_to' => null,
            'status' => 'pending',
            'priority' => 'high',
            'deadline' => '2026-07-01',
        ], $overrides);
    }

    protected function subtaskPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Draft subtask',
            'description' => 'Subtask body',
            'status' => 'pending',
            'priority' => 'medium',
            'deadline' => '2026-07-02',
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








