<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\WorkflowNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_login_throttles_after_limit(): void
    {
        $user = $this->makeAdmin('login-rate@example.com');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from(route('login'))->post(route('login'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->from(route('login'))->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
    }

    public function test_password_reset_throttles(): void
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->post(route('password.email'), ['email' => 'missing-rate@example.com'])
                ->assertRedirect();
        }

        $this->post(route('password.email'), ['email' => 'missing-rate@example.com'])
            ->assertTooManyRequests();
    }

    public function test_registration_throttles(): void
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->post(route('register'), $this->registrationPayload("rate{$attempt}@example.com"))
                ->assertRedirect(route('login'));
        }

        $this->post(route('register'), $this->registrationPayload('rate-limited@example.com'))
            ->assertTooManyRequests();
    }

    public function test_workflow_upload_throttles(): void
    {
        $admin = $this->makeAdmin('upload-rate@example.com');
        $project = Project::factory()->create();

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->actingAs($admin)->post(route('projects.files.store', $project), [
                'file' => UploadedFile::fake()->create("rate{$attempt}.pdf", 10, 'application/pdf'),
            ])->assertRedirect();
        }

        $this->actingAs($admin)->post(route('projects.files.store', $project), [
            'file' => UploadedFile::fake()->create('rate-limited.pdf', 10, 'application/pdf'),
        ])->assertTooManyRequests();
    }

    public function test_profile_photo_upload_throttles(): void
    {
        $admin = $this->makeAdmin('photo-rate@example.com');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->actingAs($admin)->post(route('profile.photo.update'), [
                'photo' => UploadedFile::fake()->create("photo{$attempt}.jpg", 10, 'image/jpeg'),
            ])->assertRedirect(route('profile.edit'));
        }

        $this->actingAs($admin)->post(route('profile.photo.update'), [
            'photo' => UploadedFile::fake()->create('photo-limited.jpg', 10, 'image/jpeg'),
        ])->assertTooManyRequests();
    }

    public function test_workflow_message_throttles(): void
    {
        $admin = $this->makeAdmin('message-rate@example.com');
        $project = Project::factory()->create();

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->actingAs($admin)->post(route('projects.messages.store', $project), [
                'body' => "Rate limit message {$attempt}",
            ])->assertRedirect();
        }

        $this->actingAs($admin)->post(route('projects.messages.store', $project), [
            'body' => 'Rate limited message',
        ])->assertTooManyRequests();
    }

    public function test_notification_read_all_throttles(): void
    {
        $admin = $this->makeAdmin('notification-rate@example.com');

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->actingAs($admin)->post(route('notifications.read-all'))
                ->assertRedirect();
        }

        $this->actingAs($admin)->post(route('notifications.read-all'))
            ->assertTooManyRequests();
    }

    public function test_single_notification_read_uses_higher_notification_action_limit(): void
    {
        $admin = $this->makeAdmin('notification-single-rate@example.com');
        $notification = WorkflowNotification::create([
            'user_id' => $admin->id,
            'type' => 'rate_limit_test',
            'title' => 'Rate limit test',
            'body' => 'Testing notification read throttling.',
        ]);

        $this->actingAs($admin)->post(route('notifications.read', $notification))
            ->assertRedirect();
    }

    public function test_report_csv_export_throttles(): void
    {
        $admin = $this->makeAdmin('report-rate@example.com');
        Project::factory()->create(['title' => 'Rate Limited Export']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->actingAs($admin)->get(route('reports.project-progress', ['export' => 'csv']))
                ->assertOk();
        }

        $this->actingAs($admin)->get(route('reports.project-progress', ['export' => 'csv']))
            ->assertTooManyRequests();
    }

    public function test_report_page_view_is_not_counted_as_csv_export(): void
    {
        $admin = $this->makeAdmin('report-view-rate@example.com');

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->actingAs($admin)->get(route('reports.project-progress'))
                ->assertOk();
        }
    }

    public function test_ai_comparison_throttles(): void
    {
        $admin = $this->makeAdmin('ai-rate@example.com');
        $project = Project::factory()->create();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->actingAs($admin)->postJson(route('projects.comparison.run', $project))
                ->assertOk();
        }

        $this->actingAs($admin)->postJson(route('projects.comparison.run', $project))
            ->assertTooManyRequests();
    }

    private function registrationPayload(string $email): array
    {
        return [
            'name' => 'Rate Limited User',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];
    }

    private function makeAdmin(?string $email = null): User
    {
        $user = User::factory()->create(['email' => $email ?? fake()->unique()->safeEmail()]);
        $user->syncRoles(['Admin']);

        return $user;
    }
}

