<?php

namespace Tests\Feature\Fulfillment;

use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentEventModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentOrderItemModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentOrderModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentOrderShipmentModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentPackageModel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ShipmentOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_filters_orders_by_combined_criteria(): void
    {
        $this->authenticateOperationsUser();

        $matching = ShipmentOrderModel::create([
            'external_order_id' => 1001,
            'customer_number' => 123,
            'sender_code' => 'SND-01',
            'destination_country' => 'DE',
            'currency' => 'EUR',
            'total_amount' => 99.5,
            'processed_at' => CarbonImmutable::parse('2025-01-10 12:00:00'),
            'is_booked' => true,
            'booked_at' => CarbonImmutable::parse('2025-01-11 09:30:00'),
            'booked_by' => 'tester',
        ]);

        ShipmentOrderModel::create([
            'external_order_id' => 1002,
            'customer_number' => 456,
            'sender_code' => 'SND-02',
            'destination_country' => 'FR',
            'currency' => 'EUR',
            'total_amount' => 49.0,
            'processed_at' => CarbonImmutable::parse('2025-02-10 12:00:00'),
            'is_booked' => false,
        ]);

        $response = $this->get('/admin/fulfillment/orders?sender_code=SND-01&destination_country=DE&is_booked=1&processed_from=2025-01-01&processed_to=2025-01-31');

        $response->assertOk();
        $response->assertSee('#'.$matching->external_order_id);
        $response->assertDontSee('#1002');
    }

    public function test_it_shows_order_details(): void
    {
        $this->authenticateOperationsUser();

        $order = ShipmentOrderModel::create([
            'external_order_id' => 2001,
            'customer_number' => 789,
            'sender_code' => 'SND-03',
            'destination_country' => 'NL',
            'currency' => 'EUR',
            'total_amount' => 149.9,
            'processed_at' => CarbonImmutable::parse('2025-03-01 08:00:00'),
            'is_booked' => true,
            'booked_at' => CarbonImmutable::parse('2025-03-02 09:15:00'),
            'booked_by' => 'automation-user',
        ]);

        ShipmentOrderItemModel::create([
            'shipment_order_id' => $order->id,
            'sku' => 'SKU-001',
            'description' => 'Testprodukt',
            'quantity' => 2,
            'weight_kg' => 1.25,
            'is_assembly' => false,
        ]);

        ShipmentPackageModel::create([
            'shipment_order_id' => $order->id,
            'package_reference' => 'PKG-REF',
            'quantity' => 1,
            'weight_kg' => 2.5,
            'length_mm' => 300,
            'width_mm' => 200,
            'height_mm' => 150,
            'truck_slot_units' => 1,
        ]);

        $shipment = ShipmentModel::create([
            'carrier_code' => 'DHL',
            'tracking_number' => 'TRACK-2001',
            'status_code' => 'TRANSIT',
            'status_description' => 'Unterwegs',
            'last_event_at' => CarbonImmutable::parse('2025-03-03 11:00:00'),
        ]);

        ShipmentOrderShipmentModel::create([
            'shipment_order_id' => $order->id,
            'shipment_id' => $shipment->id,
        ]);

        ShipmentEventModel::create([
            'id' => 1,
            'shipment_id' => $shipment->id,
            'event_code' => 'DEPART',
            'event_status' => 'DEPARTED',
            'event_description' => 'Abfahrt aus dem Paketzentrum',
            'facility' => 'Leipzig',
            'city' => 'Leipzig',
            'country_iso2' => 'DE',
            'event_occurred_at' => CarbonImmutable::parse('2025-03-03 10:45:00'),
            'payload' => ['foo' => 'bar'],
            'created_at' => CarbonImmutable::parse('2025-03-03 10:46:00'),
        ]);

        $response = $this->get(route('fulfillment-orders.show', ['order' => $order->id]));

        $response->assertOk();
        $response->assertSee('Auftrag #'.$order->external_order_id);
        $response->assertSee('Testprodukt');
        $response->assertSee('PKG-REF');
        $response->assertSee('TRACK-2001');
        $response->assertSee('Abfahrt aus dem Paketzentrum');
    }

    public function test_it_books_an_order(): void
    {
        $this->authenticateOperationsUser();

        $order = ShipmentOrderModel::create([
            'external_order_id' => 3001,
            'sender_code' => 'SND-04',
            'destination_country' => 'AT',
            'processed_at' => CarbonImmutable::parse('2025-04-01 09:00:00'),
            'is_booked' => false,
        ]);

        $this->withSession(['_token' => 'test-token']);

        $response = $this->post(
            route('fulfillment-orders.book', ['order' => $order->id]),
            [
                '_token' => 'test-token',
                'redirect_to' => route('fulfillment-orders.show', ['order' => $order->id]),
            ]
        );

        $response->assertRedirect(route('fulfillment-orders.show', ['order' => $order->id]));
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertTrue($order->is_booked);
        $this->assertNotNull($order->booked_at);
        $this->assertSame(auth()->user()->email, $order->booked_by);
    }

    public function test_it_transfers_tracking_numbers_via_service(): void
    {
        $this->authenticateOperationsUser();

        $order = ShipmentOrderModel::create([
            'external_order_id' => 4001,
            'sender_code' => 'SND-05',
            'destination_country' => 'DE',
            'processed_at' => CarbonImmutable::parse('2025-05-01 08:00:00'),
            'is_booked' => true,
            'booked_at' => CarbonImmutable::parse('2025-05-02 10:00:00'),
            'booked_by' => 'automation',
        ]);

        $shipment = ShipmentModel::create([
            'carrier_code' => 'DHL',
            'tracking_number' => 'TRACK-4001',
        ]);

        ShipmentOrderShipmentModel::create([
            'shipment_order_id' => $order->id,
            'shipment_id' => $shipment->id,
        ]);

        $this->withSession(['_token' => 'test-token']);

        $response = $this->post(
            route('fulfillment-orders.transfer', ['order' => $order->id]),
            [
                '_token' => 'test-token',
                'redirect_to' => route('fulfillment-orders.show', ['order' => $order->id]),
            ]
        );

        $response->assertRedirect(route('fulfillment-orders.show', ['order' => $order->id]));
        $response->assertSessionHas('success');

        $events = ShipmentEventModel::query()
            ->where('shipment_id', $shipment->id)
            ->get();

        $this->assertCount(1, $events);
        $event = $events->first();
        $this->assertSame('TRANSFER', $event->event_code);
        $this->assertSame('TRANSFER_REQUESTED', $event->event_status);
        $this->assertSame('Tracking transfer triggered via admin panel', $event->event_description);
        $this->assertEquals('admin-panel', $event->payload['source'] ?? null);
    }

    private function authenticateOperationsUser(): void
    {
        $this->signInWithRole('operations', [
            'username' => 'ops-user',
            'display_name' => 'Operations User',
            'email' => 'operations@example.com',
        ]);
    }
}
