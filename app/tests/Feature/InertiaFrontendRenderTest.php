<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InertiaFrontendRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_is_redirected_from_protected_pages(): void
    {
        $this->get('/projects')->assertRedirect('/login');
        $this->get('/my-projects')->assertRedirect('/login');
        $this->get('/my-subtasks')->assertRedirect('/login');
    }

    public function test_admin_can_access_projects_and_gets_projects_index_inertia_page(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['Admin']);

        $this->actingAs($admin)
            ->get('/projects')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Index')
                ->where('pageTitle', 'Projects'));
    }

    public function test_coordinator_can_access_my_projects(): void
    {
        $coordinator = User::factory()->create();
        $coordinator->syncRoles(['Coordinator']);

        $this->actingAs($coordinator)
            ->get('/my-projects')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Mine')
                ->where('pageTitle', 'My Assigned Projects'));
    }

    public function test_subordinate_can_access_my_subtasks(): void
    {
        $subordinate = User::factory()->create();
        $subordinate->syncRoles(['Subordinate']);

        $this->actingAs($subordinate)
            ->get('/my-subtasks')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('MySubtasks/Index')
                ->where('pageTitle', 'My Work Items'));
    }
}
