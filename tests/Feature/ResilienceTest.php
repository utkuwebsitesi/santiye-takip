<?php

namespace Tests\Feature;

use App\Http\Controllers\InstallController;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class ResilienceTest extends TestCase
{
    public function test_health_endpoint_checks_database_and_writable_directories(): void
    {
        $response = $this->get('/health');

        $response->assertOk()
            ->assertJson([
                'status' => 'ok',
                'checks' => ['database' => true, 'storage' => true, 'cache' => true],
            ]);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_installer_uses_resilient_file_services_and_escapes_dollar_in_password(): void
    {
        $method = new ReflectionMethod(InstallController::class, 'environment');
        $environment = $method->invoke(new InstallController, [
            'app_name' => 'Şantiye Takip',
            'app_url' => 'https://example.com',
            'db_host' => 'localhost',
            'db_port' => 3306,
            'db_database' => 'database',
            'db_username' => 'user',
            'db_password' => 'p$word',
        ], 'base64:test');

        $this->assertStringContainsString('DB_HOST="localhost"', $environment);
        $this->assertStringContainsString('DB_PASSWORD="p\\$word"', $environment);
        $this->assertStringContainsString('SESSION_DRIVER=file', $environment);
        $this->assertStringContainsString('CACHE_STORE=file', $environment);
        $this->assertStringContainsString('QUEUE_CONNECTION=sync', $environment);
    }

    public function test_health_endpoint_returns_service_unavailable_when_database_is_down(): void
    {
        $originalDefault = config('database.default');
        $originalMysql = config('database.connections.mysql');
        $originalSession = config('session.driver');

        try {
            config([
                'session.driver' => 'database',
                'database.default' => 'mysql',
                'database.connections.mysql.host' => '127.0.0.1',
                'database.connections.mysql.port' => 1,
                'database.connections.mysql.database' => 'unavailable',
                'database.connections.mysql.username' => 'unavailable',
                'database.connections.mysql.password' => 'unavailable',
            ]);
            DB::purge('mysql');

            $this->getJson('/health')
                ->assertStatus(503)
                ->assertJsonPath('status', 'unavailable')
                ->assertJsonPath('checks.database', false);
        } finally {
            config([
                'session.driver' => $originalSession,
                'database.default' => $originalDefault,
                'database.connections.mysql' => $originalMysql,
            ]);
            DB::purge('mysql');
        }
    }
}
