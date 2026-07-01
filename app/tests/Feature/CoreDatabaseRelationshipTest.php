<?php

namespace Tests\Feature;

use App\Models\ArchiveRecord;
use App\Models\Department;
use App\Models\File as ProjectFile;
use App\Models\Message;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\RepositoryEntry;
use App\Models\RepositoryUpdate;
use App\Models\Subtask;
use App\Models\SubtaskAssignment;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowFile;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CoreDatabaseRelationshipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_department_can_have_projects(): void
    {
        $department = Department::factory()->create();
        $projects = Project::factory()->count(2)->for($department)->create();

        $this->assertCount(2, $department->projects);
        $this->assertEqualsCanonicalizing($projects->modelKeys(), $department->projects->modelKeys());
    }

    public function test_a_pm_user_can_create_a_project(): void
    {
        $pm = User::factory()->create();
        $pm->syncRoles(['PM/Manager']);

        $project = Project::factory()->for($pm, 'creator')->create();

        $this->assertTrue($pm->hasRole('PM/Manager'));
        $this->assertTrue($pm->createdProjects->contains($project));
        $this->assertTrue($project->creator->is($pm));
    }

    public function test_a_project_can_be_assigned_to_a_coordinator(): void
    {
        $project = Project::factory()->create();
        $pm = User::factory()->create();
        $coordinator = User::factory()->create();

        $assignment = ProjectAssignment::create([
            'project_id' => $project->id,
            'coordinator_id' => $coordinator->id,
            'assigned_by' => $pm->id,
            'assignment_role' => 'primary',
            'assigned_at' => now(),
        ]);

        $this->assertTrue($project->assignments->contains($assignment));
        $this->assertTrue($assignment->coordinator->is($coordinator));
        $this->assertTrue($project->coordinators->contains($coordinator));
    }

    public function test_a_project_can_have_tasks(): void
    {
        $project = Project::factory()->create();
        $tasks = Task::factory()->count(2)->for($project)->create();

        $this->assertCount(2, $project->tasks);
        $this->assertEqualsCanonicalizing($tasks->modelKeys(), $project->tasks->modelKeys());
    }

    public function test_a_task_can_have_subtasks(): void
    {
        $task = Task::factory()->create();
        $subtasks = Subtask::factory()->count(2)->for($task->project)->for($task)->create();

        $this->assertCount(2, $task->subtasks);
        $this->assertEqualsCanonicalizing($subtasks->modelKeys(), $task->subtasks->modelKeys());
    }

    public function test_a_subtask_can_be_assigned_to_a_subordinate(): void
    {
        $subtask = Subtask::factory()->create();
        $coordinator = User::factory()->create();
        $subordinate = User::factory()->create();
        $subordinate->syncRoles(['Subordinate']);

        $assignment = SubtaskAssignment::create([
            'subtask_id' => $subtask->id,
            'subordinate_id' => $subordinate->id,
            'assigned_by' => $coordinator->id,
            'assigned_at' => now(),
        ]);

        $this->assertTrue($subtask->assignments->contains($assignment));
        $this->assertTrue($assignment->subordinate->is($subordinate));
        $this->assertTrue($subordinate->assignedSubtasks->contains($subtask));
    }

    public function test_a_project_can_have_repository_entries(): void
    {
        $project = Project::factory()->create();
        $entries = RepositoryEntry::factory()->count(2)->for($project)->create();

        $this->assertCount(2, $project->repositoryEntries);
        $this->assertEqualsCanonicalizing($entries->modelKeys(), $project->repositoryEntries->modelKeys());
    }

    public function test_a_repository_entry_can_have_timeline_updates(): void
    {
        $entry = RepositoryEntry::factory()->create();
        $user = User::factory()->create();

        $update = RepositoryUpdate::create([
            'repository_entry_id' => $entry->id,
            'user_id' => $user->id,
            'update_type' => 'status_changed',
            'old_status' => 'planned',
            'new_status' => 'submitted',
            'note' => 'Submitted for review.',
        ]);

        $this->assertTrue($entry->updates->contains($update));
        $this->assertTrue($update->user->is($user));
    }

    public function test_a_project_can_have_linked_messages(): void
    {
        $project = Project::factory()->create();
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $message = Message::create([
            'project_id' => $project->id,
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'body' => 'Project-linked message.',
        ]);

        $this->assertTrue($project->messages->contains($message));
        $this->assertTrue($sender->sentMessages->contains($message));
        $this->assertTrue($receiver->receivedMessages->contains($message));
    }

    public function test_a_message_belongs_to_sender_and_receiver(): void
    {
        $project = Project::factory()->create();
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $message = Message::create([
            'project_id' => $project->id,
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'body' => 'Assigned coordination update.',
        ]);

        $this->assertTrue($message->sender->is($sender));
        $this->assertTrue($message->receiver->is($receiver));
    }

    public function test_a_project_task_subtask_and_repository_can_have_workflow_file_metadata(): void
    {
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $subtask = Subtask::factory()->for($project)->for($task)->create();
        $repositoryEntry = RepositoryEntry::factory()->for($project)->create();
        $uploader = User::factory()->create();

        $file = WorkflowFile::create([
            'project_id' => $project->id,
            'task_id' => $task->id,
            'subtask_id' => $subtask->id,
            'repository_entry_id' => $repositoryEntry->id,
            'uploaded_by' => $uploader->id,
            'original_name' => 'scope-note.pdf',
            'stored_name' => 'scope-note-123.pdf',
            'disk' => 'local',
            'path' => 'workflow-files/2026/06/scope-note-123.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'file_category' => 'evidence',
        ]);

        $this->assertTrue($file->project->is($project));
        $this->assertTrue($file->task->is($task));
        $this->assertTrue($file->subtask->is($subtask));
        $this->assertTrue($file->repositoryEntry->is($repositoryEntry));
        $this->assertTrue($uploader->uploadedWorkflowFiles->contains($file));
    }
    public function test_archive_records_can_be_linked_to_a_project(): void
    {
        $project = Project::factory()->create();
        $admin = User::factory()->create();

        $record = ArchiveRecord::create([
            'project_id' => $project->id,
            'record_type' => 'message',
            'record_id' => 99,
            'archived_by' => $admin->id,
            'archive_reason' => 'Retention window reached.',
            'archived_at' => now(),
            'metadata' => ['source' => 'phase-3-test'],
        ]);

        $this->assertTrue($project->archiveRecords->contains($record));
        $this->assertTrue($record->archivedBy->is($admin));
    }
}

