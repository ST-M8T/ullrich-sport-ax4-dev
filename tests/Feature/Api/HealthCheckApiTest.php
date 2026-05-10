<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

final class HealthCheckApiTest extends TestCase
{
    public function test_live_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/health/live');

        $response->assertOk();
        $response->assertJson([
            'status' => 'ok',
        ]);
        $response->assertJsonStructure([
            'status',
            'timestamp',
        ]);
    }

    public function test_ready_endpoint_returns_component_statuses(): void
    {
        $response = $this->getJson('/api/v1/health/ready');

        $response->assertOk();
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'checks' => [
                'app' => ['status'],
                'database' => ['status'],
            ],
        ]);

        $json = $response->json();

        $this->assertNotSame('fail', $json['status']);
        $this->assertSame('ok', $json['checks']['app']['status']);
    }
}
