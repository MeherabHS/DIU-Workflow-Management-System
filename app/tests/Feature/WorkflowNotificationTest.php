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
            'type' => 'subordinate_assigned',
            'subtask_id' => $subtask->id,
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
            ->get(route('dashboard'))
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
            'status' => 'active',
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
            'status' => 'active',
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
            'status' => 'active',
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
            'title' => 'Assigned as Coordinator',
            'body' => "You have been assigned as coordinator for project: {$project->title}",
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
