<?php

namespace Tests\Feature;

use App\Console\Commands\GenerateDeadlineNotifications;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\Subtask;
use App\Models\SubtaskAssignment;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowFile;
use App\Models\WorkflowMessage;
use App\Models\WorkflowNotification;
use App\Services\WorkflowNotificationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WorkflowNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_coordinator_assignment_creates_notification_for_coordinator(): void
    {
        $admin = $this->makeAdmin();
        $coordinator = $this->makeCoordinator('coord-assign@example.com');
        $project = Project::factory()->create();

        $this->actingAs($admin)
            ->post(route('projects.assign-coordinator.update', $project), ['coordinator_id' => $coordinator->id])
            ->assertRedirect();

        $this->assertDatabaseHas('workflow_notifications', [
            'user_id' => $coordinator->id,
            'type' => 'coordinator_assigned',
            'project_id' => $project->id,
            'title' => 'New Project Assigned',
            'body' => "You have been assigned to project: {$project->title}",
            'action_url' => '/projects/'.$project->id,
        ]);
    }

    public function test_coordinator_revoke_creates_notification_for_revoked_coordinator(): void
    {
        $admin = $this->makeAdmin();
        $coordinator = $this->makeCoordinator('coord-revoke@example.com');
        $project = Project::factory()->create();
        $this->assignCoordinator($project, $coordinator, $admin);

        $this->actingAs($admin)
            ->post(route('projects.assign-coordinator.revoke', $project))
            ->assertRedirect();

        $this->assertDatabaseHas('workflow_notifications', [
            'user_id' => $coordinator->id,
            'type' => 'coordinator_revoked',
            'project_id' => $project->id,
        ]);
    }

    public function test_subordinate_assignment_creates_notification_for_subordinate(): void
    {
        $coordinator = $this->makeCoordinator('coord-sub-assign@example.com');
        $subordinate = $this->makeSubordinate('sub-assign@example.com');
        $subtask = Subtask::factory()->forTask()->create();
        $this->assignCoordinator($subtask->project, $coordinator, $this->makeAdmin());

        $this->actingAs($coordinator)
            ->post(route('subtasks.assign-subordinate.store', $subtask), ['subordinate_id' => $subordinate->id])
            ->assertRedirect();

        $this->assertDatabaseHas('workflow_notifications', [
            'user_id' => $subordinate->id,
            'actor_id' => $coordinator->id,
            'type' => 'subordinate_assigned',
            'subtask_id' => $subtask->id,
            'title' => 'Work Item assigned',
            'body' => "You have been assigned to {$subtask->title} under {$subtask->project->title}.",
            'action_url' => '/my-subtasks/'.$subtask->id,
        ]);

        $this->assertDatabaseMissing('workflow_notifications', [
            'user_id' => $coordinator->id,
            'type' => 'subordinate_assigned',
            'subtask_id' => $subtask->id,
        ]);
    }

    public function test_inline_work_item_creation_notifies_assigned_subordinate(): void
    {
        $admin = $this->makeAdmin('admin-inline-subtask@example.com');
        $coordinator = $this->makeCoordinator('coord-inline-subtask@example.com');
        $subordinate = $this->makeSubordinate('sub-inline-subtask@example.com');
        $subtask = Subtask::factory()->forTask()->make([
            'title' => 'Inline assigned work item',
            'status' => 'pending',
            'priority' => 'medium',
            'deadline' => now()->addWeek()->toDateString(),
        ]);
        $task = $subtask->task;
        $this->assignCoordinator($task->project, $coordinator, $admin);

        $this->actingAs($coordinator)
            ->post(route('tasks.subtasks.store', $task), [
                'title' => $subtask->title,
                'description' => 'Created and assigned inline.',
                'status' => 'pending',
                'priority' => 'medium',
                'deadline' => now()->addWeek()->toDateString(),
                'subordinate_id' => $subordinate->id,
            ])
            ->assertRedirect();

        $created = Subtask::query()->where('title', 'Inline assigned work item')->firstOrFail();

        $this->assertDatabaseHas('workflow_notifications', [
            'user_id' => $subordinate->id,
            'actor_id' => $coordinator->id,
            'type' => 'subordinate_assigned',
            'subtask_id' => $created->id,
            'title' => 'Work Item assigned',
            'body' => "You have been assigned to {$created->title} under {$task->project->title}.",
            'action_url' => '/my-subtasks/'.$created->id,
        ]);
    }
    public function test_subordinate_revoke_creates_notification_for_revoked_subordinate(): void
    {
        $coordinator = $this->makeCoordinator('coord-sub-revoke@example.com');
        $subordinate = $this->makeSubordinate('sub-revoke@example.com');
        $subtask = Subtask::factory()->forTask()->create();
        $this->assignCoordinator($subtask->project, $coordinator, $this->makeAdmin());
        $this->assignSubtask($subtask, $subordinate, $coordinator);

        $this->actingAs($coordinator)
            ->post(route('subtasks.assign-subordinate.revoke', [$subtask, $subordinate]))
            ->assertRedirect();

        $this->assertDatabaseHas('workflow_notifications', [
            'user_id' => $subordinate->id,
            'type' => 'subordinate_revoked',
            'subtask_id' => $subtask->id,
        ]);
    }

    public function test_feedback_message_creates_notifications_for_relevant_users_excluding_sender(): void
    {
        $admin = $this->makeAdmin();
        $coordinator = $this->makeCoordinator('coord-msg@example.com');
        $project = Project::factory()->create();
        $this->assignCoordinator($project, $coordinator, $admin);

        $initialCount = WorkflowNotification::count();

        $this->actingAs($admin)
            ->post(route('projects.messages.store', $project), [
                'message_type' => 'feedback',
                'body' => 'Test feedback from admin',
            ])
            ->assertRedirect();

        $this->assertGreaterThan($initialCount, WorkflowNotification::count());

        $this->assertDatabaseHas('workflow_notifications', [
            'user_id' => $coordinator->id,
            'type' => 'message_created',
            'project_id' => $project->id,
        ]);

        // Admin (sender) should not receive a notification
        $this->assertDatabaseMissing('workflow_notifications', [
            'user_id' => $admin->id,
            'type' => 'message_created',
            'project_id' => $project->id,
        ]);
    }

    public function test_file_upload_creates_notifications_for_relevant_users_excluding_uploader(): void
    {
        Storage::fake('local');

        $admin = $this->makeAdmin();
        $coordinator = $this->makeCoordinator('coord-file@example.com');
        $project = Project::factory()->create();
        $this->assignCoordinator($project, $coordinator, $admin);

        $initialCount = WorkflowNotification::count();

        $file = \Illuminate\Http\UploadedFile::fake()->create('test.pdf', 100);

        $this->actingAs($admin)
            ->post(route('projects.files.store', $project), ['file' => $file])
            ->assertRedirect();

        $this->assertGreaterThan($initialCount, WorkflowNotification::count());

        $this->assertDatabaseHas('workflow_notifications', [
            'user_id' => $coordinator->id,
            'type' => 'file_uploaded',
            'project_id' => $project->id,
        ]);

        // Admin (uploader) should not receive a notification
        $this->assertDatabaseMissing('workflow_notifications', [
            'user_id' => $admin->id,
            'type' => 'file_uploaded',
            'project_id' => $project->id,
        ]);
    }

    public function test_subordinate_progress_update_notifies_assigned_coordinator(): void
    {
        $admin = $this->makeAdmin('admin-progress@example.com');
        $coordinator = $this->makeCoordinator('coord-progress@example.com');
        $subordinate = $this->makeSubordinate('sub-progress@example.com');
        $subtask = Subtask::factory()->forTask()->create();
        $this->assignCoordinator($subtask->project, $coordinator, $admin);
        $this->assignSubtask($subtask, $subordinate, $admin);

        $initialCount = WorkflowNotification::count();

        $this->actingAs($subordinate)
            ->patch(route('subtasks.mine.progress', $subtask), [
                'status' => 'in_progress',
                'progress_note' => 'Working on it',
            ])
            ->assertRedirect();

        $this->assertGreaterThan($initialCount, WorkflowNotification::count());

        $this->assertDatabaseHas('workflow_notifications', [
            'user_id' => $coordinator->id,
            'type' => 'progress_updated',
            'subtask_id' => $subtask->id,
        ]);
    }

    public function test_user_can_view_own_notifications(): void
    {
        $coordinator = $this->makeCoordinator('coord-view@example.com');
        $project = Project::factory()->create();
        $admin = $this->makeAdmin();
        $this->assignCoordinator($project, $coordinator, $admin);

        $this->actingAs($coordinator)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Notifications/Index')
                ->has('notifications')
            );
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $user1 = $this->makeAdmin('user1@test.com');
        $user2 = $this->makeCoordinator('user2@test.com');

        $notification = WorkflowNotification::create([
            'user_id' => $user1->id,
            'type' => 'message_created',
            'title' => 'Test',
            'body' => 'Test body',
        ]);

        $this->actingAs($user2)
            ->post(route('notifications.read', $notification))
            ->assertForbidden();

        $this->assertDatabaseHas('workflow_notifications', [
            'id' => $notification->id,
            'read_at' => null,
        ]);
    }

    public function test_user_can_mark_own_notification_as_read(): void
    {
        $coordinator = $this->makeCoordinator('coord-read@example.com');
        $notification = WorkflowNotification::create([
            'user_id' => $coordinator->id,
            'type' => 'coordinator_assigned',
            'title' => 'Test',
            'body' => 'Test body',
        ]);

        $this->actingAs($coordinator)
            ->post(route('notifications.read', $notification))
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_own_notifications_as_read(): void
    {
        $coordinator = $this->makeCoordinator('coord-readall@example.com');

        WorkflowNotification::create([
            'user_id' => $coordinator->id,
            'type' => 'coordinator_assigned',
            'title' => 'Test 1',
        ]);
        WorkflowNotification::create([
            'user_id' => $coordinator->id,
            'type' => 'message_created',
            'title' => 'Test 2',
        ]);

        $this->actingAs($coordinator)
            ->post(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertDatabaseCount('workflow_notifications', 2);
        $this->assertDatabaseMissing('workflow_notifications', [
            'user_id' => $coordinator->id,
            'read_at' => null,
        ]);
    }

    public function test_header_shared_props_include_unread_count(): void
    {
        $coordinator = $this->makeCoordinator('coord-unread@example.com');

        WorkflowNotification::create([
            'user_id' => $coordinator->id,
            'type' => 'coordinator_assigned',
            'title' => 'Unread 1',
        ]);
        WorkflowNotification::create([
            'user_id' => $coordinator->id,
            'type' => 'message_created',
            'title' => 'Unread 2',
        ]);

        $this->actingAs($coordinator)
            ->get(route('coordinator.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('notifications.unreadCount', 2)
            );
    }

    public function test_notification_page_renders_notifications(): void
    {
        $coordinator = $this->makeCoordinator('coord-render@example.com');
        $admin = $this->makeAdmin('admin-render@example.com');
        $project = Project::factory()->create();

        // Go through controller to create notification
        $this->actingAs($admin)
            ->post(route('projects.assign-coordinator.update', $project), ['coordinator_id' => $coordinator->id])
            ->assertRedirect();

        $this->actingAs($coordinator)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Notifications/Index')
                ->has('notifications.data')
                ->where('unreadCount', 1)
            );
    }

    public function test_deadline_command_creates_reminder_notifications(): void
    {
        $admin = $this->makeAdmin('admin-deadline@example.com');
        $coordinator = $this->makeCoordinator('coord-deadline@example.com');
        $project = Project::factory()->create([
            'deadline' => now()->addHours(12),
            'status' => 'in_progress',
        ]);
        $this->assignCoordinator($project, $coordinator, $admin);

        $this->artisan(GenerateDeadlineNotifications::class)->assertExitCode(0);

        $this->assertDatabaseHas('workflow_notifications', [
            'user_id' => $coordinator->id,
            'type' => 'deadline_reminder',
            'project_id' => $project->id,
        ]);
    }

    public function test_deadline_command_creates_overdue_notifications(): void
    {
        $admin = $this->makeAdmin('admin-overdue@example.com');
        $coordinator = $this->makeCoordinator('coord-overdue@example.com');
        $project = Project::factory()->create([
            'deadline' => now()->subDay(),
            'status' => 'in_progress',
        ]);
        $this->assignCoordinator($project, $coordinator, $admin);

        $this->artisan(GenerateDeadlineNotifications::class)->assertExitCode(0);

        $this->assertDatabaseHas('workflow_notifications', [
            'user_id' => $coordinator->id,
            'type' => 'overdue_alert',
            'project_id' => $project->id,
        ]);
    }

    public function test_deadline_command_does_not_create_duplicate_alerts(): void
    {
        $admin = $this->makeAdmin('admin-dup@example.com');
        $coordinator = $this->makeCoordinator('coord-dup@example.com');
        $project = Project::factory()->create([
            'deadline' => now()->subDay(),
            'status' => 'in_progress',
        ]);
        $this->assignCoordinator($project, $coordinator, $admin);

        $this->artisan(GenerateDeadlineNotifications::class)->assertExitCode(0);

        $countAfterFirst = WorkflowNotification::where('user_id', $coordinator->id)
            ->where('type', 'overdue_alert')
            ->where('project_id', $project->id)
            ->count();

        $this->artisan(GenerateDeadlineNotifications::class)->assertExitCode(0);

        $countAfterSecond = WorkflowNotification::where('user_id', $coordinator->id)
            ->where('type', 'overdue_alert')
            ->where('project_id', $project->id)
            ->count();

        $this->assertEquals($countAfterFirst, $countAfterSecond);
    }

    public function test_subordinate_receives_only_notifications_related_to_assigned_work_items(): void
    {
        $admin = $this->makeAdmin('admin-sub-owned@example.com');
        $coordinator1 = $this->makeCoordinator('coord-sub-owned@example.com');
        $coordinator2 = $this->makeCoordinator('coord-sub-other@example.com');
        $subordinate = $this->makeSubordinate('sub-only-owned@example.com');
        $otherSubordinate = $this->makeSubordinate('sub-other@example.com');

        $subtask1 = Subtask::factory()->forTask()->create();
        $subtask2 = Subtask::factory()->forTask()->create();

        $this->assignCoordinator($subtask1->project, $coordinator1, $admin);
        $this->assignCoordinator($subtask2->project, $coordinator2, $admin);

        // Go through controller for subtask1
        $this->actingAs($coordinator1)
            ->post(route('subtasks.assign-subordinate.store', $subtask1), ['subordinate_id' => $subordinate->id])
            ->assertRedirect();

        // Go through controller for subtask2
        $this->actingAs($coordinator2)
            ->post(route('subtasks.assign-subordinate.store', $subtask2), ['subordinate_id' => $otherSubordinate->id])
            ->assertRedirect();

        // Subordinate should get notification for subtask1
        $this->assertDatabaseHas('workflow_notifications', [
            'user_id' => $subordinate->id,
            'type' => 'subordinate_assigned',
            'subtask_id' => $subtask1->id,
        ]);

        // Subordinate should NOT get notification for subtask2
        $this->assertDatabaseMissing('workflow_notifications', [
            'user_id' => $subordinate->id,
            'type' => 'subordinate_assigned',
            'subtask_id' => $subtask2->id,
        ]);
    }

    public function test_coordinator_receives_only_notifications_related_to_assigned_projects(): void
    {
        $admin = $this->makeAdmin('admin-coord-owned@example.com');
        $coordinator1 = $this->makeCoordinator('coord-own@example.com');
        $coordinator2 = $this->makeCoordinator('coord-other@example.com');

        $project1 = Project::factory()->create();
        $project2 = Project::factory()->create();

        // Go through controller for project1
        $this->actingAs($admin)
            ->post(route('projects.assign-coordinator.update', $project1), ['coordinator_id' => $coordinator1->id])
            ->assertRedirect();

        // Go through controller for project2
        $this->actingAs($admin)
            ->post(route('projects.assign-coordinator.update', $project2), ['coordinator_id' => $coordinator2->id])
            ->assertRedirect();

        // coordinator1 should get notification for project1
        $this->assertDatabaseHas('workflow_notifications', [
            'user_id' => $coordinator1->id,
            'type' => 'coordinator_assigned',
            'project_id' => $project1->id,
        ]);

        // coordinator1 should NOT get notification for project2
        $this->assertDatabaseMissing('workflow_notifications', [
            'user_id' => $coordinator1->id,
            'type' => 'coordinator_assigned',
            'project_id' => $project2->id,
        ]);
    }

    public function test_no_global_notification_leakage_between_unrelated_users(): void
    {
        $admin = $this->makeAdmin('admin-leak@example.com');
        $coordinator = $this->makeCoordinator('coord-leak@example.com');
        $subordinate = $this->makeSubordinate('sub-leak@example.com');
        $otherCoordinator = $this->makeCoordinator('coord-other-leak@example.com');

        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $subtask = Subtask::factory()->for($project)->for($task)->create();

        $this->assignCoordinator($project, $coordinator, $admin);
        $this->assignSubtask($subtask, $subordinate, $admin);

        // otherCoordinator has no relation to this project
        $notificationCountBefore = WorkflowNotification::where('user_id', $otherCoordinator->id)->count();

        // Send a message on the project
        $this->actingAs($admin)
            ->post(route('projects.messages.store', $project), [
                'message_type' => 'feedback',
                'body' => 'Test message',
            ]);

        // otherCoordinator should NOT receive any new notification
        $notificationCountAfter = WorkflowNotification::where('user_id', $otherCoordinator->id)->count();
        $this->assertEquals($notificationCountBefore, $notificationCountAfter);
    }

    public function test_notification_page_includes_action_url_in_props(): void
    {
        $admin = $this->makeAdmin('admin-actionurl@example.com');
        $coordinator = $this->makeCoordinator('coord-actionurl@example.com');
        $project = Project::factory()->create();
        $this->assignCoordinator($project, $coordinator, $admin);

        // Create a notification with relative action_url
        $actionUrl = (string) parse_url(route('projects.show', $project), PHP_URL_PATH);
        $notification = WorkflowNotification::create([
            'user_id' => $coordinator->id,
            'actor_id' => $admin->id,
            'project_id' => $project->id,
            'type' => 'coordinator_assigned',
            'title' => 'New Project Assigned',
            'body' => "You have been assigned to project: {$project->title}",
            'action_url' => $actionUrl,
        ]);

        $this->actingAs($coordinator)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Notifications/Index')
                ->has('notifications.data', 1)
                ->where('notifications.data.0.action_url', $actionUrl)
            );
    }

    public function test_overdue_task_notification_stores_task_show_url(): void
    {
        $admin = $this->makeAdmin('admin-task-overdue@example.com');
        $coordinator = $this->makeCoordinator('coord-task-overdue@example.com');
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create([
            'deadline' => now()->subDay(),
            'status' => 'in_progress',
        ]);
        $this->assignCoordinator($project, $coordinator, $admin);

        $this->artisan(GenerateDeadlineNotifications::class)->assertExitCode(0);

        $notification = WorkflowNotification::where('user_id', $coordinator->id)
            ->where('type', 'overdue_alert')
            ->where('task_id', $task->id)
            ->first();

        $this->assertNotNull($notification);
        $expectedPath = (string) parse_url(route('tasks.show', $task), PHP_URL_PATH);
        $this->assertEquals($expectedPath, $notification->action_url);
    }


    public function test_notify_user_converts_absolute_action_url_to_relative_path(): void
    {
        $user = $this->makeCoordinator('absolute-url@example.com');

        app(WorkflowNotificationService::class)->notifyUser($user, [
            'type' => 'message_created',
            'title' => 'Absolute URL Test',
            'action_url' => 'https://example.com/projects/15?tab=files',
        ]);

        $this->assertSame('/projects/15?tab=files', WorkflowNotification::latest()->first()->action_url);
    }

    public function test_notify_user_converts_localhost_action_url_to_relative_path(): void
    {
        $user = $this->makeCoordinator('localhost-url@example.com');

        app(WorkflowNotificationService::class)->notifyUser($user, [
            'type' => 'message_created',
            'title' => 'Localhost URL Test',
            'action_url' => 'http://localhost:8000/tasks/9',
        ]);

        $this->assertSame('/tasks/9', WorkflowNotification::latest()->first()->action_url);
    }

    public function test_notify_user_keeps_valid_relative_action_url_unchanged(): void
    {
        $user = $this->makeCoordinator('relative-url@example.com');

        app(WorkflowNotificationService::class)->notifyUser($user, [
            'type' => 'message_created',
            'title' => 'Relative URL Test',
            'action_url' => '/projects/7',
        ]);

        $this->assertSame('/projects/7', WorkflowNotification::latest()->first()->action_url);
    }

    public function test_notify_user_converts_empty_or_malformed_action_url_to_null(): void
    {
        $user = $this->makeCoordinator('malformed-url@example.com');

        app(WorkflowNotificationService::class)->notifyUser($user, [
            'type' => 'message_created',
            'title' => 'Malformed URL Test',
            'action_url' => 'javascript:alert(1)',
        ]);

        $this->assertNull(WorkflowNotification::latest()->first()->action_url);
    }

    public function test_notify_many_deduplicates_duplicate_users_within_one_call(): void
    {
        $user = $this->makeCoordinator('dedupe-notify@example.com');

        app(WorkflowNotificationService::class)->notifyMany(collect([$user, $user]), [
            'type' => 'message_created',
            'title' => 'Dedupe Test',
            'action_url' => '/projects/1',
        ]);

        $this->assertSame(1, WorkflowNotification::where('user_id', $user->id)->where('title', 'Dedupe Test')->count());
    }

    public function test_initial_project_file_upload_creates_notification_for_relevant_recipients(): void
    {
        Storage::fake('local');

        $admin = $this->makeAdmin('admin-initial-project-file@example.com');
        $pm = $this->makePm('pm-initial-project-file@example.com');
        $file = \Illuminate\Http\UploadedFile::fake()->create('initial-project.pdf', 100);

        $this->actingAs($admin)
            ->post(route('projects.store'), [
                'title' => 'Project With Initial File',
                'status' => 'planned',
                'file' => $file,
            ])
            ->assertRedirect();

        $project = Project::where('title', 'Project With Initial File')->firstOrFail();

        $this->assertDatabaseHas('workflow_notifications', [
            'user_id' => $pm->id,
            'type' => 'file_uploaded',
            'project_id' => $project->id,
            'title' => 'File Uploaded',
            'action_url' => '/projects/'.$project->id,
        ]);
        $this->assertDatabaseMissing('workflow_notifications', [
            'user_id' => $admin->id,
            'type' => 'file_uploaded',
            'project_id' => $project->id,
        ]);
    }

    public function test_initial_task_file_upload_creates_notification_for_relevant_recipients(): void
    {
        Storage::fake('local');

        $admin = $this->makeAdmin('admin-initial-task-file@example.com');
        $coordinator = $this->makeCoordinator('coord-initial-task-file@example.com');
        $project = Project::factory()->create();
        $this->assignCoordinator($project, $coordinator, $admin);
        $file = \Illuminate\Http\UploadedFile::fake()->create('initial-task.pdf', 100);

        $this->actingAs($admin)
            ->post(route('project.tasks.store', $project), [
                'title' => 'Task With Initial File',
                'status' => 'pending',
                'file' => $file,
            ])
            ->assertRedirect();

        $task = Task::where('title', 'Task With Initial File')->firstOrFail();

        $this->assertDatabaseHas('workflow_notifications', [
            'user_id' => $coordinator->id,
            'type' => 'file_uploaded',
            'project_id' => $project->id,
            'task_id' => $task->id,
            'title' => 'File Uploaded',
            'action_url' => '/tasks/'.$task->id,
        ]);
        $this->assertDatabaseMissing('workflow_notifications', [
            'user_id' => $admin->id,
            'type' => 'file_uploaded',
            'task_id' => $task->id,
        ]);
    }

    public function test_project_creation_with_coordinator_notifies_assigned_coordinator(): void
    {
        $pm = $this->makePm('pm-create-assignment-notify@example.com');
        $coordinator = $this->makeCoordinator('coord-create-assignment-notify@example.com');

        $this->actingAs($pm)
            ->post(route('projects.store'), [
                'title' => 'Notification On Create Assignment',
                'status' => 'planned',
                'coordinator_id' => $coordinator->id,
            ])
            ->assertRedirect();

        $project = Project::where('title', 'Notification On Create Assignment')->firstOrFail();

        $this->assertDatabaseHas('workflow_notifications', [
            'user_id' => $coordinator->id,
            'actor_id' => $pm->id,
            'project_id' => $project->id,
            'type' => 'coordinator_assigned',
            'title' => 'New Project Assigned',
            'body' => "You have been assigned to project: {$project->title}",
            'action_url' => '/projects/'.$project->id,
        ]);

        $this->assertDatabaseMissing('workflow_notifications', [
            'user_id' => $pm->id,
            'project_id' => $project->id,
            'type' => 'coordinator_assigned',
        ]);
    }
    public function test_project_coordinator_reassignment_notifies_new_and_old_coordinators(): void
    {
        $admin = $this->makeAdmin('admin-reassign@example.com');
        $oldCoordinator = $this->makeCoordinator('old-reassign@example.com');
        $newCoordinator = $this->makeCoordinator('new-reassign@example.com');
        $project = Project::factory()->create();
        $this->assignCoordinator($project, $oldCoordinator, $admin);

        $this->actingAs($admin)
            ->post(route('projects.assign-coordinator.update', $project), ['coordinator_id' => $newCoordinator->id])
            ->assertRedirect();

        $this->assertDatabaseHas('workflow_notifications', [
            'user_id' => $newCoordinator->id,
            'type' => 'coordinator_assigned',
            'project_id' => $project->id,
        ]);
        $this->assertDatabaseHas('workflow_notifications', [
            'user_id' => $oldCoordinator->id,
            'type' => 'coordinator_revoked',
            'project_id' => $project->id,
        ]);
    }

    public function test_project_coordinator_reassignment_does_not_notify_when_same_coordinator_selected(): void
    {
        $admin = $this->makeAdmin('admin-same-reassign@example.com');
        $coordinator = $this->makeCoordinator('same-reassign@example.com');
        $project = Project::factory()->create();
        $this->assignCoordinator($project, $coordinator, $admin);

        $this->actingAs($admin)
            ->post(route('projects.assign-coordinator.update', $project), ['coordinator_id' => $coordinator->id])
            ->assertRedirect();

        $this->assertDatabaseMissing('workflow_notifications', [
            'user_id' => $coordinator->id,
            'type' => 'coordinator_revoked',
            'project_id' => $project->id,
        ]);
    }

    public function test_subordinate_assignment_does_not_duplicate_active_assignment_notification(): void
    {
        $coordinator = $this->makeCoordinator('coord-sub-dedupe@example.com');
        $subordinate = $this->makeSubordinate('sub-dedupe@example.com');
        $subtask = Subtask::factory()->forTask()->create();
        $this->assignCoordinator($subtask->project, $coordinator, $this->makeAdmin());

        $this->actingAs($coordinator)
            ->post(route('subtasks.assign-subordinate.store', $subtask), ['subordinate_id' => $subordinate->id])
            ->assertRedirect();
        $this->actingAs($coordinator)
            ->post(route('subtasks.assign-subordinate.store', $subtask), ['subordinate_id' => $subordinate->id])
            ->assertRedirect();

        $this->assertSame(1, WorkflowNotification::where('user_id', $subordinate->id)
            ->where('type', 'subordinate_assigned')
            ->where('subtask_id', $subtask->id)
            ->count());
    }
    public function test_coordinator_follow_up_project_file_notifies_project_creator_and_admins(): void
    {
        Storage::fake('local');

        $creator = $this->makePm('pm-follow-up-recipient@example.com');
        $admin = $this->makeAdmin('admin-follow-up-recipient@example.com');
        $coordinator = $this->makeCoordinator('coord-follow-up-uploader@example.com');
        $otherCoordinator = $this->makeCoordinator('coord-follow-up-unrelated@example.com');
        $project = Project::factory()->create(['created_by' => $creator->id]);
        $this->assignCoordinator($project, $coordinator, $admin);

        $file = \Illuminate\Http\UploadedFile::fake()->create('registrar-progress.pdf', 100, 'application/pdf');

        $this->actingAs($coordinator)
            ->post(route('projects.files.store', $project), [
                'file' => $file,
                'file_category' => 'follow_up',
            ])
            ->assertRedirect();

        $storedFile = WorkflowFile::where('original_name', 'registrar-progress.pdf')->firstOrFail();

        foreach ([$creator, $admin] as $recipient) {
            $this->assertDatabaseHas('workflow_notifications', [
                'user_id' => $recipient->id,
                'actor_id' => $coordinator->id,
                'project_id' => $project->id,
                'workflow_file_id' => $storedFile->id,
                'type' => 'file_uploaded',
                'title' => 'Follow-up file uploaded',
                'body' => "{$coordinator->name} uploaded a follow-up file for {$project->title}.",
                'action_url' => '/projects/'.$project->id,
            ]);
        }

        $this->assertDatabaseMissing('workflow_notifications', [
            'user_id' => $coordinator->id,
            'workflow_file_id' => $storedFile->id,
        ]);
        $this->assertDatabaseMissing('workflow_notifications', [
            'user_id' => $otherCoordinator->id,
            'workflow_file_id' => $storedFile->id,
        ]);
    }
    // Helper methods

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






