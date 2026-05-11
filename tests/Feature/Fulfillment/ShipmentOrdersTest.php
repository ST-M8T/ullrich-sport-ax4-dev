<?php

namespace Tests\Feature\Fulfillment;

use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentOrderModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShipmentOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_applies_filters_and_displays_matching_orders(): void
    {
        $this->actingAsAdmin();

        Carbon::setTestNow('2024-01-10 12:00:00');

        $matching = $this->createShipmentOrder([
            'external_order_id' => 123456,
            'sender_code' => 'SND-100',
            'destination_country' => 'DE',
            'is_booked' => false,
            'booked_at' => null,
            'booked_by' => null,
            'processed_at' => Carbon::now()->subDays(2),
        ]);

        $this->createShipmentOrder([
            'external_order_id' => 654321,
            'sender_code' => 'SND-200',
            'destination_country' => 'FR',
            'is_booked' => true,
            'booked_at' => Carbon::now()->subDay(),
            'booked_by' => 'system',
            'processed_at' => Carbon::now()->subDay(),
        ]);

        $response = $this->get(route('fulfillment-orders', [
            'sender_code' => 'SND-100',
            'destination_country' => 'DE',
            'is_booked' => '0',
            'processed_from' => Carbon::now()->subDays(3)->format('Y-m-d'),
            'processed_to' => Carbon::now()->format('Y-m-d'),
        ]));

        $response->assertOk();
        $response->assertSee('#'.$matching->external_order_id, escape: false);
        $response->assertDontSee('#654321', escape: false);

        Carbon::setTestNow();
    }

    public function test_order_can_be_booked_via_post_action(): void
    {
        $this->actingAsAdmin();

        $order = $this->createShipmentOrder([
            'is_booked' => false,
            'booked_at' => null,
            'booked_by' => null,
        ]);

        $showRoute = route('fulfillment-orders.show', $order->getKey());

        $response = $this->post(route('fulfillment-orders.book', $order->getKey()), [
            'redirect_to' => $showRoute,
        ]);

        $response->assertRedirect($showRoute);
        $response->assertSessionHas('success');

        $this->assertTrue(
            ShipmentOrderModel::query()->whereKey($order->getKey())->value('is_booked')
        );
    }

    public function test_order_detail_exposes_sender_profile_assignment_before_dhl_booking(): void
    {
        $this->actingAsAdmin();

        $order = $this->createShipmentOrder([
            'sender_profile_id' => null,
            'sender_code' => 'LEGACY',
            'is_booked' => false,
            'booked_at' => null,
            'booked_by' => null,
        ]);

        $senderProfile = $this->createSenderProfile([
            'sender_code' => 'AX4',
            'display_name' => 'AX4 Versand',
        ]);

        $response = $this->get(route('fulfillment-orders.show', $order->getKey()));

        $response->assertOk();
        $response->assertSee('Vor der DHL-Buchung muss ein Senderprofil zugeordnet werden.');
        $response->assertSee('AX4 Versand (ax4)');
        $response->assertSee('Ordne zuerst ein Senderprofil zu.');

        $assignResponse = $this->post(route('fulfillment-orders.sender-profile', $order->getKey()), [
            'sender_profile_id' => $senderProfile->getKey(),
            'redirect_to' => route('fulfillment-orders.show', $order->getKey()),
        ]);

        $assignResponse->assertRedirect(route('fulfillment-orders.show', $order->getKey()));
        $assignResponse->assertSessionHas('success');

        $this->assertDatabaseHas('shipment_orders', [
            'id' => $order->getKey(),
            'sender_profile_id' => $senderProfile->getKey(),
            'sender_code' => 'ax4',
        ]);
    }

    public function test_booked_order_without_dhl_shipment_can_assign_sender_profile(): void
    {
        $this->actingAsAdmin();

        $order = $this->createShipmentOrder([
            'sender_profile_id' => null,
            'sender_code' => null,
            'is_booked' => true,
            'booked_at' => now(),
            'booked_by' => 'admin-panel',
            'dhl_shipment_id' => null,
        ]);

        $senderProfile = $this->createSenderProfile([
            'sender_code' => 'AX4',
            'display_name' => 'AX4 Versand',
        ]);

        $response = $this->get(route('fulfillment-orders.show', $order->getKey()));

        $response->assertOk();
        $response->assertSee('Vor der DHL-Buchung muss ein Senderprofil zugeordnet werden.');
        $response->assertSee('Senderprofil zuordnen');
        $response->assertSee('Ordne zuerst ein Senderprofil zu.');

        $assignResponse = $this->post(route('fulfillment-orders.sender-profile', $order->getKey()), [
            'sender_profile_id' => $senderProfile->getKey(),
            'redirect_to' => route('fulfillment-orders.show', $order->getKey()),
        ]);

        $assignResponse->assertRedirect(route('fulfillment-orders.show', $order->getKey()));
        $assignResponse->assertSessionHas('success');

        $this->assertDatabaseHas('shipment_orders', [
            'id' => $order->getKey(),
            'sender_profile_id' => $senderProfile->getKey(),
            'sender_code' => 'ax4',
        ]);
    }

    public function test_tracking_transfer_creates_event_and_domain_record(): void
    {
        $this->actingAsAdmin();

        $order = $this->createShipmentOrder([
            'is_booked' => true,
            'booked_at' => now()->subDay(),
            'booked_by' => 'test-user',
        ]);

        $shipment = $this->createShipment([
            'tracking_number' => 'TRACK-123',
        ], 0);

        $this->linkShipmentToOrder($order, $shipment);

        $showRoute = route('fulfillment-orders.show', $order->getKey());

        $response = $this->post(route('fulfillment-orders.transfer', $order->getKey()), [
            'tracking_number' => 'TRACK-123',
            'sync_immediately' => '1',
            'redirect_to' => $showRoute,
        ]);

        $response->assertRedirect($showRoute);
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('shipment_events', [
            'shipment_id' => $shipment->getKey(),
            'event_code' => 'TRANSFER',
            'event_status' => 'TRANSFER_SYNC_NOW',
        ]);

        $this->assertDatabaseHas('domain_events', [
            'event_name' => 'fulfillment.shipment.event_recorded',
            'aggregate_id' => (string) $shipment->getKey(),
        ]);
    }
}
