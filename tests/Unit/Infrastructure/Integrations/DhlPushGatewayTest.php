<?php

namespace Tests\Unit\Infrastructure\Integrations;

use App\Domain\Integrations\Contracts\DhlPushGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class DhlPushGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->configureGateway();
        Cache::forget('circuit_breaker:dhl.push');
    }

    public function test_push_subscription_crud(): void
    {
        Http::fake([
            'https://push.example/subscription' => Http::response(['id' => 'sub-1'], 201),
            'https://push.example/subscription/sub-1' => Http::sequence()
                ->push(['id' => 'sub-1', 'status' => 'pending'], 200)
                ->push(['id' => 'sub-1', 'status' => 'activated'], 200)
                ->push(status: 204),
            'https://push.example/subscriptions' => Http::response([['id' => 'sub-1']], 200),
        ]);

        $gateway = app(DhlPushGateway::class);

        $created = $gateway->createSubscription(['callbackUrl' => 'https://example.com/webhook']);
        $fetched = $gateway->getSubscription('sub-1');
        $activated = $gateway->activateSubscription('sub-1', 'secret');
        $list = $gateway->listSubscriptions();
        $gateway->removeSubscription('sub-1', 'secret');

        $this->assertSame('sub-1', $created['id'] ?? null);
        $this->assertSame('pending', $fetched['status'] ?? null);
        $this->assertSame('activated', $activated['status'] ?? null);
        $this->assertSame('sub-1', $list[0]['id'] ?? null);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), '/subscription') && ($request->data()['callbackUrl'] ?? '') !== '');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET' && str_contains($request->url(), '/subscription/sub-1'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), '/subscription/sub-1') && ($request->data()['secret'] ?? '') === 'secret');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && str_contains($request->url(), '/subscription/sub-1'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET' && str_contains($request->url(), '/subscriptions'));
    }

    private function configureGateway(): void
    {
        config([
            'services.dhl_push' => [
                'base_url' => 'https://push.example',
                'api_key' => 'push-key',
                'api_key_header' => 'DHL-API-Key',
                'paths' => [
                    'subscription' => '/subscription',
                    'subscription_with_id' => '/subscription/{id}',
                    'subscriptions' => '/subscriptions',
                ],
                'timeout' => 5,
                'connect_timeout' => 2,
                'retry' => ['times' => 0, 'sleep' => 0],
                'circuit_breaker' => ['failures' => 3, 'cooldown' => 60],
                'log_channel' => 'stack',
                'verify' => true,
            ],
        ]);
    }
}
