<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class IntegrationPingCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureIntegrations();
        Cache::forget('circuit_breaker:plenty.orders');
        Cache::forget('circuit_breaker:dhl');
    }

    public function test_plenty_ping_command_reports_success(): void
    {
        Http::fake([
            'https://plenty.example/ping' => Http::response(['ok' => true], 200),
        ]);

        $this->artisan('plenty:ping')
            ->assertExitCode(0)
            ->expectsOutputToContain('Plenty responded with HTTP 200');

        $this->assertDatabaseHas('system_jobs', [
            'job_name' => 'integration.plenty.ping',
            'status' => 'completed',
        ]);
    }

    public function test_plenty_ping_command_reports_failure(): void
    {
        Http::fake([
            'https://plenty.example/ping' => Http::response(['error' => true], 503),
        ]);

        $this->artisan('plenty:ping')
            ->assertExitCode(1)
            ->expectsOutputToContain('Plenty ping failed');

        $this->assertDatabaseHas('system_jobs', [
            'job_name' => 'integration.plenty.ping',
            'status' => 'failed',
        ]);
    }

    public function test_dhl_ping_command_reports_success(): void
    {
        Http::fake([
            'https://dhl.example/health' => Http::response(['status' => 'ok'], 200),
        ]);

        $this->artisan('dhl:ping')
            ->assertExitCode(0)
            ->expectsOutputToContain('DHL responded with HTTP 200');

        $this->assertDatabaseHas('system_jobs', [
            'job_name' => 'integration.dhl.ping',
            'status' => 'completed',
        ]);
    }

    public function test_dhl_ping_command_reports_failure(): void
    {
        Http::fake([
            'https://dhl.example/health' => Http::response([], 500),
        ]);

        $this->artisan('dhl:ping')
            ->assertExitCode(1)
            ->expectsOutputToContain('DHL ping failed');

        $this->assertDatabaseHas('system_jobs', [
            'job_name' => 'integration.dhl.ping',
            'status' => 'failed',
        ]);
    }

    private function configureIntegrations(): void
    {
        config([
            'services.plenty' => [
                'base_url' => 'https://plenty.example',
                'username' => 'user',
                'password' => 'secret',
                'timeout' => 5,
                'connect_timeout' => 2,
                'retry' => ['times' => 0, 'sleep' => 0],
                'circuit_breaker' => ['failures' => 3, 'cooldown' => 60],
                'log_channel' => 'stack',
                'ping' => ['method' => 'GET', 'path' => '/ping'],
                'verify' => true,
            ],
            'services.dhl' => [
                'base_url' => 'https://dhl.example',
                'api_key' => 'key',
                'timeout' => 5,
                'connect_timeout' => 2,
                'retry' => ['times' => 0, 'sleep' => 0],
                'circuit_breaker' => ['failures' => 3, 'cooldown' => 60],
                'log_channel' => 'stack',
                'ping' => ['method' => 'GET', 'path' => '/health'],
                'verify' => true,
            ],
        ]);
    }
}
