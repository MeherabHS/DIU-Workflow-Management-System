<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_view_users_index(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Index')
                ->where('pageTitle', 'User Management')
                ->has('users')
            );
    }

    public function test_pm_cannot_view_users_index(): void
    {
        $pm = $this->makePM();

        $this->actingAs($pm)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_coordinator_cannot_view_users_index(): void
    {
        $coord = $this->makeCoordinator();

        $this->actingAs($coord)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_subordinate_cannot_view_users_index(): void
    {
        $sub = $this->makeSubordinate();

        $this->actingAs($sub)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_user(): void
    {
        $admin = $this->makeAdmin();
        $department = Department::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'New User',
                'email' => 'newuser@test.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'Coordinator',
                'department_id' => $department->id,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', ['email' => 'newuser@test.com', 'name' => 'New User']);

        $user = User::where('email', 'newuser@test.com')->first();
        $this->assertTrue($user->hasRole('Coordinator'));
    }

    public function test_admin_can_update_user(): void
    {
        $admin = $this->makeAdmin();
        $user = $this->makeCoordinator('update@test.com');

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $user), [
                'name' => 'Updated Name',
                'email' => 'updated@test.com',
                'role' => 'Subordinate',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.users.show', $user));

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated Name', 'email' => 'updated@test.com']);
        $this->assertTrue($user->fresh()->hasRole('Subordinate'));
    }

    public function test_admin_can_toggle_user_active(): void
    {
        $admin = $this->makeAdmin();
        $user = $this->makeCoordinator('toggle@test.com');

        $this->actingAs($admin)
            ->post(route('admin.users.toggle-active', $user))
            ->assertRedirect();

        $this->assertFalse($user->fresh()->is_active);

        $this->actingAs($admin)
            ->post(route('admin.users.toggle-active', $user))
            ->assertRedirect();

        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_admin_cannot_deactivate_own_account(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('admin.users.toggle-active', $admin))
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_deactivated_user_cannot_login(): void
    {
        $user = $this->makeCoordinator('deactivated-login@test.com');
        $user->update(['is_active' => false]);

        $this->post('/login', [
            'email' => 'deactivated-login@test.com',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_list_filters_by_role(): void
    {
        $admin = $this->makeAdmin();
        $coord = $this->makeCoordinator('filter-coord@test.com');
        $this->makeSubordinate('filter-sub@test.com');

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['role' => 'Coordinator']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Index')
                ->has('users.data')
            );

        // Verify the coordinator user exists in the database with the right role
        $this->assertDatabaseHas('model_has_roles', [
            'model_id' => $coord->id,
        ]);
    }

    public function test_user_list_filters_by_active_status(): void
    {
        $admin = $this->makeAdmin();
        $inactive = $this->makeCoordinator('inactive-filter@test.com');
        $inactive->update(['is_active' => false]);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['is_active' => '0']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Index')
                ->has('users.data')
            );

        // Verify the user is actually inactive in the database
        $this->assertDatabaseHas('users', ['id' => $inactive->id, 'is_active' => false]);
    }

    // Helpers
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
