<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Integrations\Contracts\DhlFreightGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Feature tests for GET /api/admin/dhl/timetable.
 *
 * Engineering-Handbuch §22 (API Regel): API-Endpunkte sind Verträge —
 * konsistent, versionierbar, verständlich.
 * §20 (Auth): Authentifizierung und Autorisierung immer auf dem Server prüfen.
 *
 * Route: GET /api/admin/dhl/timetable
 * Middleware: can:fulfillment.orders.view
 * Controller: DhlTimetableController::show
 */
final class DhlTimetableControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureDhlFreight();
    }

    public function test_show_returns_timetable_data_for_valid_request(): void
    {
        Http::fake([
            'https://freight.example/info/time-table/v1/gettimetable' => Http::response([
                'slots' => [
                    ['departure' => '2026-05-12T08:00:00Z', 'arrival' => '2026-05-12T14:00:00Z'],
                ],
            ], 200),
        ]);

        $this->signInWithRole('leiter');

        $response = $this->getJson('/api/admin/dhl/timetable?'.http_build_query([
            'origin_postal_code' => '10115',
            'destination_postal_code' => '80331',
            'pickup_date' => now()->addDay()->format('Y-m-d'),
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.api+json');
        $response->assertJsonStructure([
            'data' => [
                'type',
                'id',
                'attributes' => [
                    'slots',
                ],
            ],
        ]);
        $response->assertJsonPath('data.type', 'dhl-timetable');
        $response->assertJsonPath('data.attributes.slots.0.departure', '2026-05-12T08:00:00Z');
    }

    public function test_show_returns_401_when_not_authenticated(): void
    {
        $response = $this->getJson('/api/admin/dhl/timetable?'.http_build_query([
            'origin_postal_code' => '10115',
            'destination_postal_code' => '80331',
            'pickup_date' => now()->addDay()->format('Y-m-d'),
        ]));

        $response->assertUnauthorized();
    }

    public function test_show_returns_403_when_user_lacks_permission(): void
    {
        // support has admin.access but not fulfillment.orders.view
        $this->signInWithRole('support');

        $response = $this->getJson('/api/admin/dhl/timetable?'.http_build_query([
            'origin_postal_code' => '10115',
            'destination_postal_code' => '80331',
            'pickup_date' => now()->addDay()->format('Y-m-d'),
        ]));

        $response->assertForbidden();
    }

    public function test_show_returns_422_when_required_params_missing(): void
    {
        $this->signInWithRole('leiter');

        // Missing all query params
        $response = $this->getJson('/api/admin/dhl/timetable');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['origin_postal_code', 'destination_postal_code', 'pickup_date']);
    }

    public function test_show_returns_422_when_pickup_date_is_in_the_past(): void
    {
        $this->signInWithRole('leiter');

        $response = $this->getJson('/api/admin/dhl/timetable?'.http_build_query([
            'origin_postal_code' => '10115',
            'destination_postal_code' => '80331',
            'pickup_date' => now()->subDay()->format('Y-m-d'),
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['pickup_date']);
    }

    public function test_show_returns_502_when_gateway_throws_exception(): void
    {
        Http::fake([
            'https://freight.example/info/time-table/v1/gettimetable' => function () {
                throw new ConnectionException('Gateway unreachable');
            },
        ]);

        $this->signInWithRole('leiter');

        $response = $this->getJson('/api/admin/dhl/timetable?'.http_build_query([
            'origin_postal_code' => '10115',
            'destination_postal_code' => '80331',
            'pickup_date' => now()->addDay()->format('Y-m-d'),
        ]));

        $response->assertStatus(502);
        $response->assertJsonStructure([
            'errors' => [
                ['status', 'title', 'detail'],
            ],
        ]);
    }

    public function test_show_returns_502_on_http_502_from_gateway(): void
    {
        Http::fake([
            'https://freight.example/info/time-table/v1/gettimetable' => Http::response([
                'message' => 'DHL Freight service unavailable',
            ], 502),
        ]);

        $this->signInWithRole('leiter');

        $response = $this->getJson('/api/admin/dhl/timetable?'.http_build_query([
            'origin_postal_code' => '10115',
            'destination_postal_code' => '80331',
            'pickup_date' => now()->addDay()->format('Y-m-d'),
        ]));

        $response->assertStatus(502);
    }

    private function configureDhlFreight(): void
    {
        config([
            'services.dhl_auth' => [
                'base_url' => 'https://auth.example',
                'username' => 'user',
                'password' => 'pass',
                'path' => '/auth/v1/token',
                'token_cache_ttl' => 0,
                'timeout' => 5,
                'connect_timeout' => 2,
                'retry' => ['times' => 0, 'sleep' => 0],
                'log_channel' => 'stack',
                'verify' => true,
            ],
            'services.dhl_freight' => [
                'base_url' => 'https://freight.example',
                'api_key' => 'key',
                'api_secret' => 'secret',
                'auth' => 'bearer',
                'api_key_header' => 'DHL-API-Key',
                'api_secret_header' => null,
                'paths' => [
                    'timetable' => '/info/time-table/v1/gettimetable',
                    'products' => '/info/products/services/v1/products',
                ],
                'timeout' => 5,
                'connect_timeout' => 2,
                'retry' => ['times' => 0, 'sleep' => 0],
                'circuit_breaker' => ['failures' => 3, 'cooldown' => 60],
                'log_channel' => 'stack',
                'ping' => ['method' => 'GET', 'path' => '/health'],
                'verify' => true,
            ],
        ]);

        // Mock auth gateway to return a valid token so the gateway can make real calls
        $this->app->bind(\App\Domain\Integrations\Contracts\DhlAuthenticationGateway::class, fn () => new class implements \App\Domain\Integrations\Contracts\DhlAuthenticationGateway
        {
            public function getToken(string $responseType = 'access_token'): array
            {
                return ['access_token' => 'test-token', 'token_type' => 'Bearer', 'expires_in' => 3600];
            }
        });
    }
}