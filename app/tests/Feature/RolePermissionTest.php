<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_access_admin_dashboard(): void
    {
        $user = User::factory()->create();
        $user->syncRoles(['Admin']);

        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertOk();
    }

    public function test_pm_manager_can_access_pm_dashboard(): void
    {
        $user = User::factory()->create();
        $user->syncRoles(['PM/Manager']);

        $this->actingAs($user)
            ->get('/pm/dashboard')
            ->assertOk();
    }

    public function test_coordinator_can_access_coordinator_dashboard(): void
    {
        $user = User::factory()->create();
        $user->syncRoles(['Coordinator']);

        $this->actingAs($user)
            ->get('/coordinator/dashboard')
            ->assertOk();
    }

    public function test_subordinate_can_access_subordinate_dashboard(): void
    {
        $user = User::factory()->create();
        $user->syncRoles(['Subordinate']);

        $this->actingAs($user)
            ->get('/subordinate/dashboard')
            ->assertOk();
    }

    public function test_pm_manager_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->create();
        $user->syncRoles(['PM/Manager']);

        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_coordinator_cannot_access_pm_dashboard(): void
    {
        $user = User::factory()->create();
        $user->syncRoles(['Coordinator']);

        $this->actingAs($user)
            ->get('/pm/dashboard')
            ->assertForbidden();
    }

    public function test_subordinate_cannot_access_coordinator_dashboard(): void
    {
        $user = User::factory()->create();
        $user->syncRoles(['Subordinate']);

        $this->actingAs($user)
            ->get('/coordinator/dashboard')
            ->assertForbidden();
    }

    public function test_guest_cannot_access_any_role_dashboard(): void
    {
        foreach (['/admin/dashboard', '/pm/dashboard', '/coordinator/dashboard', '/subordinate/dashboard'] as $uri) {
            $this->get($uri)->assertRedirect('/login');
        }
    }

    public function test_seeded_roles_exist(): void
    {
        $expectedRoles = ['Admin', 'PM/Manager', 'Coordinator', 'Subordinate'];

        foreach ($expectedRoles as $roleName) {
            $this->assertTrue(Role::where('name', $roleName)->exists(), "Failed asserting role [{$roleName}] exists.");
        }
    }

    public function test_seeded_permissions_exist(): void
    {
        $expectedPermissions = [
            'access admin dashboard',
            'access pm dashboard',
            'access coordinator dashboard',
            'access subordinate dashboard',
            'manage users',
            'assign roles',
            'view own profile',
            'update own profile',
            'view repository',
            'create repository entry',
            'update repository entry',
            'add repository update',
            'view projects',
            'create project',
            'update project',
            'assign coordinator',
            'view assigned projects',
        ];

        foreach ($expectedPermissions as $permissionName) {
            $this->assertTrue(Permission::where('name', $permissionName)->exists(), "Failed asserting permission [{$permissionName}] exists.");
        }
    }

    public function test_user_can_be_assigned_a_role(): void
    {
        $user = User::factory()->create();

        $user->syncRoles(['Coordinator']);

        $this->assertTrue($user->fresh()->hasRole('Coordinator'));
    }
}
