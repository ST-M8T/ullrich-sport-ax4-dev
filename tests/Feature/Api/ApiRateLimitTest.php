<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ApiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_requests_are_rate_limited(): void
    {
        config()->set('services.api.key', 'test-key');
        config()->set('security.rate_limiting.api.max_attempts', 2);
        config()->set('security.rate_limiting.api.decay_seconds', 60);

        for ($i = 0; $i < 2; $i++) {
            $response = $this->withHeaders(['X-API-Key' => 'test-key'])
                ->getJson('/api/v1/dispatch-lists');

            $response->assertStatus(200);
        }

        $rateLimited = $this->withHeaders(['X-API-Key' => 'test-key'])
            ->getJson('/api/v1/dispatch-lists');

        $rateLimited->assertStatus(429);
        $rateLimited->assertJsonStructure(['message']);
    }
}
