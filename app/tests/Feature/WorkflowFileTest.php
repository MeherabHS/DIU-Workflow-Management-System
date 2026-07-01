<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\RepositoryEntry;
use App\Models\Subtask;
use App\Models\SubtaskAssignment;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowFile;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WorkflowFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
    }

    public function test_guest_cannot_upload_project_file(): void
    {
        $this->post(route('projects.files.store', Project::factory()->create()), $this->uploadPayload())->assertRedirect('/login');
    }

    public function test_guest_cannot_download_file(): void
    {
        $file = $this->makeWorkflowFile(Project::factory()->create(), $this->makeAdmin());

        $this->get(route('workflow-files.download', $file))->assertRedirect('/login');
    }

    public function test_admin_can_upload_and_download_project_file(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create();

        $this->actingAs($admin)->post(route('projects.files.store', $project), $this->uploadPayload())->assertRedirect();

        $file = WorkflowFile::firstOrFail();
        $this->assertSame($project->id, $file->project_id);
        $this->actingAs($admin)->get(route('workflow-files.download', $file))->assertOk();
    }

    public function test_pm_can_upload_and_download_project_file(): void
    {
        $pm = $this->makePm();
        $project = Project::factory()->create();

        $this->actingAs($pm)->post(route('projects.files.store', $project), $this->uploadPayload())->assertRedirect();
        $this->actingAs($pm)->get(route('workflow-files.download', WorkflowFile::firstOrFail()))->assertOk();
    }

    public function test_assigned_coordinator_can_upload_and_download_under_assigned_project(): void
    {
        $admin = $this->makeAdmin();
        $coordinator = $this->makeCoordinator();
        $project = Project::factory()->create();
        $this->assignCoordinator($project, $coordinator, $admin);

        $this->actingAs($coordinator)->post(route('projects.files.store', $project), $this->uploadPayload())->assertRedirect();
        $this->actingAs($coordinator)->get(route('workflow-files.download', WorkflowFile::firstOrFail()))->assertOk();
    }

    public function test_unassigned_coordinator_cannot_upload_or_download_under_another_project(): void
    {
        $admin = $this->makeAdmin();
        $assigned = $this->makeCoordinator('assigned-file@example.com');
        $blocked = $this->makeCoordinator('blocked-file@example.com');
        $project = Project::factory()->create();
        $this->assignCoordinator($project, $assigned, $admin);
        $file = $this->makeWorkflowFile($project, $admin);

        $this->actingAs($blocked)->post(route('projects.files.store', $project), $this->uploadPayload())->assertForbidden();
        $this->actingAs($blocked)->get(route('workflow-files.download', $file))->assertForbidden();
    }

    public function test_admin_and_assigned_coordinator_can_upload_task_files_but_unassigned_coordinator_cannot(): void
    {
        $admin = $this->makeAdmin();
        $coordinator = $this->makeCoordinator('task-file-coordinator@example.com');
        $blocked = $this->makeCoordinator('task-file-blocked@example.com');
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $this->assignCoordinator($project, $coordinator, $admin);

        $this->actingAs($admin)->post(route('tasks.files.store', $task), $this->uploadPayload(['file' => UploadedFile::fake()->create('admin-task.pdf', 20, 'application/pdf')]))->assertRedirect();
        $this->actingAs($coordinator)->post(route('tasks.files.store', $task), $this->uploadPayload(['file' => UploadedFile::fake()->create('coord-task.pdf', 20, 'application/pdf')]))->assertRedirect();
        $this->actingAs($blocked)->post(route('tasks.files.store', $task), $this->uploadPayload())->assertForbidden();
    }

    public function test_assigned_subordinate_can_upload_evidence_and_download_assigned_subtask_file(): void
    {
        $admin = $this->makeAdmin();
        $subordinate = $this->makeSubordinate();
        $subtask = Subtask::factory()->forTask()->create();
        $this->assignSubtask($subtask, $subordinate, $admin);

        $this->actingAs($subordinate)->post(route('subtasks.files.store', $subtask), $this->uploadPayload())->assertRedirect();

        $file = WorkflowFile::firstOrFail();
        $this->assertSame('evidence', $file->file_category);
        $this->actingAs($subordinate)->get(route('workflow-files.download', $file))->assertOk();
    }

    public function test_unassigned_subordinate_cannot_upload_or_download_another_subtask_file(): void
    {
        $admin = $this->makeAdmin();
        $assigned = $this->makeSubordinate('assigned-sub-file@example.com');
        $blocked = $this->makeSubordinate('blocked-sub-file@example.com');
        $subtask = Subtask::factory()->forTask()->create();
        $this->assignSubtask($subtask, $assigned, $admin);
        $file = $this->makeWorkflowFile($subtask, $admin);

        $this->actingAs($blocked)->post(route('subtasks.files.store', $subtask), $this->uploadPayload())->assertForbidden();
        $this->actingAs($blocked)->get(route('workflow-files.download', $file))->assertForbidden();
    }

    public function test_subordinate_cannot_upload_project_or_repository_file(): void
    {
        $subordinate = $this->makeSubordinate();
        $project = Project::factory()->create();
        $entry = RepositoryEntry::factory()->for($project)->create();

        $this->actingAs($subordinate)->post(route('projects.files.store', $project), $this->uploadPayload())->assertForbidden();
        $this->actingAs($subordinate)->post(route('repository.files.store', $entry), $this->uploadPayload())->assertForbidden();
    }

    public function test_invalid_file_type_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create();

        $this->actingAs($admin)->post(route('projects.files.store', $project), $this->uploadPayload([
            'file' => UploadedFile::fake()->create('unsafe.php', 1, 'application/x-php'),
        ]))->assertSessionHasErrors('file');
    }

    public function test_oversized_file_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create();

        $this->actingAs($admin)->post(route('projects.files.store', $project), $this->uploadPayload([
            'file' => UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf'),
        ]))->assertSessionHasErrors('file');
    }

    public function test_file_metadata_is_stored_on_private_local_disk(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create();

        $this->actingAs($admin)->post(route('projects.files.store', $project), $this->uploadPayload([
            'file' => UploadedFile::fake()->create('Evidence Name.pdf', 25, 'application/pdf'),
            'description' => 'Signed evidence',
        ]))->assertRedirect();

        $file = WorkflowFile::firstOrFail();
        $this->assertSame('Evidence Name.pdf', $file->original_name);
        $this->assertSame('local', $file->disk);
        $this->assertStringStartsWith('workflow-files/', $file->path);
        $this->assertStringNotContainsString('public', $file->path);
        Storage::disk('local')->assertExists($file->path);
    }

    public function test_download_route_returns_authorized_file_response_without_raw_storage_path(): void
    {
        $admin = $this->makeAdmin();
        $file = $this->makeWorkflowFile(Project::factory()->create(), $admin, 'private-report.pdf');

        $response = $this->actingAs($admin)->get(route('workflow-files.download', $file))->assertOk();

        $this->assertStringContainsString('private-report.pdf', $response->headers->get('content-disposition'));
        $this->assertStringNotContainsString($file->path, $response->headers->get('content-disposition'));
    }

    public function test_delete_file_is_authorized_for_admin_and_pm_only(): void
    {
        $admin = $this->makeAdmin();
        $pm = $this->makePm('pm-delete-file@example.com');
        $coordinator = $this->makeCoordinator('coordinator-delete-file@example.com');
        $subordinate = $this->makeSubordinate('subordinate-delete-file@example.com');
        $project = Project::factory()->create();
        $subtask = Subtask::factory()->forTask()->create();
        $this->assignSubtask($subtask, $subordinate, $admin);

        $coordinatorFile = $this->makeWorkflowFile($project, $coordinator, 'coordinator-owned.pdf');
        $subordinateFile = $this->makeWorkflowFile($subtask, $subordinate, 'subordinate-owned.pdf');

        $this->actingAs($coordinator)->delete(route('workflow-files.destroy', $coordinatorFile))->assertForbidden();
        $this->actingAs($subordinate)->delete(route('workflow-files.destroy', $subordinateFile))->assertForbidden();

        $this->actingAs($pm)->delete(route('workflow-files.destroy', $coordinatorFile))->assertRedirect();
        $this->assertSoftDeleted('workflow_files', ['id' => $coordinatorFile->id]);

        $this->actingAs($admin)->delete(route('workflow-files.destroy', $subordinateFile))->assertRedirect();
        $this->assertSoftDeleted('workflow_files', ['id' => $subordinateFile->id]);
    }

    public function test_project_task_subtask_and_my_subtask_detail_pages_include_file_props(): void
    {
        $admin = $this->makeAdmin();
        $subordinate = $this->makeSubordinate();
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $subtask = Subtask::factory()->forTask($task)->create();
        $this->assignSubtask($subtask, $subordinate, $admin);

        $this->actingAs($admin)->get(route('projects.show', $project))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Projects/Show')->has('files')->where('canUploadFile', true)->where('fileUploadUrl', route('projects.files.store', $project)));
        $this->actingAs($admin)->get(route('tasks.show', $task))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Tasks/Show')->has('files')->where('canUploadFile', true)->where('fileUploadUrl', route('tasks.files.store', $task)));
        $this->actingAs($admin)->get(route('subtasks.show', $subtask))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Subtasks/Show')->has('files')->where('canUploadFile', true)->where('fileUploadUrl', route('subtasks.files.store', $subtask)));
        $this->actingAs($subordinate)->get(route('subtasks.mine.show', $subtask))->assertOk()->assertInertia(fn (Assert $page) => $page->component('MySubtasks/Show')->has('files')->where('canUploadFile', true)->where('fileSectionLabel', 'Evidence / Attachments'));
    }

    public function test_project_detail_upload_props_are_role_aware(): void
    {
        $admin = $this->makeAdmin();
        $pm = $this->makePm('pm-props@example.com');
        $coordinator = $this->makeCoordinator('coordinator-props@example.com');
        $subordinate = $this->makeSubordinate('subordinate-props@example.com');
        $project = Project::factory()->create();
        $this->assignCoordinator($project, $coordinator, $admin);

        $this->actingAs($admin)->get(route('projects.show', $project))->assertOk()->assertInertia(fn (Assert $page) => $page->where('canUploadFile', true)->where('fileUploadUrl', route('projects.files.store', $project)));
        $this->actingAs($pm)->get(route('projects.show', $project))->assertOk()->assertInertia(fn (Assert $page) => $page->where('canUploadFile', true)->where('fileUploadUrl', route('projects.files.store', $project)));
        $this->actingAs($coordinator)->get(route('projects.show', $project))->assertOk()->assertInertia(fn (Assert $page) => $page->where('canUploadFile', true)->where('fileUploadUrl', route('projects.files.store', $project)));
        $this->actingAs($subordinate)->get(route('projects.show', $project))->assertForbidden();
    }

    public function test_uploaded_file_appears_in_project_files_prop_with_download_url(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create();

        $this->actingAs($admin)->post(route('projects.files.store', $project), $this->uploadPayload([
            'file' => UploadedFile::fake()->create('visible-proof.pdf', 12, 'application/pdf'),
        ]))->assertRedirect();

        $file = WorkflowFile::firstOrFail();

        $this->actingAs($admin)->get(route('projects.show', $project))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('files.0.original_name', 'visible-proof.pdf')
            ->where('files.0.size_human', '12 KB')
            ->where('files.0.uploaded_by_name', $admin->name)
            ->where('files.0.download_url', route('workflow-files.download', $file))
            ->where('files.0.can_delete', true));
    }

    public function test_compact_attachment_components_match_reference_structure(): void
    {
        $fileList = file_get_contents(resource_path('js/Components/WorkManagement/FileList.tsx'));
        $fileCard = file_get_contents(resource_path('js/Components/WorkManagement/FileCard.tsx'));

        $this->assertStringContainsString('Paperclip', $fileList);
        $this->assertStringContainsString('{title} ({files.length})', $fileList);
        $this->assertStringContainsString('inputRef.current?.click()', $fileList);
        $this->assertStringContainsString('FormData', $fileList);
        $this->assertStringContainsString('forceFormData: true', $fileList);
        $this->assertStringContainsString('No attachments yet', $fileList);
        $this->assertStringNotContainsString('No files uploaded yet', $fileList);
        $this->assertStringContainsString('FileText', $fileCard);
        $this->assertStringContainsString('Download', $fileCard);
        $this->assertStringContainsString('file.original_name', $fileCard);
    }
    public function test_pm_can_create_project_with_initial_attachment(): void
    {
        $pm = $this->makePm('pm-project-attachment@example.com');

        $this->actingAs($pm)->post(route('projects.store'), [
            'title' => 'Project with required docs',
            'description' => 'Includes required documents.',
            'status' => 'planned',
            'priority' => 'high',
            'start_date' => now()->format('Y-m-d'),
            'deadline' => now()->addMonth()->format('Y-m-d'),
            'file' => UploadedFile::fake()->create('project-requirements.pdf', 18, 'application/pdf'),
        ])->assertRedirect();

        $project = Project::query()->where('title', 'Project with required docs')->firstOrFail();
        $file = WorkflowFile::query()->where('project_id', $project->id)->whereNull('task_id')->firstOrFail();

        $this->assertSame('project-requirements.pdf', $file->original_name);
        $this->assertSame('local', $file->disk);
        Storage::disk('local')->assertExists($file->path);
        $this->actingAs($pm)->get(route('workflow-files.download', $file))->assertOk();
    }
    public function test_pm_can_create_task_with_initial_attachment_and_download_it(): void
    {
        $pm = $this->makePm('pm-task-attachment@example.com');
        $project = Project::factory()->create();

        $this->actingAs($pm)->post(route('project.tasks.store', $project), [
            'title' => 'Task with report',
            'description' => 'Includes setup report.',
            'status' => 'pending',
            'priority' => 'high',
            'deadline' => now()->addWeek()->format('Y-m-d'),
            'file' => UploadedFile::fake()->create('requirements-report.pdf', 15, 'application/pdf'),
        ])->assertRedirect();

        $task = Task::query()->where('title', 'Task with report')->firstOrFail();
        $file = WorkflowFile::query()->where('task_id', $task->id)->firstOrFail();

        $this->assertSame('requirements-report.pdf', $file->original_name);
        $this->assertSame('local', $file->disk);
        $this->assertStringStartsWith('workflow-files/', $file->path);
        Storage::disk('local')->assertExists($file->path);

        $this->actingAs($pm)->get(route('workflow-files.download', $file))->assertOk();
    }

    public function test_download_icon_uses_normal_anchor_for_file_response(): void
    {
        $fileCard = file_get_contents(resource_path('js/Components/WorkManagement/FileCard.tsx'));

        $this->assertStringContainsString('<a href={file.download_url}', $fileCard);
        $this->assertStringNotContainsString('<Link href={file.download_url}', $fileCard);
    }
    public function test_repository_detail_page_includes_file_props_when_detail_exists(): void
    {
        $admin = $this->makeAdmin();
        $entry = RepositoryEntry::factory()->create();

        $this->actingAs($admin)->get(route('repository.show', $entry))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Repository/Show')->has('files')->where('canUploadFile', true)->where('fileUploadUrl', route('repository.files.store', $entry)));
    }

    public function test_no_public_storage_direct_url_is_used_for_workflow_files(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create();
        $file = $this->makeWorkflowFile($project, $admin);

        $this->actingAs($admin)->get(route('projects.show', $project))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('files.0.download_url', route('workflow-files.download', $file))
            ->where('files.0.delete_url', route('workflow-files.destroy', $file)));

        $this->assertStringNotContainsString('/storage/', $file->path);
        $this->get('/storage/'.$file->path)->assertStatus(403);
    }

    protected function uploadPayload(array $overrides = []): array
    {
        return $overrides + [
            'file' => UploadedFile::fake()->create('evidence.pdf', 10, 'application/pdf'),
        ];
    }

    protected function makeWorkflowFile(Project|Task|Subtask|RepositoryEntry $context, User $uploader, string $name = 'evidence.pdf'): WorkflowFile
    {
        $path = 'workflow-files/2026/06/'.uniqid('file_', true).'.pdf';
        Storage::disk('local')->put($path, 'phase 9 evidence');

        $columns = match (true) {
            $context instanceof Project => ['project_id' => $context->id],
            $context instanceof Task => ['project_id' => $context->project_id, 'task_id' => $context->id],
            $context instanceof Subtask => ['project_id' => $context->project_id, 'task_id' => $context->task_id, 'subtask_id' => $context->id],
            $context instanceof RepositoryEntry => ['project_id' => $context->project_id, 'repository_entry_id' => $context->id],
        };

        return WorkflowFile::create($columns + [
            'uploaded_by' => $uploader->id,
            'original_name' => $name,
            'stored_name' => basename($path),
            'disk' => 'local',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size' => 16,
            'file_category' => 'attachment',
        ]);
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
        $user = User::factory()->create(['email' => $email ?? 'admin-file@example.com']);
        $user->syncRoles(['Admin']);

        return $user;
    }

    protected function makePm(?string $email = null): User
    {
        $user = User::factory()->create(['email' => $email ?? 'pm-file@example.com']);
        $user->syncRoles(['PM/Manager']);

        return $user;
    }

    protected function makeCoordinator(?string $email = null): User
    {
        $user = User::factory()->create(['email' => $email ?? 'coordinator-file@example.com']);
        $user->syncRoles(['Coordinator']);

        return $user;
    }

    protected function makeSubordinate(?string $email = null): User
    {
        $user = User::factory()->create(['email' => $email ?? 'subordinate-file@example.com']);
        $user->syncRoles(['Subordinate']);

        return $user;
    }
}











