<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\RepositoryEntry;
use App\Models\User;
use App\Models\WorkflowComparisonConfig;
use App\Models\WorkflowComparisonResult;
use App\Models\WorkflowFile;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProjectFinalizeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_finalize_completed_project(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('projects.finalize-to-repository', $project))
            ->assertRedirect();

        $this->assertDatabaseHas('repository_entries', [
            'project_id' => $project->id,
            'status' => 'completed',
        ]);

        $entry = RepositoryEntry::where('project_id', $project->id)->first();
        $this->assertNotNull($entry->finalized_at);
        $this->assertNotNull($entry->finalized_by);
        $this->assertNotNull($entry->final_status_snapshot);
    }

    public function test_pm_can_finalize_completed_project_they_manage(): void
    {
        $pm = $this->makePM();
        $project = Project::factory()->create([
            'status' => 'completed',
            'completed_at' => now(),
            'created_by' => $pm->id,
        ]);

        $this->actingAs($pm)
            ->post(route('projects.finalize-to-repository', $project))
            ->assertRedirect();

        $this->assertDatabaseHas('repository_entries', [
            'project_id' => $project->id,
            'status' => 'completed',
        ]);

        $entry = RepositoryEntry::where('project_id', $project->id)->first();
        $this->assertNotNull($entry->finalized_at);
    }

    public function test_cannot_finalize_incomplete_project(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create(['status' => 'in_progress']);

        $this->actingAs($admin)
            ->post(route('projects.finalize-to-repository', $project))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('repository_entries', [
            'project_id' => $project->id,
        ]);
    }

    public function test_cannot_finalize_same_project_twice(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // First finalization
        $this->actingAs($admin)
            ->post(route('projects.finalize-to-repository', $project))
            ->assertRedirect();

        // Second finalization should fail
        $this->actingAs($admin)
            ->post(route('projects.finalize-to-repository', $project))
            ->assertStatus(409);

        $count = RepositoryEntry::where('project_id', $project->id)
            ->whereNotNull('finalized_at')
            ->count();

        $this->assertEquals(1, $count);
    }

    public function test_coordinator_cannot_finalize_project(): void
    {
        $coordinator = $this->makeCoordinator('coord-finalize@test.com');
        $admin = $this->makeAdmin('admin-finalize-coord@test.com');
        $project = Project::factory()->create([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        $this->assignCoordinator($project, $coordinator, $admin);

        $this->actingAs($coordinator)
            ->post(route('projects.finalize-to-repository', $project))
            ->assertForbidden();
    }

    public function test_subordinate_cannot_finalize_project(): void
    {
        $subordinate = $this->makeSubordinate('sub-finalize@test.com');
        $project = Project::factory()->create([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->actingAs($subordinate)
            ->post(route('projects.finalize-to-repository', $project))
            ->assertForbidden();
    }

    public function test_repository_record_links_to_project(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create([
            'status' => 'completed',
            'completed_at' => now(),
            'title' => 'Test Finalization Project',
        ]);

        $this->actingAs($admin)
            ->post(route('projects.finalize-to-repository', $project))
            ->assertRedirect();

        $entry = RepositoryEntry::where('project_id', $project->id)->first();
        $this->assertNotNull($entry);
        $this->assertEquals($project->id, $entry->project_id);
        $this->assertEquals('Test Finalization Project', $entry->title);
        $this->assertEquals('completed', $entry->status);
    }

    public function test_repository_snapshot_stores_final_summary(): void
    {
        $admin = $this->makeAdmin();
        $coordinator = $this->makeCoordinator('coord-snapshot@test.com');
        $project = Project::factory()->create([
            'status' => 'completed',
            'completed_at' => now(),
            'priority' => 'high',
            'deadline' => now()->addWeek(),
        ]);
        $this->assignCoordinator($project, $coordinator, $admin);

        // Create tasks and subtasks
        $project->tasks()->createMany([
            ['title' => 'Task 1', 'created_by' => $admin->id, 'status' => 'completed', 'approved_at' => now()],
            ['title' => 'Task 2', 'created_by' => $admin->id, 'status' => 'in_progress'],
        ]);
        $project->subtasks()->createMany([
            ['title' => 'Work Item 1', 'created_by' => $admin->id, 'status' => 'completed', 'approved_at' => now()],
        ]);

        // Create comparison config and result
        $config = WorkflowComparisonConfig::create([
            'project_id' => $project->id,
            'task_id' => null,
            'subtask_id' => null,
        ]);
        WorkflowComparisonResult::create([
            'comparison_config_id' => $config->id,
            'status' => 'completed',
            'completion_percentage' => 85.00,
            'summary' => 'Most requirements met',
        ]);

        $this->actingAs($admin)
            ->post(route('projects.finalize-to-repository', $project))
            ->assertRedirect();

        $entry = RepositoryEntry::where('project_id', $project->id)->first();
        $snapshot = $entry->final_status_snapshot;

        $this->assertNotNull($snapshot);
        $this->assertEquals($project->title, $snapshot['project']['title']);
        $this->assertEquals('completed', $snapshot['project']['status']);
        $this->assertEquals('high', $snapshot['project']['priority']);
        $this->assertEquals(2, $snapshot['task_count']);
        $this->assertEquals(1, $snapshot['approved_task_count']);
        $this->assertEquals(1, $snapshot['work_item_count']);
        $this->assertEquals(1, $snapshot['approved_work_item_count']);
        $this->assertEquals('Most requirements met', $snapshot['ai_comparison_summary']);
        $this->assertEquals($coordinator->name, $snapshot['active_coordinator']);
    }

    public function test_finalized_project_shows_repository_link_on_show_page(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Finalize first
        $this->actingAs($admin)
            ->post(route('projects.finalize-to-repository', $project))
            ->assertRedirect();

        // Now check the project show page
        $this->actingAs($admin)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Show')
                ->where('canFinalizeProject', false)
                ->has('alreadyFinalized')
                ->where('alreadyFinalized.route', route('repository.show', RepositoryEntry::where('project_id', $project->id)->first()->id))
            );
    }

    public function test_project_show_shows_finalize_button_for_completed_project(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Show')
                ->where('canFinalizeProject', true)
                ->where('alreadyFinalized', null)
            );
    }

    public function test_project_show_does_not_show_finalize_for_non_completed(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create(['status' => 'in_progress']);

        $this->actingAs($admin)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Show')
                ->where('canFinalizeProject', false)
            );
    }

    public function test_finalize_stores_final_summary(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create([
            'status' => 'completed',
            'completed_at' => now(),
            'title' => 'Summary Test Project',
        ]);

        $this->actingAs($admin)
            ->post(route('projects.finalize-to-repository', $project), [
                'final_summary' => 'Custom summary for this project.',
            ])
            ->assertRedirect();

        $entry = RepositoryEntry::where('project_id', $project->id)->first();
        $this->assertNotNull($entry);
        $this->assertEquals('Custom summary for this project.', $entry->final_summary);
    }

    public function test_finalize_generates_summary_when_none_provided(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create([
            'status' => 'completed',
            'completed_at' => now(),
            'title' => 'Auto Summary Project',
            'priority' => 'medium',
        ]);

        $this->actingAs($admin)
            ->post(route('projects.finalize-to-repository', $project))
            ->assertRedirect();

        $entry = RepositoryEntry::where('project_id', $project->id)->first();
        $this->assertNotNull($entry);
        $this->assertStringContainsString('Auto Summary Project', $entry->final_summary);
        $this->assertStringContainsString('completed', $entry->final_summary);
    }

    public function test_finalize_links_project_workflow_files(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Create project-level workflow files (not yet linked to repository)
        $file1 = WorkflowFile::create([
            'project_id' => $project->id,
            'uploaded_by' => $admin->id,
            'original_name' => 'doc1.pdf',
            'stored_name' => Str::uuid().'.pdf',
            'disk' => 'local',
            'path' => 'test/doc1.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1000,
            'file_category' => 'attachment',
        ]);
        $file2 = WorkflowFile::create([
            'project_id' => $project->id,
            'uploaded_by' => $admin->id,
            'original_name' => 'doc2.xlsx',
            'stored_name' => Str::uuid().'.xlsx',
            'disk' => 'local',
            'path' => 'test/doc2.xlsx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'size' => 2000,
            'file_category' => 'evidence',
        ]);

        $this->actingAs($admin)
            ->post(route('projects.finalize-to-repository', $project))
            ->assertRedirect();

        $entry = RepositoryEntry::where('project_id', $project->id)->first();
        $this->assertNotNull($entry);

        // Both files should now be linked to the repository entry
        $this->assertDatabaseHas('workflow_files', [
            'id' => $file1->id,
            'repository_entry_id' => $entry->id,
        ]);
        $this->assertDatabaseHas('workflow_files', [
            'id' => $file2->id,
            'repository_entry_id' => $entry->id,
        ]);

        // Verify via relationship
        $this->assertEquals(2, $entry->files()->count());
    }

    public function test_finalize_does_not_duplicate_physical_files(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        WorkflowFile::create([
            'project_id' => $project->id,
            'uploaded_by' => $admin->id,
            'original_name' => 'unique.pdf',
            'stored_name' => 'unique-stored.pdf',
            'disk' => 'local',
            'path' => 'test/unique.pdf',
            'mime_type' => 'application/pdf',
            'size' => 500,
            'file_category' => 'attachment',
        ]);

        $this->actingAs($admin)
            ->post(route('projects.finalize-to-repository', $project))
            ->assertRedirect();

        // Should still be exactly 1 workflow file record (updated, not duplicated)
        $this->assertDatabaseCount('workflow_files', 1);
        $this->assertDatabaseHas('workflow_files', ['original_name' => 'unique.pdf']);
    }

    public function test_finalize_includes_review_summary_in_snapshot(): void
    {
        $admin = $this->makeAdmin();
        $project = Project::factory()->create([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Create files in different categories
        WorkflowFile::create([
            'project_id' => $project->id,
            'uploaded_by' => $admin->id,
            'original_name' => 'report.pdf',
            'stored_name' => Str::uuid().'.pdf',
            'disk' => 'local',
            'path' => 'test/report.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1000,
            'file_category' => 'report',
        ]);
        WorkflowFile::create([
            'project_id' => $project->id,
            'uploaded_by' => $admin->id,
            'original_name' => 'evidence.png',
            'stored_name' => Str::uuid().'.png',
            'disk' => 'local',
            'path' => 'test/evidence.png',
            'mime_type' => 'image/png',
            'size' => 500,
            'file_category' => 'evidence',
        ]);
        WorkflowFile::create([
            'project_id' => $project->id,
            'uploaded_by' => $admin->id,
            'original_name' => 'notes.docx',
            'stored_name' => Str::uuid().'.docx',
            'disk' => 'local',
            'path' => 'test/notes.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size' => 300,
            'file_category' => 'evidence',
        ]);

        $this->actingAs($admin)
            ->post(route('projects.finalize-to-repository', $project))
            ->assertRedirect();

        $entry = RepositoryEntry::where('project_id', $project->id)->first();
        $snapshot = $entry->final_status_snapshot;

        $this->assertNotNull($snapshot);
        $this->assertArrayHasKey('review_summary', $snapshot);
        $this->assertEquals(3, $snapshot['review_summary']['total_files']);
        $this->assertEquals(1, $snapshot['review_summary']['files_by_category']['report']);
        $this->assertEquals(2, $snapshot['review_summary']['files_by_category']['evidence']);
        $this->assertNotNull($snapshot['review_summary']['latest_file']);
        $this->assertArrayHasKey('name', $snapshot['review_summary']['latest_file']);
        $this->assertArrayHasKey('category', $snapshot['review_summary']['latest_file']);
        $this->assertArrayHasKey('uploaded_at', $snapshot['review_summary']['latest_file']);
    }

    // Helpers

    protected function assignCoordinator(Project $project, User $coordinator, User $assigner): void
    {
        ProjectAssignment::create([
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

    protected function makePM(?string $email = null): User
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
