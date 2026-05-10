<?php

namespace Tests\Unit\Infrastructure\Integrations;

use App\Domain\Integrations\Contracts\DhlAuthenticationGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class DhlAuthenticationGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->configureGateway();
        Cache::forget('dhl.auth.token:access_token');
    }

    public function test_get_token_uses_basic_auth_and_caches(): void
    {
        Http::fake([
            'https://auth.example/auth/v1/token' => Http::response([
                'access_token' => 'token-123',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),
        ]);

        $gateway = app(DhlAuthenticationGateway::class);

        $first = $gateway->getToken();
        $second = $gateway->getToken();

        $this->assertSame('token-123', $first['access_token'] ?? null);
        $this->assertSame('token-123', $second['access_token'] ?? null);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $authHeader = $request->header('Authorization')[0] ?? '';

            return $request->method() === 'POST'
                && str_contains($request->url(), '/auth/v1/token')
                && str_starts_with($authHeader, 'Basic ')
                && ($request->data()['grant_type'] ?? '') === 'client_credentials';
        });
    }

    private function configureGateway(): void
    {
        config([
            'services.dhl_auth' => [
                'base_url' => 'https://auth.example',
                'username' => 'user',
                'password' => 'pass',
                'path' => '/auth/v1/token',
                'token_cache_ttl' => 0,
                'timeout' => 3,
                'connect_timeout' => 2,
                'retry' => ['times' => 0, 'sleep' => 0],
                'log_channel' => 'stack',
                'verify' => true,
            ],
        ]);
    }
}
