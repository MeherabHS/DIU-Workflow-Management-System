<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }


    public function test_root_redirects_guest_to_login_without_laravel_welcome(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
        $response->assertDontSee('Laravel News');
        $response->assertDontSee('Laracasts');
        $response->assertDontSee('Vibrant Ecosystem');
    }

    public function test_login_screen_contains_register_link(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/Login'));

        $login = file_get_contents(resource_path('js/Pages/Auth/Login.tsx'));

        $this->assertStringContainsString('Register', $login);
        $this->assertStringContainsString("href={route('register')}", $login);
    }

    public function test_root_redirects_authenticated_roles_to_role_dashboard(): void
    {
        $admin = User::factory()->create(['email' => 'root-admin@example.com']);
        $admin->syncRoles(['Admin']);
        $this->actingAs($admin)->get('/')->assertRedirect(route('admin.dashboard'));

        auth()->logout();

        $pm = User::factory()->create(['email' => 'root-pm@example.com']);
        $pm->syncRoles(['PM/Manager']);
        $this->actingAs($pm)->get('/')->assertRedirect(route('pm.dashboard'));

        auth()->logout();

        $coordinator = User::factory()->create(['email' => 'root-coordinator@example.com']);
        $coordinator->syncRoles(['Coordinator']);
        $this->actingAs($coordinator)->get('/')->assertRedirect(route('coordinator.dashboard'));

        auth()->logout();

        $subordinate = User::factory()->create(['email' => 'root-subordinate@example.com']);
        $subordinate->syncRoles(['Subordinate']);
        $this->actingAs($subordinate)->get('/')->assertRedirect(route('subordinate.dashboard'));
    }
    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('subordinate.dashboard', absolute: false));
    }

    public function test_admin_login_redirects_to_admin_dashboard(): void
    {
        $user = User::factory()->create(['email' => 'login-admin@example.com']);
        $user->syncRoles(['Admin']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_pm_login_redirects_to_pm_dashboard(): void
    {
        $user = User::factory()->create(['email' => 'login-pm@example.com']);
        $user->syncRoles(['PM/Manager']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('pm.dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}


