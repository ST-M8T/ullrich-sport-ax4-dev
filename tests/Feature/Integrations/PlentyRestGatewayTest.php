<?php

namespace Tests\Feature\Integrations;

use App\Domain\Integrations\Contracts\PlentyOrderGateway;
use App\Support\Exceptions\CircuitBreakerOpenException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PlentyRestGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePlenty();

        Cache::forget('circuit_breaker:plenty.orders');
    }

    public function test_fetch_orders_by_status_uses_configured_client(): void
    {
        Http::fake([
            'https://plenty.example/rest/orders/search' => Http::response(['orders' => [['id' => 1]]], 200),
        ]);

        $gateway = app(PlentyOrderGateway::class);

        $result = $gateway->fetchOrdersByStatus(['BOOKED'], ['createdAfter' => '2024-01-01']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://plenty.example/rest/orders/search'
                && $request['status'] === ['BOOKED']
                && $request['filters'] === ['createdAfter' => '2024-01-01']
                && Str::startsWith($request->header('Authorization')[0] ?? '', 'Basic ');
        });

        $this->assertSame([['id' => 1]], $result['orders']);
    }

    public function test_circuit_breaker_opens_after_configured_failures(): void
    {
        config(['services.plenty.circuit_breaker.failures' => 1]);
        Cache::forget('circuit_breaker:plenty.orders');

        Http::fake([
            'https://plenty.example/rest/orders/search' => Http::response(['error' => 'upstream'], 503),
        ]);

        $gateway = app(PlentyOrderGateway::class);

        try {
            $gateway->fetchOrdersByStatus(['BOOKED']);
            $this->fail('Expected first call to throw');
        } catch (RequestException $exception) {
            $this->assertSame(503, $exception->response?->status());
        }

        $this->expectException(CircuitBreakerOpenException::class);
        $gateway->fetchOrdersByStatus(['BOOKED']);
    }

    public function test_ping_returns_status_and_body(): void
    {
        Http::fake([
            'https://plenty.example/ping' => Http::response(['ok' => true], 200),
        ]);

        config(['services.plenty.ping.path' => '/ping']);

        $gateway = app(PlentyOrderGateway::class);

        $result = $gateway->ping();

        $this->assertSame(200, $result['status']);
        $this->assertIsFloat($result['duration_ms']);
        $this->assertSame(['ok' => true], $result['body']);
    }

    private function configurePlenty(): void
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
        ]);
    }
}
