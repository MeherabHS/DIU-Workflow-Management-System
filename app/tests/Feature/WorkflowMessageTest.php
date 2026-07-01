<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\Subtask;
use App\Models\SubtaskAssignment;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowMessage;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WorkflowMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_cannot_access_project_messages(): void
    {
        $project = Project::factory()->create();

        $this->get(route('projects.messages.index', $project))->assertRedirect('/login');
    }

    public function test_admin_pm_and_assigned_coordinator_can_view_project_messages_but_others_cannot(): void
    {
        $admin = $this->makeAdmin();
        $pm = $this->makePm();
        $assignedCoordinator = $this->makeCoordinator('assigned-project-message@example.com');
        $unassignedCoordinator = $this->makeCoordinator('unassigned-project-message@example.com');
        $subordinate = $this->makeSubordinate();
        $project = Project::factory()->create();
        $this->assignCoordinator($project, $assignedCoordinator, $admin);

        $this->actingAs($admin)->get(route('projects.messages.index', $project))->assertOk();
        $this->actingAs($pm)->get(route('projects.messages.index', $project))->assertOk();
        $this->actingAs($assignedCoordinator)->get(route('projects.messages.index', $project))->assertOk();
        $this->actingAs($unassignedCoordinator)->get(route('projects.messages.index', $project))->assertForbidden();
        $this->actingAs($subordinate)->get(route('projects.messages.index', $project))->assertForbidden();
    }

    public function test_project_message_create_authorization_and_linkage(): void
    {
        $admin = $this->makeAdmin();
        $pm = $this->makePm();
        $assignedCoordinator = $this->makeCoordinator('assigned-project-create@example.com');
        $unassignedCoordinator = $this->makeCoordinator('unassigned-project-create@example.com');
        $subordinate = $this->makeSubordinate();
        $project = Project::factory()->create();
        $this->assignCoordinator($project, $assignedCoordinator, $admin);

        $this->actingAs($admin)->post(route('projects.messages.store', $project), $this->messagePayload(['body' => 'Admin project feedback']))->assertRedirect();
        $this->actingAs($pm)->post(route('projects.messages.store', $project), $this->messagePayload(['body' => 'PM follow-up']))->assertRedirect();
        $this->actingAs($assignedCoordinator)->post(route('projects.messages.store', $project), $this->messagePayload(['body' => 'Coordinator project update']))->assertRedirect();
        $this->actingAs($unassignedCoordinator)->post(route('projects.messages.store', $project), $this->messagePayload(['body' => 'Blocked coordinator']))->assertForbidden();
        $this->actingAs($subordinate)->post(route('projects.messages.store', $project), $this->messagePayload(['body' => 'Blocked subordinate']))->assertForbidden();

        // Body is encrypted, so we check other columns match
        $this->assertDatabaseHas('workflow_messages', [
            'project_id' => $project->id,
            'task_id' => null,
            'subtask_id' => null,
            'sender_id' => $admin->id,
            'message_type' => 'feedback',
        ]);
    }

    public function test_admin_pm_and_assigned_coordinator_can_view_and_create_task_messages(): void
    {
        $admin = $this->makeAdmin();
        $pm = $this->makePm();
        $assignedCoordinator = $this->makeCoordinator('assigned-task-message@example.com');
        $unassignedCoordinator = $this->makeCoordinator('unassigned-task-message@example.com');
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $this->assignCoordinator($project, $assignedCoordinator, $admin);

        $this->actingAs($admin)->get(route('tasks.messages.index', $task))->assertOk();
        $this->actingAs($pm)->get(route('tasks.messages.index', $task))->assertOk();
        $this->actingAs($assignedCoordinator)->get(route('tasks.messages.index', $task))->assertOk();
        $this->actingAs($unassignedCoordinator)->get(route('tasks.messages.index', $task))->assertForbidden();

        $this->actingAs($admin)->post(route('tasks.messages.store', $task), $this->messagePayload(['body' => 'Admin task feedback']))->assertRedirect();
        $this->actingAs($pm)->post(route('tasks.messages.store', $task), $this->messagePayload(['body' => 'PM task follow-up']))->assertRedirect();
        $this->actingAs($assignedCoordinator)->post(route('tasks.messages.store', $task), $this->messagePayload(['body' => 'Coordinator task clarification']))->assertRedirect();
        $this->actingAs($unassignedCoordinator)->post(route('tasks.messages.store', $task), $this->messagePayload(['body' => 'Blocked task message']))->assertForbidden();

        // Body is encrypted, so we check other columns match
        $this->assertDatabaseHas('workflow_messages', [
            'project_id' => $project->id,
            'task_id' => $task->id,
            'subtask_id' => null,
            'message_type' => 'feedback',
        ]);
    }

    public function test_assigned_subordinate_can_view_and_create_only_assigned_subtask_messages(): void
    {
        $admin = $this->makeAdmin();
        $assignedSubordinate = $this->makeSubordinate('assigned-subtask-message@example.com');
        $otherSubordinate = $this->makeSubordinate('other-subtask-message@example.com');
        $subtask = Subtask::factory()->forTask()->create();
        $otherSubtask = Subtask::factory()->forTask()->create();
        $this->assignSubtask($subtask, $assignedSubordinate, $admin);
        $this->assignSubtask($otherSubtask, $otherSubordinate, $admin);

        $this->actingAs($assignedSubordinate)->get(route('subtasks.messages.index', $subtask))->assertOk();
        $this->actingAs($assignedSubordinate)->post(route('subtasks.messages.store', $subtask), $this->messagePayload([
            'message_type' => 'progress_note',
            'body' => 'Subordinate progress note',
        ]))->assertRedirect();

        $this->actingAs($assignedSubordinate)->get(route('subtasks.messages.index', $otherSubtask))->assertForbidden();
        $this->actingAs($assignedSubordinate)->post(route('subtasks.messages.store', $otherSubtask), $this->messagePayload(['body' => 'Blocked unrelated note']))->assertForbidden();

        // Body is encrypted, so we check other columns match
        $this->assertDatabaseHas('workflow_messages', [
            'subtask_id' => $subtask->id,
            'sender_id' => $assignedSubordinate->id,
            'message_type' => 'progress_note',
        ]);
    }

    public function test_message_validation_sender_and_context_links(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $subtask = Subtask::factory()->for($project)->for($task)->create();

        $this->actingAs($admin)->post(route('projects.messages.store', $project), ['body' => ''])->assertSessionHasErrors('body');
        $this->actingAs($admin)->post(route('projects.messages.store', $project), ['body' => 'Valid body', 'message_type' => 'invalid'])->assertSessionHasErrors('message_type');

        $this->actingAs($admin)->post(route('projects.messages.store', $project), $this->messagePayload(['body' => 'Project context']))->assertRedirect();
        $this->actingAs($admin)->post(route('tasks.messages.store', $task), $this->messagePayload(['body' => 'Task context']))->assertRedirect();
        $this->actingAs($admin)->post(route('subtasks.messages.store', $subtask), $this->messagePayload(['body' => 'Subtask context']))->assertRedirect();

        // Body is encrypted, so we check context linkage via other columns
        $this->assertDatabaseHas('workflow_messages', ['project_id' => $project->id, 'task_id' => null, 'subtask_id' => null, 'sender_id' => $admin->id]);
        $this->assertDatabaseHas('workflow_messages', ['project_id' => $project->id, 'task_id' => $task->id, 'subtask_id' => null, 'sender_id' => $admin->id]);
        $this->assertDatabaseHas('workflow_messages', ['project_id' => $project->id, 'task_id' => $task->id, 'subtask_id' => $subtask->id, 'sender_id' => $admin->id]);
    }

    public function test_detail_pages_include_feedback_thread_props(): void
    {
        $admin = $this->makeAdmin();
        $subordinate = $this->makeSubordinate();
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $subtask = Subtask::factory()->for($project)->for($task)->create();
        $this->assignSubtask($subtask, $subordinate, $admin);

        $this->actingAs($admin)->get(route('projects.show', $project))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('messageSectionTitle', 'Feedback / Follow-up')
            ->has('messages')
            ->where('canCreateMessage', true)
            ->where('messageStoreUrl', route('projects.messages.store', $project)));

        $this->actingAs($admin)->get(route('tasks.show', $task))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('messageSectionTitle', 'Feedback / Follow-up')
            ->has('messages')
            ->where('messageStoreUrl', route('tasks.messages.store', $task)));

        $this->actingAs($admin)->get(route('subtasks.show', $subtask))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('messageSectionTitle', 'Feedback / Follow-up')
            ->has('messages')
            ->where('messageStoreUrl', route('subtasks.messages.store', $subtask)));

        $this->actingAs($subordinate)->get(route('subtasks.mine.show', $subtask))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('messageSectionTitle', 'Feedback / Follow-up')
            ->where('canCreateMessage', true)
            ->where('messageStoreUrl', route('subtasks.messages.store', $subtask)));
    }

    public function test_authorized_detail_props_enable_message_composer_for_all_allowed_roles(): void
    {
        $admin = $this->makeAdmin();
        $pm = $this->makePm();
        $coordinator = $this->makeCoordinator('composer-coordinator@example.com');
        $subordinate = $this->makeSubordinate('composer-subordinate@example.com');
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $subtask = Subtask::factory()->for($project)->for($task)->create();
        $this->assignCoordinator($project, $coordinator, $admin);
        $this->assignSubtask($subtask, $subordinate, $admin);

        foreach ([$admin, $pm, $coordinator] as $user) {
            $this->actingAs($user)->get(route('projects.show', $project))->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('canCreateMessage', true)
                ->where('messageStoreUrl', route('projects.messages.store', $project))
                ->where('defaultMessageType', 'message')
                ->where('allowedMessageTypes.0.value', 'message')
                ->where('allowedMessageTypes.1.value', 'feedback')
                ->where('allowedMessageTypes.2.value', 'follow_up')
                ->where('allowedMessageTypes.3.value', 'progress_note')
                ->where('allowedMessageTypes.4.value', 'clarification'));

            $this->actingAs($user)->get(route('tasks.show', $task))->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('canCreateMessage', true)
                ->where('messageStoreUrl', route('tasks.messages.store', $task))
                ->where('defaultMessageType', 'message'));

            $this->actingAs($user)->get(route('subtasks.show', $subtask))->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('canCreateMessage', true)
                ->where('messageStoreUrl', route('subtasks.messages.store', $subtask))
                ->where('defaultMessageType', 'message'));
        }

        $this->actingAs($subordinate)->get(route('subtasks.mine.show', $subtask))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('canCreateMessage', true)
            ->where('messageStoreUrl', route('subtasks.messages.store', $subtask))
            ->where('defaultMessageType', 'progress_note'));
    }

    public function test_messages_persist_and_appear_in_subsequent_detail_props(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create();

        $this->actingAs($admin)->post(route('projects.messages.store', $project), $this->messagePayload([
            'message_type' => 'follow_up',
            'body' => 'Persisted project follow-up',
        ]))->assertRedirect();

        $this->actingAs($admin)->get(route('projects.show', $project))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('messages.0.body', 'Persisted project follow-up')
            ->where('messages.0.message_type', 'follow_up')
            ->where('messages.0.sender_id', $admin->id));
    }

    public function test_message_thread_renders_composer_controls_when_authorized_even_with_empty_messages(): void
    {
        $thread = file_get_contents(resource_path('js/Components/WorkManagement/MessageThread.tsx'));
        $composer = file_get_contents(resource_path('js/Components/WorkManagement/MessageComposer.tsx'));

        $this->assertStringContainsString('No feedback or follow-up messages yet.', $thread);
        $this->assertStringContainsString('canCreateMessage && messageStoreUrl', $thread);
        $this->assertStringContainsString('<MessageComposer', $thread);
        $this->assertStringContainsString('Message Type', $composer);
        $this->assertStringContainsString('<textarea', $composer);
        $this->assertStringContainsString('Send Message', $composer);
    }
    public function test_no_global_message_routes_exist_and_history_order_is_preserved(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create();
        WorkflowMessage::create(['project_id' => $project->id, 'sender_id' => $admin->id, 'body' => 'First message', 'message_type' => 'message', 'created_at' => now()->subMinutes(2), 'updated_at' => now()->subMinutes(2)]);
        WorkflowMessage::create(['project_id' => $project->id, 'sender_id' => $admin->id, 'body' => 'Second message', 'message_type' => 'follow_up', 'created_at' => now()->subMinute(), 'updated_at' => now()->subMinute()]);

        $this->assertFalse(Route::has('messages.index'));
        $this->assertFalse(Route::has('chat.index'));
        $this->assertFalse(Route::has('inbox.index'));
        $this->actingAs($admin)->get('/messages')->assertNotFound();
        $this->actingAs($admin)->get('/chat')->assertNotFound();

        $this->actingAs($admin)->get(route('projects.messages.index', $project))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('messages.0.body', 'First message')
            ->where('messages.1.body', 'Second message'));
    }

    public function test_unauthorized_user_cannot_see_unrelated_message_body(): void
    {
        $admin = $this->makeAdmin();
        $assignedSubordinate = $this->makeSubordinate('visible-message-sub@example.com');
        $otherSubordinate = $this->makeSubordinate('hidden-message-sub@example.com');
        $subtask = Subtask::factory()->forTask()->create();
        $this->assignSubtask($subtask, $assignedSubordinate, $admin);
        WorkflowMessage::create(['project_id' => $subtask->project_id, 'task_id' => $subtask->task_id, 'subtask_id' => $subtask->id, 'sender_id' => $admin->id, 'body' => 'Confidential assigned thread', 'message_type' => 'feedback']);

        $this->actingAs($otherSubordinate)->get(route('subtasks.messages.index', $subtask))
            ->assertForbidden()
            ->assertDontSee('Confidential assigned thread');
    }

    public function test_subordinate_sees_own_message_on_assigned_work_item(): void
    {
        $admin = $this->makeAdmin();
        $subordinate = $this->makeSubordinate('sub-own-msg@example.com');
        $subtask = Subtask::factory()->forTask()->create();
        $this->assignSubtask($subtask, $subordinate, $admin);

        WorkflowMessage::create([
            'project_id' => $subtask->project_id,
            'task_id' => $subtask->task_id,
            'subtask_id' => $subtask->id,
            'sender_id' => $subordinate->id,
            'body' => 'My own progress note',
            'message_type' => 'progress_note',
        ]);

        $this->actingAs($subordinate)
            ->get(route('subtasks.messages.index', $subtask))
            ->assertOk()
            ->assertSee('My own progress note');
    }

    public function test_subordinate_sees_admin_pm_coordinator_messages_on_assigned_work_item(): void
    {
        $admin = $this->makeAdmin();
        $pm = $this->makePm();
        $coordinator = $this->makeCoordinator('coord-msg-sub@example.com');
        $subordinate = $this->makeSubordinate('sub-view-mgmt@example.com');
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $subtask = Subtask::factory()->for($project)->for($task)->create();
        $this->assignCoordinator($project, $coordinator, $admin);
        $this->assignSubtask($subtask, $subordinate, $admin);

        WorkflowMessage::create(['project_id' => $project->id, 'task_id' => $task->id, 'subtask_id' => $subtask->id, 'sender_id' => $admin->id, 'body' => 'Admin feedback', 'message_type' => 'feedback']);
        WorkflowMessage::create(['project_id' => $project->id, 'task_id' => $task->id, 'subtask_id' => $subtask->id, 'sender_id' => $pm->id, 'body' => 'PM follow-up', 'message_type' => 'follow_up']);
        WorkflowMessage::create(['project_id' => $project->id, 'task_id' => $task->id, 'subtask_id' => $subtask->id, 'sender_id' => $coordinator->id, 'body' => 'Coordinator note', 'message_type' => 'message']);

        $this->actingAs($subordinate)
            ->get(route('subtasks.messages.index', $subtask))
            ->assertOk()
            ->assertSee('Admin feedback')
            ->assertSee('PM follow-up')
            ->assertSee('Coordinator note');
    }

    public function test_subordinate_does_not_see_other_subordinate_message_on_same_work_item(): void
    {
        $admin = $this->makeAdmin();
        $subordinate1 = $this->makeSubordinate('sub1-hidden@example.com');
        $subordinate2 = $this->makeSubordinate('sub2-hidden@example.com');
        $subtask = Subtask::factory()->forTask()->create();
        $this->assignSubtask($subtask, $subordinate1, $admin);
        $this->assignSubtask($subtask, $subordinate2, $admin);

        // subordinate1 posts a message
        WorkflowMessage::create([
            'project_id' => $subtask->project_id,
            'task_id' => $subtask->task_id,
            'subtask_id' => $subtask->id,
            'sender_id' => $subordinate1->id,
            'body' => 'Secret message from sub1',
            'message_type' => 'progress_note',
        ]);

        // subordinate2 should NOT see sub1's message
        $this->actingAs($subordinate2)
            ->get(route('subtasks.messages.index', $subtask))
            ->assertOk()
            ->assertDontSee('Secret message from sub1');

        // But subordinate2 can see their own messages
        WorkflowMessage::create([
            'project_id' => $subtask->project_id,
            'task_id' => $subtask->task_id,
            'subtask_id' => $subtask->id,
            'sender_id' => $subordinate2->id,
            'body' => 'My own message',
            'message_type' => 'progress_note',
        ]);

        $this->actingAs($subordinate2)
            ->get(route('subtasks.messages.index', $subtask))
            ->assertOk()
            ->assertSee('My own message')
            ->assertDontSee('Secret message from sub1');
    }

    public function test_unassigned_subordinate_sees_nothing(): void
    {
        $admin = $this->makeAdmin();
        $unassignedSubordinate = $this->makeSubordinate('sub-unassigned@example.com');
        $subtask = Subtask::factory()->forTask()->create();

        WorkflowMessage::create([
            'project_id' => $subtask->project_id,
            'task_id' => $subtask->task_id,
            'subtask_id' => $subtask->id,
            'sender_id' => $admin->id,
            'body' => 'Admin note',
            'message_type' => 'message',
        ]);

        $this->actingAs($unassignedSubordinate)
            ->get(route('subtasks.messages.index', $subtask))
            ->assertForbidden();
    }

    public function test_message_body_is_encrypted_in_database_not_plaintext(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create();

        $this->actingAs($admin)
            ->post(route('projects.messages.store', $project), [
                'message_type' => 'feedback',
                'body' => 'Secret encrypted body',
            ])
            ->assertRedirect();

        $message = WorkflowMessage::latest()->first();

        // The raw database value should be encrypted (not plaintext)
        $rawBody = $message->getAttributes()['body'];
        $this->assertNotEquals('Secret encrypted body', $rawBody);
        $this->assertTrue(str_starts_with($rawBody, 'eyJ') || str_starts_with($rawBody, '{"iv":'));
    }

    public function test_message_displays_decrypted_correctly_in_ui_props(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create();

        $this->actingAs($admin)
            ->post(route('projects.messages.store', $project), [
                'message_type' => 'feedback',
                'body' => 'Decrypted display test',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('messages.0.body', 'Decrypted display test')
            );
    }

    public function test_notification_action_url_still_points_to_message_thread_route(): void
    {
        $admin = $this->makeAdmin();
        $coordinator = $this->makeCoordinator('coord-notif-url@example.com');
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $subtask = Subtask::factory()->for($project)->for($task)->create();
        $this->assignCoordinator($project, $coordinator, $admin);

        $this->actingAs($admin)
            ->post(route('projects.messages.store', $project), [
                'message_type' => 'feedback',
                'body' => 'Project message',
            ]);

        $projectNotif = \App\Models\WorkflowNotification::where('project_id', $project->id)
            ->where('type', 'message_created')
            ->latest()
            ->first();
        $this->assertEquals(
            (string) parse_url(route('projects.messages.index', $project), PHP_URL_PATH),
            $projectNotif->action_url
        );

        $this->actingAs($admin)
            ->post(route('tasks.messages.store', $task), [
                'message_type' => 'feedback',
                'body' => 'Task message',
            ]);

        $taskNotif = \App\Models\WorkflowNotification::where('task_id', $task->id)
            ->where('type', 'message_created')
            ->latest()
            ->first();
        $this->assertEquals(
            (string) parse_url(route('tasks.messages.index', $task), PHP_URL_PATH),
            $taskNotif->action_url
        );

        $this->actingAs($admin)
            ->post(route('subtasks.messages.store', $subtask), [
                'message_type' => 'feedback',
                'body' => 'Subtask message',
            ]);

        $subtaskNotif = \App\Models\WorkflowNotification::where('subtask_id', $subtask->id)
            ->where('type', 'message_created')
            ->latest()
            ->first();
        $this->assertEquals(
            (string) parse_url(route('subtasks.messages.index', $subtask), PHP_URL_PATH),
            $subtaskNotif->action_url
        );
    }

    protected function messagePayload(array $overrides = []): array
    {
        return array_merge(['message_type' => 'feedback', 'body' => 'Follow-up message body.'], $overrides);
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
