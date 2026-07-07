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
use App\Services\WorkflowFileService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
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

    public function test_assigned_coordinator_can_download_pm_uploaded_project_requirement_file(): void
    {
        $admin = $this->makeAdmin('admin-pm-project-file-access@example.com');
        $pm = $this->makePm('pm-project-file-access@example.com');
        $coordinator = $this->makeCoordinator('coord-project-file-access@example.com');
        $project = Project::factory()->create();
        $this->assignCoordinator($project, $coordinator, $admin);

        $file = $this->makeWorkflowFile($project, $pm, 'pm-requirement.pdf', 'requirement');

        $this->actingAs($coordinator)->get(route('workflow-files.download', $file))->assertOk();
    }

    public function test_assigned_coordinator_can_download_pm_uploaded_task_file_under_assigned_project(): void
    {
        $admin = $this->makeAdmin('admin-pm-task-file-access@example.com');
        $pm = $this->makePm('pm-task-file-access@example.com');
        $coordinator = $this->makeCoordinator('coord-task-file-access@example.com');
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $this->assignCoordinator($project, $coordinator, $admin);

        $file = $this->makeWorkflowFile($task, $pm, 'pm-task-instructions.pdf', 'requirement');

        $this->actingAs($coordinator)->get(route('workflow-files.download', $file))->assertOk();
    }

    public function test_coordinator_cannot_download_pm_uploaded_file_from_unassigned_project(): void
    {
        $pm = $this->makePm('pm-unassigned-file-access@example.com');
        $coordinator = $this->makeCoordinator('coord-unassigned-file-access@example.com');
        $project = Project::factory()->create();

        $file = $this->makeWorkflowFile($project, $pm, 'pm-unassigned-requirement.pdf', 'requirement');

        $this->actingAs($coordinator)->get(route('workflow-files.download', $file))->assertForbidden();
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
            'file' => UploadedFile::fake()->create('large.pdf', 102401, 'application/pdf'),
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

    public function test_assigned_coordinator_can_delete_own_deliverable_follow_up_evidence_and_attachment_files(): void
    {
        $admin = $this->makeAdmin();
        $coordinator = $this->makeCoordinator('coordinator-delete-own-files@example.com');
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $subtask = Subtask::factory()->for($project)->for($task)->create();
        $this->assignCoordinator($project, $coordinator, $admin);

        foreach ([
            [$project, 'coordinator-deliverable.pdf', 'deliverable'],
            [$project, 'coordinator-follow-up.pdf', 'follow_up'],
            [$task, 'coordinator-attachment.pdf', 'attachment'],
            [$subtask, 'coordinator-evidence.pdf', 'evidence'],
            [$project, 'coordinator-other.pdf', 'other'],
        ] as [$context, $name, $category]) {
            $file = $this->makeWorkflowFile($context, $coordinator, $name, $category);

            $this->actingAs($coordinator)->delete(route('workflow-files.destroy', $file))->assertRedirect();
            $this->assertSoftDeleted('workflow_files', ['id' => $file->id]);
            Storage::disk('local')->assertMissing($file->path);
        }
    }

    public function test_coordinator_cannot_delete_pm_requirement_or_another_users_file_or_unassigned_project_file(): void
    {
        $admin = $this->makeAdmin('admin-delete-boundary@example.com');
        $pm = $this->makePm('pm-delete-boundary@example.com');
        $coordinator = $this->makeCoordinator('coordinator-delete-boundary@example.com');
        $otherCoordinator = $this->makeCoordinator('other-coordinator-delete-boundary@example.com');
        $assignedProject = Project::factory()->create();
        $unassignedProject = Project::factory()->create();
        $this->assignCoordinator($assignedProject, $coordinator, $admin);

        $requirement = $this->makeWorkflowFile($assignedProject, $pm, 'pm-requirement.pdf', 'requirement');
        $otherUserFile = $this->makeWorkflowFile($assignedProject, $otherCoordinator, 'other-user-deliverable.pdf', 'deliverable');
        $unassignedFile = $this->makeWorkflowFile($unassignedProject, $coordinator, 'unassigned-deliverable.pdf', 'deliverable');

        $this->actingAs($coordinator)->delete(route('workflow-files.destroy', $requirement))->assertForbidden();
        $this->actingAs($coordinator)->delete(route('workflow-files.destroy', $otherUserFile))->assertForbidden();
        $this->actingAs($coordinator)->delete(route('workflow-files.destroy', $unassignedFile))->assertForbidden();

        $this->assertNotSoftDeleted('workflow_files', ['id' => $requirement->id]);
        $this->assertNotSoftDeleted('workflow_files', ['id' => $otherUserFile->id]);
        $this->assertNotSoftDeleted('workflow_files', ['id' => $unassignedFile->id]);
    }

    public function test_coordinator_cannot_delete_own_file_when_project_is_locked_or_repository_finalized(): void
    {
        $admin = $this->makeAdmin('admin-delete-locked@example.com');
        $coordinator = $this->makeCoordinator('coordinator-delete-locked@example.com');
        $completedProject = Project::factory()->create(['status' => 'completed']);
        $activeProject = Project::factory()->create();
        $repositoryEntry = RepositoryEntry::factory()->for($activeProject)->create();
        $this->assignCoordinator($completedProject, $coordinator, $admin);
        $this->assignCoordinator($activeProject, $coordinator, $admin);

        $lockedFile = $this->makeWorkflowFile($completedProject, $coordinator, 'locked-deliverable.pdf', 'deliverable');
        $repositoryFile = $this->makeWorkflowFile($repositoryEntry, $coordinator, 'repository-attachment.pdf', 'attachment');

        $this->actingAs($coordinator)->delete(route('workflow-files.destroy', $lockedFile))->assertForbidden();
        $this->actingAs($coordinator)->delete(route('workflow-files.destroy', $repositoryFile))->assertForbidden();
    }

    public function test_admin_and_pm_delete_behavior_still_works_and_subordinate_cannot_delete_project_file(): void
    {
        $admin = $this->makeAdmin('admin-delete-file@example.com');
        $pm = $this->makePm('pm-delete-file@example.com');
        $subordinate = $this->makeSubordinate('subordinate-delete-file@example.com');
        $project = Project::factory()->create();

        $pmFile = $this->makeWorkflowFile($project, $pm, 'pm-owned.pdf', 'requirement');
        $adminFile = $this->makeWorkflowFile($project, $admin, 'admin-owned.pdf', 'attachment');
        $subordinateProjectFile = $this->makeWorkflowFile($project, $subordinate, 'sub-project-file.pdf', 'evidence');

        $this->actingAs($subordinate)->delete(route('workflow-files.destroy', $subordinateProjectFile))->assertForbidden();
        $this->assertNotSoftDeleted('workflow_files', ['id' => $subordinateProjectFile->id]);

        $this->actingAs($pm)->delete(route('workflow-files.destroy', $adminFile))->assertRedirect();
        $this->assertSoftDeleted('workflow_files', ['id' => $adminFile->id]);

        $this->actingAs($admin)->delete(route('workflow-files.destroy', $pmFile))->assertRedirect();
        $this->assertSoftDeleted('workflow_files', ['id' => $pmFile->id]);
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


    public function test_project_file_payload_marks_coordinator_owned_files_deletable_only_when_allowed(): void
    {
        $admin = $this->makeAdmin('admin-file-payload-delete@example.com');
        $pm = $this->makePm('pm-file-payload-delete@example.com');
        $coordinator = $this->makeCoordinator('coord-file-payload-delete@example.com');
        $project = Project::factory()->create();
        $this->assignCoordinator($project, $coordinator, $admin);

        $ownDeliverable = $this->makeWorkflowFile($project, $coordinator, 'aaa-own-deliverable.pdf', 'deliverable');
        $pmRequirement = $this->makeWorkflowFile($project, $pm, 'zzz-pm-requirement.pdf', 'requirement');

        $this->actingAs($coordinator)->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('files.1.id', $ownDeliverable->id)
                ->where('files.1.can_delete', true)
                ->where('files.1.canDelete', true)
                ->where('files.1.delete_url', route('workflow-files.destroy', $ownDeliverable))
                ->where('files.0.id', $pmRequirement->id)
                ->where('files.0.can_delete', false)
                ->where('files.0.canDelete', false)
                ->where('files.0.delete_url', null));
    }
    public function test_project_file_list_returns_latest_hundred_files(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create();

        for ($i = 1; $i <= 105; $i++) {
            WorkflowFile::create([
                'project_id' => $project->id,
                'uploaded_by' => $admin->id,
                'original_name' => sprintf('proof-%03d.pdf', $i),
                'stored_name' => sprintf('proof-%03d.pdf', $i),
                'disk' => 'local',
                'path' => sprintf('workflow-files/proof-%03d.pdf', $i),
                'mime_type' => 'application/pdf',
                'size' => 1024,
                'file_category' => 'attachment',
                'created_at' => now()->subMinutes(106 - $i),
                'updated_at' => now()->subMinutes(106 - $i),
            ]);
        }

        $this->actingAs($admin)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('files', 100)
                ->where('files.0.original_name', 'proof-105.pdf')
                ->where('files.99.original_name', 'proof-006.pdf'));
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
        $this->assertStringContainsString('file.can_delete || file.canDelete', $fileCard);
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


    public function test_workflow_file_service_exposes_current_allowed_extensions_and_limits(): void
    {
        $service = app(WorkflowFileService::class);

        $this->assertSame([
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'png', 'jpg', 'jpeg', 'webp', 'txt', 'csv',
        ], $service->allowedExtensions());
        $this->assertSame(102400, $service->maxUploadKilobytes());
        $this->assertSame(100, $service->maxUploadMegabytes());
        $this->assertSame('.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.png,.jpg,.jpeg,.webp,.txt,.csv', $service->acceptAttribute());
    }

    public function test_normal_project_task_subtask_and_repository_file_endpoints_create_workflow_files(): void
    {
        $admin = $this->makeAdmin('admin-all-endpoints@example.com');
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $subtask = Subtask::factory()->for($project)->for($task)->create();
        $entry = RepositoryEntry::factory()->for($project)->create();

        $this->actingAs($admin)->post(route('projects.files.store', $project), $this->uploadPayload(['file' => UploadedFile::fake()->create('project.pdf', 10, 'application/pdf')]))->assertRedirect();
        $this->actingAs($admin)->post(route('tasks.files.store', $task), $this->uploadPayload(['file' => UploadedFile::fake()->create('task.pdf', 10, 'application/pdf')]))->assertRedirect();
        $this->actingAs($admin)->post(route('subtasks.files.store', $subtask), $this->uploadPayload(['file' => UploadedFile::fake()->create('subtask.pdf', 10, 'application/pdf')]))->assertRedirect();
        $this->actingAs($admin)->post(route('repository.files.store', $entry), $this->uploadPayload(['file' => UploadedFile::fake()->create('repository.pdf', 10, 'application/pdf')]))->assertRedirect();

        $this->assertDatabaseHas('workflow_files', ['project_id' => $project->id, 'task_id' => null, 'subtask_id' => null, 'repository_entry_id' => null, 'original_name' => 'project.pdf']);
        $this->assertDatabaseHas('workflow_files', ['task_id' => $task->id, 'original_name' => 'task.pdf']);
        $this->assertDatabaseHas('workflow_files', ['subtask_id' => $subtask->id, 'original_name' => 'subtask.pdf']);
        $this->assertDatabaseHas('workflow_files', ['repository_entry_id' => $entry->id, 'original_name' => 'repository.pdf']);
    }

    public function test_allowed_workflow_file_types_are_accepted(): void
    {
        $admin = $this->makeAdmin('admin-valid-types@example.com');
        $project = Project::factory()->create();

        $files = [
            ['valid.pdf', 'application/pdf'],
            ['valid.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            ['valid.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            ['valid.pptx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            ['valid.zip', 'application/zip'],
            ['valid.png', 'image/png'],
            ['valid.jpg', 'image/jpeg'],
            ['valid.jpeg', 'image/jpeg'],
            ['valid.webp', 'image/webp'],
            ['valid.txt', 'text/plain'],
            ['valid.csv', 'text/csv'],
        ];

        foreach ($files as [$name, $mime]) {
            $this->actingAs($admin)
                ->post(route('projects.files.store', $project), $this->uploadPayload([
                    'file' => UploadedFile::fake()->create($name, 10, $mime),
                ]))
                ->assertSessionHasNoErrors()
                ->assertRedirect();

            Cache::flush();
        }

        foreach ($files as [$name]) {
            $this->assertDatabaseHas('workflow_files', ['original_name' => $name]);
        }
    }

    public function test_dangerous_workflow_file_types_are_rejected(): void
    {
        $admin = $this->makeAdmin('admin-dangerous-types@example.com');
        $project = Project::factory()->create();

        $files = [
            ['unsafe.php', 'application/x-php'],
            ['unsafe.js', 'application/javascript'],
            ['unsafe.html', 'text/html'],
            ['unsafe.svg', 'image/svg+xml'],
            ['unsafe.exe', 'application/vnd.microsoft.portable-executable'],
            ['unsafe.bat', 'application/x-msdos-program'],
            ['unsafe.cmd', 'text/plain'],
            ['unsafe.sh', 'application/x-sh'],
        ];

        foreach ($files as [$name, $mime]) {
            $this->actingAs($admin)
                ->post(route('projects.files.store', $project), $this->uploadPayload([
                    'file' => UploadedFile::fake()->create($name, 1, $mime),
                ]))
                ->assertSessionHasErrors('file');
        }
    }


    public function test_all_upload_paths_accept_valid_file_under_one_hundred_mb(): void
    {
        $admin = $this->makeAdmin('admin-under-100mb@example.com');
        $subordinate = $this->makeSubordinate('sub-under-100mb@example.com');
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create();
        $subtask = Subtask::factory()->for($project)->for($task)->create();
        $entry = RepositoryEntry::factory()->for($project)->create();
        $this->assignSubtask($subtask, $subordinate, $admin);

        $this->actingAs($admin)->post(route('projects.store'), [
            'title' => 'Project Under 100MB',
            'status' => 'planned',
            'file' => UploadedFile::fake()->create('project-under-limit.pdf', 99000, 'application/pdf'),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->actingAs($admin)->post(route('project.tasks.store', $project), [
            'title' => 'Task Under 100MB',
            'status' => 'pending',
            'file' => UploadedFile::fake()->create('task-initial-under-limit.pdf', 99000, 'application/pdf'),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->actingAs($admin)->post(route('projects.files.store', $project), $this->uploadPayload([
            'file' => UploadedFile::fake()->create('project-endpoint-under-limit.pdf', 99000, 'application/pdf'),
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $this->actingAs($admin)->post(route('tasks.files.store', $task), $this->uploadPayload([
            'file' => UploadedFile::fake()->create('task-endpoint-under-limit.pdf', 99000, 'application/pdf'),
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $this->actingAs($subordinate)->post(route('subtasks.files.store', $subtask), $this->uploadPayload([
            'file' => UploadedFile::fake()->create('work-item-under-limit.pdf', 99000, 'application/pdf'),
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $this->actingAs($admin)->post(route('repository.files.store', $entry), $this->uploadPayload([
            'file' => UploadedFile::fake()->create('repository-under-limit.pdf', 99000, 'application/pdf'),
        ]))->assertSessionHasNoErrors()->assertRedirect();

        foreach ([
            'project-under-limit.pdf',
            'task-initial-under-limit.pdf',
            'project-endpoint-under-limit.pdf',
            'task-endpoint-under-limit.pdf',
            'work-item-under-limit.pdf',
            'repository-under-limit.pdf',
        ] as $name) {
            $this->assertDatabaseHas('workflow_files', ['original_name' => $name]);
        }
    }
    public function test_repository_standalone_file_access_remains_admin_pm_only(): void
    {
        $admin = $this->makeAdmin('admin-standalone-file@example.com');
        $pm = $this->makePm('pm-standalone-file@example.com');
        $coordinator = $this->makeCoordinator('coord-standalone-file@example.com');
        $subordinate = $this->makeSubordinate('sub-standalone-file@example.com');
        $entry = RepositoryEntry::factory()->create(['project_id' => null]);
        $file = $this->makeWorkflowFile($entry, $admin, 'standalone-repository.pdf');

        $this->actingAs($admin)->get(route('workflow-files.download', $file))->assertOk();
        $this->actingAs($pm)->get(route('workflow-files.download', $file))->assertOk();
        $this->actingAs($coordinator)->get(route('workflow-files.download', $file))->assertForbidden();
        $this->actingAs($subordinate)->get(route('workflow-files.download', $file))->assertForbidden();
    }

    public function test_file_upload_accept_props_match_workflow_file_service(): void
    {
        $admin = $this->makeAdmin('admin-accept-props@example.com');
        $project = Project::factory()->create();
        $expected = app(WorkflowFileService::class)->acceptAttribute();

        $this->actingAs($admin)->get(route('projects.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('allowedFileTypes', $expected)
            ->where('maxFileSizeMb', 100));

        $this->actingAs($admin)->get(route('project.tasks.create', $project))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('allowedFileTypes', $expected)
            ->where('maxFileSizeMb', 100));
    }
    public function test_project_file_upload_uses_role_aware_ai_comparison_categories(): void
    {
        $admin = $this->makeAdmin('admin-ai-file-categories@example.com');
        $coordinator = $this->makeCoordinator('coord-ai-file-categories@example.com');
        $project = Project::factory()->create(['created_by' => $admin->id]);
        $this->assignCoordinator($project, $coordinator, $admin);

        foreach (['requirement', 'attachment', 'other'] as $category) {
            $this->actingAs($admin)->post(route('projects.files.store', $project), $this->uploadPayload([
                'file' => UploadedFile::fake()->create('admin-'.$category.'.txt', 1, 'text/plain'),
                'file_category' => $category,
            ]))->assertRedirect();

            $this->assertDatabaseHas('workflow_files', [
                'project_id' => $project->id,
                'original_name' => 'admin-'.$category.'.txt',
                'file_category' => $category,
            ]);
        }

        foreach (['follow_up', 'deliverable', 'evidence', 'attachment', 'other'] as $category) {
            $this->actingAs($coordinator)->post(route('projects.files.store', $project), $this->uploadPayload([
                'file' => UploadedFile::fake()->create('coord-'.$category.'.txt', 1, 'text/plain'),
                'file_category' => $category,
            ]))->assertRedirect();

            $this->assertDatabaseHas('workflow_files', [
                'project_id' => $project->id,
                'original_name' => 'coord-'.$category.'.txt',
                'file_category' => $category,
            ]);
        }

        $this->actingAs($coordinator)->post(route('projects.files.store', $project), $this->uploadPayload([
            'file' => UploadedFile::fake()->create('coord-requirement.txt', 1, 'text/plain'),
            'file_category' => 'requirement',
        ]))->assertSessionHasErrors('file_category');
    }

    public function test_attachment_components_expose_category_selector_badge_and_ai_helper_text(): void
    {
        $fileList = file_get_contents(resource_path('js/Components/WorkManagement/FileList.tsx'));
        $fileCard = file_get_contents(resource_path('js/Components/WorkManagement/FileCard.tsx'));
        $projectShow = file_get_contents(resource_path('js/Pages/Projects/Show.tsx'));
        $progressComparison = file_get_contents(resource_path('js/Components/WorkManagement/ProgressComparison.tsx'));

        $this->assertStringContainsString("formData.append('file_category', fileCategory)", $fileList);
        $this->assertStringContainsString('File Category', $fileList);
        $this->assertStringContainsString('Requirement', $fileList);
        $this->assertStringContainsString('defaultFileCategory', $fileList);
        $this->assertStringContainsString('categoryOptions.map', $fileList);
        $this->assertStringContainsString('AI comparison requires at least one Requirement file and one Deliverable/Evidence file.', $fileList);
        $this->assertStringContainsString('categoryLabel(file.file_category)', $fileCard);
        $this->assertStringContainsString('Follow-up', $fileCard);
        $this->assertStringContainsString('Attachment', $fileCard);
        $this->assertStringContainsString('RequirementDeliverableComparison', $projectShow);
        $this->assertStringNotContainsString('<ProgressComparison result={comparisonResult} />', $projectShow);
        $this->assertStringNotContainsString("['Project setup', 'Task planning', 'Delivery review']", $projectShow);
        $this->assertStringContainsString('Run AI comparison after uploading requirement and deliverable/evidence files.', $progressComparison);
    }
    public function test_project_detail_file_category_props_are_role_aware(): void
    {
        $admin = $this->makeAdmin('admin-category-props@example.com');
        $coordinator = $this->makeCoordinator('coord-category-props@example.com');
        $project = Project::factory()->create(['created_by' => $admin->id]);
        $this->assignCoordinator($project, $coordinator, $admin);

        $this->actingAs($admin)->get(route('projects.show', $project))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('defaultFileCategory', 'requirement')
            ->where('fileCategoryOptions.0.value', 'requirement')
            ->where('fileCategoryOptions.1.value', 'attachment')
            ->where('fileUploadHelperText', 'Upload the project requirement or instruction file. Coordinator follow-up/evidence files will be compared against this requirement.'));

        $this->actingAs($coordinator)->get(route('projects.show', $project))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('defaultFileCategory', 'follow_up')
            ->where('fileCategoryOptions.0.value', 'follow_up')
            ->where('fileCategoryOptions.1.value', 'deliverable')
            ->where('fileCategoryOptions.2.value', 'evidence')
            ->where('fileUploadHelperText', 'Upload follow-up, deliverable, or evidence files after completing assigned work. PM/Admin will use these for AI comparison.'));
    }
    protected function uploadPayload(array $overrides = []): array
    {
        return $overrides + [
            'file' => UploadedFile::fake()->create('evidence.pdf', 10, 'application/pdf'),
        ];
    }

    protected function makeWorkflowFile(Project|Task|Subtask|RepositoryEntry $context, User $uploader, string $name = 'evidence.pdf', string $category = 'attachment'): WorkflowFile
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
            'file_category' => $category,
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






























