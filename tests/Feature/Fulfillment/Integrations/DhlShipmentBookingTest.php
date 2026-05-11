<?php

declare(strict_types=1);

namespace Tests\Feature\Fulfillment\Integrations;

use App\Application\Fulfillment\Integrations\Dhl\DTOs\DhlBookingOptions;
use App\Application\Fulfillment\Integrations\Dhl\Services\DhlShipmentBookingService;
use App\Domain\Shared\ValueObjects\Identifier;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentSenderProfileModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentOrderModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class DhlShipmentBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.dhl_auth.base_url' => 'https://api-sandbox.dhl.com',
            'services.dhl_auth.username' => 'client-id',
            'services.dhl_auth.password' => 'client-secret',
            'services.dhl_auth.path' => '/auth/v1/token',
            'services.dhl_auth.token_cache_ttl' => 0,
            'services.dhl_freight.base_url' => 'https://api-sandbox.dhl.com/freight',
            'services.dhl_freight.api_key' => 'fallback-api-key',
        ]);

        Cache::forget('dhl.auth.token:access_token');
    }

    public function test_book_shipment_success(): void
    {
        Http::fake([
            'https://api-sandbox.dhl.com/auth/v1/token' => Http::response([
                'access_token' => 'test-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),
            'https://api-sandbox.dhl.com/freight/*' => Http::response([
                'shipmentId' => 'DHL-12345',
                'trackingNumbers' => ['TRACK001', 'TRACK002'],
                'status' => 'booked',
            ], 200),
        ]);

        $senderProfile = FulfillmentSenderProfileModel::factory()->create();
        $order = ShipmentOrderModel::factory()->create([
            'sender_profile_id' => $senderProfile->id,
            'is_booked' => false,
        ]);

        $service = $this->app->make(DhlShipmentBookingService::class);
        $result = $service->bookShipment(
            Identifier::fromInt($order->id),
            DhlBookingOptions::fromArray([])
        );

        $this->assertTrue($result->success);
        $this->assertEquals('DHL-12345', $result->shipmentId);
        $this->assertCount(2, $result->trackingNumbers);

        $order->refresh();
        $this->assertEquals('DHL-12345', $order->dhl_shipment_id);
        $this->assertTrue($order->is_booked);
    }

    public function test_book_shipment_api_error(): void
    {
        Http::fake([
            'https://api-sandbox.dhl.com/auth/v1/token' => Http::response([
                'access_token' => 'test-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),
            'https://api-sandbox.dhl.com/freight/*' => Http::response([
                'error' => 'Invalid product ID',
                'status' => 'error',
            ], 400),
        ]);

        $senderProfile = FulfillmentSenderProfileModel::factory()->create();
        $order = ShipmentOrderModel::factory()->create([
            'sender_profile_id' => $senderProfile->id,
            'is_booked' => false,
        ]);

        $service = $this->app->make(DhlShipmentBookingService::class);
        $result = $service->bookShipment(
            Identifier::fromInt($order->id),
            DhlBookingOptions::fromArray([])
        );

        $this->assertFalse($result->success);
        $this->assertNotNull($result->error);
        $this->assertNull($result->shipmentId);

        $order->refresh();
        $this->assertNotNull($order->dhl_booking_error);
    }

    public function test_book_shipment_order_not_found(): void
    {
        $service = $this->app->make(DhlShipmentBookingService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Shipment order not found');

        $service->bookShipment(
            Identifier::fromInt(99999),
            DhlBookingOptions::fromArray([])
        );
    }

    public function test_book_shipment_no_sender_profile(): void
    {
        $order = ShipmentOrderModel::factory()->create([
            'sender_profile_id' => null,
            'is_booked' => false,
        ]);

        $service = $this->app->make(DhlShipmentBookingService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Shipment order has no sender profile');

        $service->bookShipment(
            Identifier::fromInt($order->id),
            DhlBookingOptions::fromArray([])
        );
    }
}
