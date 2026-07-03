<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProfilePhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
    }

    public function test_user_can_upload_valid_jpg_under_two_mb(): void
    {
        $this->assertValidProfilePhotoUpload($this->imageUpload('avatar.jpg', 'image/jpeg', 512));
    }

    public function test_user_can_upload_valid_png_under_two_mb(): void
    {
        $this->assertValidProfilePhotoUpload($this->imageUpload('avatar.png', 'image/png', 512));
    }

    public function test_user_can_upload_valid_webp_under_two_mb(): void
    {
        $this->assertValidProfilePhotoUpload($this->imageUpload('avatar.webp', 'image/webp', 512));
    }

    public function test_profile_photo_over_two_mb_is_rejected_by_laravel_validation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.photo.update'), [
                'photo' => $this->imageUpload('large.jpg', 'image/jpeg', 2049),
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('photo');

        $this->assertNull($user->fresh()->profile_photo_path);
    }

    public function test_unsafe_profile_photo_file_types_are_rejected(): void
    {
        $user = User::factory()->create();

        foreach ([
            $this->fileUpload('avatar.svg', '<svg></svg>', 'image/svg+xml'),
            $this->fileUpload('avatar.php', '<?php echo "no";', 'application/x-php'),
            $this->fileUpload('avatar.js', 'alert("no");', 'application/javascript'),
            $this->fileUpload('avatar.html', '<!doctype html><title>No</title>', 'text/html'),
        ] as $file) {
            $this->actingAs($user)
                ->from(route('profile.edit'))
                ->post(route('profile.photo.update'), ['photo' => $file])
                ->assertRedirect(route('profile.edit'))
                ->assertSessionHasErrors('photo');
        }

        $this->assertNull($user->fresh()->profile_photo_path);
    }

    private function assertValidProfilePhotoUpload(UploadedFile $file): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.photo.update'), ['photo' => $file])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasNoErrors();

        $path = $user->fresh()->profile_photo_path;

        $this->assertNotNull($path);
        $this->assertStringStartsWith('profile-photos/', $path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.profile_photo_url', '/storage/'.$path)
            );
    }

    private function imageUpload(string $name, string $mimeType, int $kilobytes): UploadedFile
    {
        $bytes = match ($mimeType) {
            'image/jpeg' => base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAqf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/Aaf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/Aaf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/Aqf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/IV//2gAMAwEAAgADAAAAEP/EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQMBAT8QH//EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQIBAT8QH//EABQQAQAAAAAAAAAAAAAAAAAAABD/2gAIAQEAAT8QH//Z'),
            'image/png' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='),
            'image/webp' => base64_decode('UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEADsD+JaQAA3AAAAAA'),
        };

        $targetBytes = $kilobytes * 1024;
        if (strlen($bytes) < $targetBytes) {
            $bytes .= str_repeat("\0", $targetBytes - strlen($bytes));
        }

        return $this->fileUpload($name, $bytes, $mimeType);
    }

    private function fileUpload(string $name, string $contents, string $mimeType): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'profile-photo-test-');
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, $mimeType, null, true);
    }
}