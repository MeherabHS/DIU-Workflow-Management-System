<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\RepositoryEntry;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RepositoryModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_cannot_access_repository_index(): void
    {
        $this->get('/repository')->assertRedirect('/login');
    }

    public function test_user_without_view_repository_permission_cannot_access_repository_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/repository')->assertForbidden();
    }

    public function test_admin_can_view_repository_index(): void
    {
        $user = User::factory()->create();
        $user->syncRoles(['Admin']);

        $this->actingAs($user)->get('/repository')->assertOk()->assertSee('Repository Tracker');
    }

    public function test_pm_manager_can_view_repository_index(): void
    {
        $user = User::factory()->create();
        $user->syncRoles(['PM/Manager']);

        $this->actingAs($user)->get('/repository')->assertOk()->assertSee('Repository Tracker');
    }

    public function test_coordinator_can_view_repository_index_if_view_permission_assigned(): void
    {
        $user = User::factory()->create();
        $user->syncRoles(['Coordinator']);

        $this->actingAs($user)->get('/repository')->assertOk()->assertSee('Repository Tracker');
    }

    public function test_subordinate_cannot_view_repository_index_by_default(): void
    {
        $user = User::factory()->create();
        $user->syncRoles(['Subordinate']);

        $this->actingAs($user)->get('/repository')->assertForbidden();
    }

    public function test_admin_can_access_repository_create_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)
            ->get('/repository/create')
            ->assertOk()
            ->assertSee('Create Repository Entry');
    }

    public function test_pm_manager_can_access_repository_create_page(): void
    {
        $pm = User::factory()->create();
        $pm->assignRole('PM/Manager');

        $this->actingAs($pm)
            ->get('/repository/create')
            ->assertOk()
            ->assertSee('Create Repository Entry');
    }

    public function test_coordinator_cannot_access_repository_create_page(): void
    {
        $coordinator = User::factory()->create();
        $coordinator->assignRole('Coordinator');

        $this->actingAs($coordinator)
            ->get('/repository/create')
            ->assertForbidden();
    }

    public function test_admin_can_create_repository_entry(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $department = Department::factory()->create();

        $this->actingAs($admin)->post('/repository', [
            'title' => 'Tender Alpha',
            'type' => 'Tender',
            'department_id' => $department->id,
            'client_or_office' => 'Head Office',
            'responsible_user_id' => $admin->id,
            'status' => 'planned',
            'deadline' => now()->addWeek()->format('Y-m-d'),
            'value_amount' => '5000',
            'value_currency' => 'BDT',
            'description' => 'Initial repository entry.',
        ])->assertRedirect();

        $this->assertDatabaseHas('repository_entries', ['title' => 'Tender Alpha', 'created_by' => $admin->id]);
    }

    public function test_pm_manager_can_create_repository_entry(): void
    {
        $pm = User::factory()->create();
        $pm->assignRole('PM/Manager');

        $this->actingAs($pm)->post('/repository', [
            'title' => 'PM Repository Entry',
            'status' => 'planned',
            'value_currency' => 'BDT',
            'description' => 'Created by PM.',
        ])->assertRedirect();

        $this->assertDatabaseHas('repository_entries', ['title' => 'PM Repository Entry', 'created_by' => $pm->id]);
    }

    public function test_coordinator_cannot_create_repository_entry(): void
    {
        $coordinator = User::factory()->create();
        $coordinator->assignRole('Coordinator');

        $this->actingAs($coordinator)->post('/repository', [
            'title' => 'Blocked Entry',
            'status' => 'planned',
            'value_currency' => 'BDT',
        ])->assertForbidden();
    }

    public function test_repository_entry_validation_requires_title_and_valid_status(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)->post('/repository', [
            'title' => '',
            'status' => 'invalid-status',
        ])->assertSessionHasErrors(['title', 'status']);
    }

    public function test_admin_pm_can_update_repository_entry(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $entry = RepositoryEntry::factory()->create(['title' => 'Original Title']);

        $this->actingAs($admin)->patch('/repository/'.$entry->id, [
            'title' => 'Updated Title',
            'status' => 'ongoing',
            'value_currency' => 'BDT',
        ])->assertRedirect(route('repository.show', $entry));

        $this->assertDatabaseHas('repository_entries', ['id' => $entry->id, 'title' => 'Updated Title', 'status' => 'ongoing']);
    }

    public function test_repository_show_page_displays_repository_entry_details(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $entry = RepositoryEntry::factory()->create(['title' => 'Visible Entry', 'description' => 'Repository detail body']);

        $this->actingAs($admin)->get('/repository/'.$entry->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Repository/Show')
                ->where('pageTitle', 'Repository Details')
                ->where('entry.title', 'Visible Entry')
                ->where('entry.description', 'Repository detail body')
                ->has('entry.updates'));
    }

    public function test_admin_pm_can_add_repository_timeline_update(): void
    {
        $pm = User::factory()->create();
        $pm->assignRole('PM/Manager');
        $entry = RepositoryEntry::factory()->create(['status' => 'planned']);

        $this->actingAs($pm)->post('/repository/'.$entry->id.'/updates', [
            'update_type' => 'status_change',
            'new_status' => 'submitted',
            'note' => 'Submitted to committee.',
        ])->assertRedirect(route('repository.show', $entry));

        $this->assertDatabaseHas('repository_updates', ['repository_entry_id' => $entry->id, 'user_id' => $pm->id, 'note' => 'Submitted to committee.']);
    }

    public function test_timeline_update_stores_old_status_and_new_status(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $entry = RepositoryEntry::factory()->create(['status' => 'planned']);

        $this->actingAs($admin)->post('/repository/'.$entry->id.'/updates', [
            'new_status' => 'ongoing',
            'note' => 'Started tracking.',
        ]);

        $this->assertDatabaseHas('repository_updates', ['repository_entry_id' => $entry->id, 'old_status' => 'planned', 'new_status' => 'ongoing']);
    }

    public function test_adding_update_with_new_status_changes_repository_entry_status(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $entry = RepositoryEntry::factory()->create(['status' => 'planned']);

        $this->actingAs($admin)->post('/repository/'.$entry->id.'/updates', [
            'new_status' => 'ongoing',
            'note' => 'Moved into ongoing work.',
        ]);

        $this->assertSame('ongoing', $entry->fresh()->status);
    }

    public function test_completed_status_sets_completed_at(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $entry = RepositoryEntry::factory()->create(['status' => 'submitted', 'completed_at' => null]);

        $this->actingAs($admin)->post('/repository/'.$entry->id.'/updates', [
            'new_status' => 'completed',
            'note' => 'Completed successfully.',
        ]);

        $this->assertNotNull($entry->fresh()->completed_at);
    }

    public function test_archived_status_sets_archived_at_but_does_not_run_cleanup(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $entry = RepositoryEntry::factory()->create(['status' => 'completed', 'archived_at' => null]);

        $this->actingAs($admin)->post('/repository/'.$entry->id.'/updates', [
            'new_status' => 'archived',
            'note' => 'Archived in tracker only.',
        ]);

        $entry->refresh();

        $this->assertNotNull($entry->archived_at);
        $this->assertDatabaseCount('archive_records', 0);
    }

    public function test_repository_navigation_link_is_limited_to_admin_and_pm_sidebar(): void
    {
        $coordinator = User::factory()->create();
        $coordinator->assignRole('Coordinator');

        $this->actingAs($coordinator)->get('/coordinator/dashboard')->assertOk()->assertDontSee('Repository Tracker');

        $subordinate = User::factory()->create();
        $subordinate->assignRole('Subordinate');

        $this->actingAs($subordinate)->get('/subordinate/dashboard')->assertOk()->assertDontSee('Repository Tracker');
    }

    public function test_repository_index_shows_create_repository_entry_button_only_to_users_with_permission(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)->get('/repository')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Repository/Index')
                ->where('primaryAction', 'Create Repository Entry'));

        $pm = User::factory()->create();
        $pm->assignRole('PM/Manager');

        $this->actingAs($pm)->get('/repository')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Repository/Index')
                ->where('primaryAction', 'Create Repository Entry'));

        $coordinator = User::factory()->create();
        $coordinator->assignRole('Coordinator');

        $this->actingAs($coordinator)->get('/repository')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Repository/Index')
                ->where('primaryAction', null));
    }

    public function test_update_repository_entry_requires_update_permission_not_create(): void
    {
        // Create a user with create permission but NOT update permission
        $user = User::factory()->create();
        // Give Subordinate role so middleware allows access, then add only create permission
        $user->syncRoles(['Subordinate']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $createPerm = \Spatie\Permission\Models\Permission::where('name', 'create repository entry')->first();
        if ($createPerm) {
            $user->givePermissionTo($createPerm);
        }

        $entry = RepositoryEntry::factory()->create(['title' => 'Original']);

        $this->actingAs($user)->patch('/repository/'.$entry->id, [
            'title' => 'Hacked Title',
            'status' => 'ongoing',
            'value_currency' => 'BDT',
        ])->assertForbidden();

        $this->assertDatabaseHas('repository_entries', ['id' => $entry->id, 'title' => 'Original']);
    }
}

