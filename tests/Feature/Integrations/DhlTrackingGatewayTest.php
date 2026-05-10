<?php

namespace Tests\Feature\Integrations;

use App\Domain\Integrations\Contracts\DhlTrackingGateway;
use App\Support\Exceptions\CircuitBreakerOpenException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class DhlTrackingGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->configureDhl();

        Cache::forget('circuit_breaker:dhl');
    }

    public function test_fetch_tracking_events_uses_bearer_token(): void
    {
        Http::fake([
            'https://dhl.example/tracking/TRACK-1' => Http::response(['events' => []], 200),
        ]);

        $gateway = app(DhlTrackingGateway::class);

        $gateway->fetchTrackingEvents('TRACK-1');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://dhl.example/tracking/TRACK-1'
                && $request->header('Authorization')[0] === 'Bearer key';
        });
    }

    public function test_circuit_breaker_opens_after_failure_threshold(): void
    {
        config(['services.dhl.circuit_breaker.failures' => 1]);
        Cache::forget('circuit_breaker:dhl');

        Http::fake([
            'https://dhl.example/tracking/TRACK-2' => Http::response([], 500),
        ]);

        $gateway = app(DhlTrackingGateway::class);

        try {
            $gateway->fetchTrackingEvents('TRACK-2');
            $this->fail('Expected first call to throw');
        } catch (RequestException $exception) {
            $this->assertSame(500, $exception->response?->status());
        }

        $this->expectException(CircuitBreakerOpenException::class);
        $gateway->fetchTrackingEvents('TRACK-2');
    }

    public function test_ping_returns_status(): void
    {
        Http::fake([
            'https://dhl.example/health' => Http::response(['status' => 'ok'], 200),
        ]);

        config(['services.dhl.ping.path' => '/health']);

        $gateway = app(DhlTrackingGateway::class);

        $result = $gateway->ping();

        $this->assertSame(200, $result['status']);
        $this->assertIsFloat($result['duration_ms']);
        $this->assertSame(['status' => 'ok'], $result['body']);
    }

    private function configureDhl(): void
    {
        config([
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
