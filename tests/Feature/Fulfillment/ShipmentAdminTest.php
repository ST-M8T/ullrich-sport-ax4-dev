<?php

namespace Tests\Feature\Fulfillment;

use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentEventModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentModel;
use App\Infrastructure\Persistence\Identity\Eloquent\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class ShipmentAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_shipments_by_carrier_and_status(): void
    {
        $this->authenticateOperationsUser();

        $now = Carbon::now();

        ShipmentModel::query()->create([
            'carrier_code' => 'DHL',
            'tracking_number' => 'DH123',
            'status_code' => 'IN_TRANSIT',
            'status_description' => 'Unterwegs',
            'failed_attempts' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        ShipmentModel::query()->create([
            'carrier_code' => 'GLS',
            'tracking_number' => 'GLS999',
            'status_code' => 'DELIVERED',
            'status_description' => 'Zugestellt',
            'failed_attempts' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $response = $this->get(route('fulfillment-shipments', [
            'carrier' => 'DHL',
            'status' => 'IN_TRANSIT',
        ]));

        $response
            ->assertOk()
            ->assertSee('DH123')
            ->assertDontSee('GLS999');
    }

    public function test_manual_sync_creates_event_and_redirects(): void
    {
        $this->authenticateOperationsUser();

        $now = Carbon::now();

        $shipment = ShipmentModel::query()->create([
            'carrier_code' => 'DHL',
            'tracking_number' => 'SYNC001',
            'status_code' => 'IN_TRANSIT',
            'status_description' => 'Unterwegs',
            'failed_attempts' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $response = $this->post(route('fulfillment-shipments.sync', $shipment->getKey()), [
            'note' => 'Bitte erneut prüfen',
        ]);

        $response->assertRedirect(route('fulfillment-shipments'));

        $manualEvent = ShipmentEventModel::query()
            ->where('shipment_id', $shipment->getKey())
            ->where('event_code', 'MANUAL_SYNC')
            ->first();

        $this->assertNotNull($manualEvent, 'Manual sync event was not persisted.');
        $this->assertSame('Bitte erneut prüfen', $manualEvent->event_description);
        $this->assertSame('MANUAL_SYNC', $manualEvent->event_status);
        $this->assertIsArray($manualEvent->payload);
        $this->assertSame(auth()->user()->email, $manualEvent->payload['initiator'] ?? null);
    }

    public function test_events_tab_renders_event_data(): void
    {
        $this->authenticateOperationsUser();

        $now = Carbon::now();

        $shipment = ShipmentModel::query()->create([
            'carrier_code' => 'DHL',
            'tracking_number' => 'EVENT001',
            'status_code' => 'IN_TRANSIT',
            'status_description' => 'Unterwegs',
            'failed_attempts' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $occurredAt = $now->copy()->subHours(2);

        ShipmentEventModel::query()->create([
            'id' => 1,
            'shipment_id' => $shipment->getKey(),
            'event_code' => 'PICKED_UP',
            'event_status' => 'IN_TRANSIT',
            'event_description' => 'Abholung im Depot',
            'facility' => 'Hamburg',
            'city' => 'Hamburg',
            'country_iso2' => 'DE',
            'event_occurred_at' => $occurredAt,
            'payload' => ['message' => 'Package picked up'],
            'created_at' => $occurredAt,
        ]);

        $response = $this->get(route('fulfillment-shipments', [
            'tab' => 'events',
        ]));

        $response
            ->assertOk()
            ->assertSee('Abholung im Depot')
            ->assertSee('PICKED_UP')
            ->assertSee('EVENT001');
    }

    private function authenticateOperationsUser(): void
    {
        $user = UserModel::query()->create([
            'username' => 'shipment-admin',
            'display_name' => 'Shipment Admin',
            'email' => 'shipment@example.com',
            'password_hash' => bcrypt('password'),
            'role' => 'operations',
            'must_change_password' => false,
            'disabled' => false,
        ]);

        $this->actingAs($user);
    }
}
