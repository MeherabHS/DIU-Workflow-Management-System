<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_ok_when_database_and_storage_are_available(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'app' => 'DIUS Workflow Management Portal',
                'checks' => [
                    'database' => 'ok',
                    'storage' => 'ok',
                ],
            ]);
    }

    public function test_health_endpoint_is_public(): void
    {
        $this->assertGuest();

        $this->getJson('/health')->assertOk();
    }

    public function test_health_endpoint_does_not_expose_secrets_or_internal_paths(): void
    {
        $appKey = (string) config('app.key');
        config(['database.connections.mysql.host' => 'private-db-host']);

        $response = $this->getJson('/health')->assertOk();
        $content = $response->getContent();

        if ($appKey !== '') {
            $this->assertStringNotContainsString($appKey, $content);
        }
        $this->assertStringNotContainsString('private-db-host', $content);
        $this->assertStringNotContainsString(base_path(), $content);
        $this->assertStringNotContainsString(storage_path(), $content);
    }

    public function test_health_endpoint_returns_service_unavailable_when_database_check_fails(): void
    {
        DB::shouldReceive('select')
            ->once()
            ->with('select 1')
            ->andThrow(new RuntimeException('database unavailable'));

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertJson([
                'status' => 'error',
                'checks' => [
                    'database' => 'failed',
                    'storage' => 'ok',
                ],
            ])
            ->assertJsonMissing(['database unavailable']);
    }
}
