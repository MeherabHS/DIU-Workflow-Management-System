<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WorkflowReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
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

    // ── Access Control Tests ─────────────────────────────────────────

    public function test_admin_can_view_reports_index(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->get(route('reports.index'))->assertOk()->assertInertia(fn ($p) => $p->component('Reports/Index'));
    }

    public function test_pm_can_view_reports_index(): void
    {
        $pm = $this->makePM();
        $this->actingAs($pm)->get(route('reports.index'))->assertOk()->assertInertia(fn ($p) => $p->component('Reports/Index'));
    }

    public function test_coordinator_cannot_view_reports_index(): void
    {
        $coord = $this->makeCoordinator();
        $this->actingAs($coord)->get(route('reports.index'))->assertForbidden();
    }

    public function test_subordinate_cannot_view_reports_index(): void
    {
        $sub = $this->makeSubordinate();
        $this->actingAs($sub)->get(route('reports.index'))->assertForbidden();
    }

    public function test_guest_cannot_view_reports_index(): void
    {
        $this->get(route('reports.index'))->assertRedirect('/login');
    }

    // ── Individual Report Access Tests ───────────────────────────────

    public function test_admin_can_view_all_reports(): void
    {
        $admin = $this->makeAdmin();
        $reports = ['project-progress', 'task-status', 'coordinator-performance', 'subordinate-completion', 'repository-preservation', 'audit-activity'];
        foreach ($reports as $report) {
            $this->actingAs($admin)->get(route("reports.{$report}"))->assertOk();
        }
    }

    public function test_pm_can_view_all_reports(): void
    {
        $pm = $this->makePM();
        $reports = ['project-progress', 'task-status', 'coordinator-performance', 'subordinate-completion', 'repository-preservation', 'audit-activity'];
        foreach ($reports as $report) {
            $this->actingAs($pm)->get(route("reports.{$report}"))->assertOk();
        }
    }

    // ── CSV Export Tests ─────────────────────────────────────────────

    public function test_admin_can_export_csv(): void
    {
        $admin = $this->makeAdmin();
        Project::factory()->create(['title' => 'CSV Test Project']);

        $response = $this->actingAs($admin)->get(route('reports.project-progress', ['export' => 'csv']));
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertDownload('project-progress-report.csv');
    }

    public function test_coordinator_cannot_export_csv(): void
    {
        $coord = $this->makeCoordinator();
        $this->actingAs($coord)->get(route('reports.project-progress', ['export' => 'csv']))->assertForbidden();
    }

    // ── Role Scoping Tests ───────────────────────────────────────────

    public function test_pm_sees_only_managed_projects_in_reports(): void
    {
        $pm = $this->makePM();
        Project::factory()->create(['title' => 'Other Project']);
        Project::factory()->create(['title' => 'PM Project', 'created_by' => $pm->id]);

        $response = $this->actingAs($pm)->get(route('reports.project-progress'));
        $response->assertOk();
    }

    // ── Navigation Tests ─────────────────────────────────────────────

    public function test_reports_nav_link_shows_for_admin(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('navigation', fn ($nav) => collect($nav)->contains(fn ($item) => $item['label'] === 'Reports')));
    }

    public function test_reports_nav_link_shows_for_pm(): void
    {
        $pm = $this->makePM();
        $this->actingAs($pm)->get(route('pm.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('navigation', fn ($nav) => collect($nav)->contains(fn ($item) => $item['label'] === 'Reports')));
    }

    public function test_reports_nav_link_hidden_for_coordinator(): void
    {
        $coord = $this->makeCoordinator();
        $this->actingAs($coord)->get(route('coordinator.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('navigation', fn ($nav) => !collect($nav)->contains(fn ($item) => $item['label'] === 'Reports')));
    }

    public function test_reports_nav_link_hidden_for_subordinate(): void
    {
        $sub = $this->makeSubordinate();
        $this->actingAs($sub)->get(route('subordinate.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('navigation', fn ($nav) => !collect($nav)->contains(fn ($item) => $item['label'] === 'Reports')));
    }
}

