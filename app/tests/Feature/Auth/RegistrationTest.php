<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_can_open_register_page(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Auth/Register'));
    }

    public function test_guest_can_register_with_name_email_password(): void
    {
        $response = $this->post('/register', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', 'Your account has been registered. Please wait for admin approval before logging in.');

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'name' => 'New User',
            'is_active' => false,
        ]);
    }

    public function test_registered_user_receives_no_role_by_default(): void
    {
        $this->post('/register', [
            'name' => 'No Role User',
            'email' => 'norole@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'norole@example.com')->first();
        $this->assertTrue($user->getRoleNames()->isEmpty());
    }

    public function test_registered_user_is_inactive_by_default(): void
    {
        $this->post('/register', [
            'name' => 'Inactive User',
            'email' => 'inactive@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'inactive@example.com')->first();
        $this->assertFalse($user->is_active);
    }

    public function test_pending_user_cannot_login(): void
    {
        $this->post('/register', [
            'name' => 'Pending User',
            'email' => 'pending@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->post('/login', [
            'email' => 'pending@example.com',
            'password' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_pending_user_cannot_access_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => 'pending-dash@example.com',
            'is_active' => false,
        ]);

        // Middleware logs out and redirects to login
        $this->actingAs($user)->get('/dashboard')
            ->assertRedirect(route('login'));
    }

    public function test_active_user_with_no_role_cannot_access_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => 'no-role-active@example.com',
            'is_active' => true,
        ]);
        // Strip auto-assigned role from factory
        $user->syncRoles([]);

        // Middleware logs out and redirects to login
        $this->actingAs($user)->get('/dashboard')
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_assign_role_to_pending_user(): void
    {
        $this->post('/register', [
            'name' => 'Assign Role User',
            'email' => 'assignrole@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'assignrole@example.com')->first();
        $admin = User::factory()->create();
        $admin->syncRoles(['Admin']);

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $user), [
                'name' => 'Assign Role User',
                'email' => 'assignrole@example.com',
                'role' => 'Coordinator',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertTrue($user->fresh()->hasRole('Coordinator'));
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_approved_user_can_login_and_access_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => 'approved@example.com',
            'is_active' => true,
        ]);
        $user->syncRoles(['Subordinate']);

        $this->post('/login', [
            'email' => 'approved@example.com',
            'password' => 'password',
        ])->assertRedirect(route('subordinate.dashboard'));

        $this->assertAuthenticated();
    }

    public function test_user_cannot_assign_role_to_self_during_registration(): void
    {
        $response = $this->post('/register', [
            'name' => 'Self Role User',
            'email' => 'selfrole@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'Admin', // Should be ignored
        ]);

        $response->assertRedirect(route('login'));

        $user = User::where('email', 'selfrole@example.com')->first();
        $this->assertTrue($user->getRoleNames()->isEmpty());
    }
}

