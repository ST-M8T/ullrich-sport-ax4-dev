<?php

namespace Tests\Feature\Api;

use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentEventModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ShipmentDetailApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_shipment_detail_endpoint_returns_payload_with_events(): void
    {
        config(['services.api.key' => 'secret']);

        $shipment = ShipmentModel::query()->create([
            'carrier_code' => 'dhl',
            'shipping_profile_id' => null,
            'tracking_number' => 'TRACK-9999',
            'status_code' => 'IN_TRANSIT',
            'status_description' => 'On the way',
            'last_event_at' => now()->subMinutes(5),
            'delivered_at' => null,
            'failed_attempts' => 0,
            'last_payload' => ['lastStatus' => 'IN_TRANSIT'],
            'metadata' => ['source' => 'sync-test'],
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        ShipmentEventModel::query()->create([
            'id' => 1,
            'shipment_id' => $shipment->getKey(),
            'event_code' => 'PU',
            'event_status' => 'PICKED_UP',
            'event_description' => 'Shipment picked up',
            'facility' => 'DHL HUB',
            'city' => 'Bonn',
            'country_iso2' => 'DE',
            'event_occurred_at' => now()->subMinutes(10),
            'payload' => ['raw' => 'payload'],
            'created_at' => now()->subMinutes(10),
        ]);

        $response = $this->getJson('/api/v1/shipments/TRACK-9999', [
            'X-API-Key' => 'secret',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'id',
            'carrier_code',
            'shipping_profile_id',
            'tracking_number',
            'status_code',
            'status_description',
            'last_payload',
            'metadata',
            'events' => [
                [
                    'id',
                    'event_code',
                    'status',
                    'description',
                    'facility',
                    'city',
                    'country',
                    'occurred_at',
                    'payload',
                ],
            ],
        ]);

        $response->assertJsonFragment([
            'tracking_number' => 'TRACK-9999',
            'carrier_code' => 'dhl',
            'event_code' => 'PU',
            'status' => 'PICKED_UP',
        ]);
    }

    public function test_shipment_detail_endpoint_returns_not_found_for_missing_record(): void
    {
        config(['services.api.key' => 'secret']);

        $this->getJson('/api/v1/shipments/DOES-NOT-EXIST', [
            'X-API-Key' => 'secret',
        ])->assertNotFound();
    }
}
