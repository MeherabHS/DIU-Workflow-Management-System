<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\WorkflowComparisonConfig;
use App\Models\WorkflowComparisonResult;
use App\Models\WorkflowDeliverable;
use App\Models\WorkflowRequirement;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WorkflowComparisonRelationshipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_workflow_comparison_relationships_use_comparison_config_id(): void
    {
        $project = Project::factory()->create();
        $config = WorkflowComparisonConfig::create([
            'project_id' => $project->id,
            'enabled' => true,
        ]);

        WorkflowRequirement::create([
            'comparison_config_id' => $config->id,
            'requirement_text' => 'Requirement A',
        ]);
        WorkflowDeliverable::create([
            'comparison_config_id' => $config->id,
            'deliverable_text' => 'Deliverable A',
        ]);
        WorkflowComparisonResult::create([
            'comparison_config_id' => $config->id,
            'status' => 'completed',
            'completion_percentage' => 100,
            'summary' => 'All requirements met.',
            'matched_items' => [[
                'requirement' => 'Requirement A',
                'status' => 'completed',
                'matched_deliverable' => 'Deliverable A',
                'notes' => 'Matched cleanly.',
            ]],
        ]);

        $this->assertSame('Requirement A', $config->requirements()->firstOrFail()->requirement_text);
        $this->assertSame('Deliverable A', $config->deliverables()->firstOrFail()->deliverable_text);
        $this->assertSame('All requirements met.', $config->results()->latest()->firstOrFail()->summary);
    }

    public function test_project_show_loads_when_workflow_comparison_result_exists(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['Admin']);

        $project = Project::factory()->create();
        $config = WorkflowComparisonConfig::create([
            'project_id' => $project->id,
            'enabled' => true,
        ]);
        WorkflowComparisonResult::create([
            'comparison_config_id' => $config->id,
            'status' => 'completed',
            'completion_percentage' => 100,
            'summary' => 'All requirements met.',
            'matched_items' => [],
        ]);

        $this->actingAs($admin)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Projects/Show')
                ->where('comparisonResult.status', 'completed')
                ->where('comparisonResult.summary', 'All requirements met.')
            );
    }

    public function test_project_show_loads_when_no_workflow_comparison_result_exists(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['Admin']);

        $project = Project::factory()->create();

        $this->actingAs($admin)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Projects/Show')
                ->where('comparisonResult', null)
            );
    }
}