<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Integrations;

use App\Domain\Integrations\Contracts\DhlAuthenticationGateway;
use App\Domain\Integrations\Contracts\DhlFreightGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

final class DhlFreightGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->configureGateway();
        $this->mockAuthGateway();
        Cache::forget('circuit_breaker:dhl.freight');
    }

    public function test_timetable_uses_bearer_token_and_configured_path(): void
    {
        Http::fake([
            'https://freight.example/timetable/gettimetable' => Http::response(['slots' => []], 200),
        ]);

        $gateway = app(DhlFreightGateway::class);
        $response = $gateway->getTimetable([
            'origin' => 'HAM',
            'destination' => 'MUC',
        ]);

        $this->assertSame(['slots' => []], $response);

        Http::assertSent(function (Request $request): bool {
            $authHeader = $request->header('Authorization')[0] ?? '';

            return $request->method() === 'POST'
                && str_contains($request->url(), '/timetable/gettimetable')
                && str_starts_with($authHeader, 'Bearer test-token')
                && $request->data()['origin'] === 'HAM'
                && $request->data()['destination'] === 'MUC';
        });
    }

    public function test_can_book_shipment_and_print_label(): void
    {
        Http::fake([
            'https://freight.example/sendtransportinstruction' => Http::response(['id' => 'ABC123'], 201),
            'https://freight.example/print/printdocumentsbyid' => Http::response(['label' => 'pdf'], 200),
            'https://freight.example/pricequote/quoteforprice' => Http::response(['price' => 42], 200),
        ]);

        $gateway = app(DhlFreightGateway::class);
        $booking = $gateway->bookShipment(['reference' => '123']);
        $label = $gateway->printLabel('ABC123', ['format' => 'PDF']);
        $quote = $gateway->getPriceQuote(['foo' => 'bar']);

        $this->assertSame(['id' => 'ABC123'], $booking);
        $this->assertSame(['label' => 'pdf'], $label);
        $this->assertSame(['price' => 42], $quote);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), '/sendtransportinstruction') && ($request->data()['reference'] ?? null) === '123');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), '/print/printdocumentsbyid') && ($request->data()['shipmentId'] ?? null) === 'ABC123');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), '/pricequote/quoteforprice'));
    }

    public function test_additional_services_and_validation_use_product_id(): void
    {
        Http::fake([
            'https://freight.example/products/DFI/additionalservices/validationresults' => Http::response(['valid' => true], 200),
            'https://freight.example/products/DFI/additionalservices*' => Http::response(['services' => []], 200),
        ]);

        $gateway = app(DhlFreightGateway::class);

        $services = $gateway->listAdditionalServices('DFI', ['country' => 'DE']);
        $validation = $gateway->validateAdditionalServices('DFI', ['svc1'], ['country' => 'DE']);

        $this->assertSame(['services' => []], $services);
        $this->assertSame(['valid' => true], $validation);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET' && str_contains($request->url(), '/products/DFI/additionalservices'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), '/products/DFI/additionalservices/validationresults') && ($request->data()['services'][0] ?? null) === 'svc1');
    }

    public function test_bearer_auth_fails_fast_without_token_or_api_key(): void
    {
        config(['services.dhl_freight.api_key' => '']);

        $this->app->bind(DhlAuthenticationGateway::class, fn () => new class implements DhlAuthenticationGateway
        {
            public function getToken(string $responseType = 'access_token'): array
            {
                return [];
            }
        });

        Http::fake();

        $gateway = app(DhlFreightGateway::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('DHL Freight bearer auth requires a DHL Auth token or API key fallback.');

        $gateway->bookShipment(['reference' => '123']);
    }

    public function test_print_documents_variants(): void
    {
        Http::fake([
            'https://freight.example/print/printdocuments' => Http::response(['document' => 'pdf'], 200),
            'https://freight.example/print/printmultipledocuments' => Http::response(['batch' => true], 200),
        ]);

        $gateway = app(DhlFreightGateway::class);

        $single = $gateway->printDocuments(['shipmentId' => 'A1']);
        $multi = $gateway->printMultipleDocuments([['shipmentId' => 'A1'], ['shipmentId' => 'A2']], ['format' => 'PDF']);

        $this->assertSame(['document' => 'pdf'], $single);
        $this->assertSame(['batch' => true], $multi);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), '/print/printdocuments') && ($request->data()['shipmentId'] ?? null) === 'A1');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && str_contains($request->url(), '/print/printmultipledocuments') && count($request->data()['shipments'] ?? []) === 2);
    }

    public function test_ping_uses_configured_endpoint(): void
    {
        Http::fake([
            'https://freight.example/health' => Http::response(['ok' => true], 200),
        ]);

        $gateway = app(DhlFreightGateway::class);
        $result = $gateway->ping();

        $this->assertSame(200, $result['status']);
        $this->assertSame(['ok' => true], $result['body']);
        $this->assertGreaterThan(0.0, $result['duration_ms']);
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
                    'timetable' => '/timetable/gettimetable',
                    'products' => '/products',
                    'additional_services' => '/products/{productId}/additionalservices',
                    'additional_services_validation' => '/products/{productId}/additionalservices/validationresults',
                    'shipments' => '/sendtransportinstruction',
                    'price_quote' => '/pricequote/quoteforprice',
                    'label' => '/print/printdocumentsbyid',
                    'print_documents' => '/print/printdocuments',
                    'print_multiple_documents' => '/print/printmultipledocuments',
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
    }

    private function mockAuthGateway(): void
    {
        $this->app->bind(DhlAuthenticationGateway::class, fn () => new class implements DhlAuthenticationGateway
        {
            public function getToken(string $responseType = 'access_token'): array
            {
                return ['access_token' => 'test-token', 'token_type' => 'Bearer', 'expires_in' => 3600];
            }
        });
    }
}
