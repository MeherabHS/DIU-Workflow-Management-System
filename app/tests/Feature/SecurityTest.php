<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_pending_user_cannot_access_projects(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->actingAs($user)->get('/projects')
            ->assertRedirect(route('login'));
    }

    public function test_pending_user_cannot_access_repository(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->actingAs($user)->get('/repository')
            ->assertRedirect(route('login'));
    }

    public function test_pending_user_cannot_access_tasks(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->actingAs($user)->get('/my-subtasks')
            ->assertRedirect(route('login'));
    }

    public function test_pending_user_cannot_access_reports(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->actingAs($user)->get('/reports')
            ->assertRedirect(route('login'));
    }

    public function test_pending_user_cannot_access_user_management(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->actingAs($user)->get('/admin/users')
            ->assertRedirect(route('login'));
    }

    public function test_pending_user_cannot_access_audit_trail(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->actingAs($user)->get('/admin/audit-logs')
            ->assertRedirect(route('login'));
    }

    public function test_pending_user_cannot_access_notifications(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->actingAs($user)->get('/notifications')
            ->assertRedirect(route('login'));
    }

    public function test_admin_cannot_change_own_role(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['Admin']);

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $admin), [
                'name' => 'Admin User',
                'email' => $admin->email,
                'role' => 'Subordinate',
                'is_active' => true,
            ])
            ->assertSessionHas('error', 'You cannot change your own role.');

        $this->assertTrue($admin->fresh()->hasRole('Admin'));
    }

    public function test_admin_cannot_deactivate_self(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['Admin']);

        $this->actingAs($admin)
            ->post(route('admin.users.toggle-active', $admin))
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_prevent_role_escalation_by_non_admin(): void
    {
        $pm = User::factory()->create();
        $pm->syncRoles(['PM/Manager']);

        $target = User::factory()->create();

        $this->actingAs($pm)
            ->patch(route('admin.users.update', $target), [
                'name' => 'Target User',
                'email' => $target->email,
                'role' => 'Admin',
                'is_active' => true,
            ])
            ->assertForbidden();
    }

    public function test_active_user_without_role_is_blocked(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);
        // Strip auto-assigned role from factory
        $user->syncRoles([]);

        // No role assigned — middleware blocks
        $this->actingAs($user)->get('/dashboard')
            ->assertRedirect(route('login'));
        $this->actingAs($user)->get('/projects')
            ->assertRedirect(route('login'));
    }
}
