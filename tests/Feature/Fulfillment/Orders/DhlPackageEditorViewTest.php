<?php

declare(strict_types=1);

namespace Tests\Feature\Fulfillment\Orders;

use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentSenderProfileModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentOrderModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentPackageModel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * View-Test fuer den DHL Package Editor (Task t14).
 *
 * Verifiziert nur das Rendering — Validation/Booking sind separat
 * abgedeckt (DhlBookingRequest, DhlShipmentBookingService).
 */
final class DhlPackageEditorViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_package_editor_table_and_add_button_for_unbooked_order(): void
    {
        $this->signInWithRole('operations', [
            'username' => 'ops-editor',
            'display_name' => 'Operations Editor',
            'email' => 'ops-editor@example.com',
        ]);

        $senderProfile = $this->makeEditorSenderProfile();

        $order = ShipmentOrderModel::create([
            'external_order_id' => 9001,
            'customer_number' => 1,
            'sender_code' => $senderProfile->sender_code,
            'sender_profile_id' => $senderProfile->id,
            'destination_country' => 'DE',
            'currency' => 'EUR',
            'total_amount' => 50.0,
            'processed_at' => CarbonImmutable::parse('2025-04-01 12:00:00'),
            'is_booked' => false,
        ]);

        ShipmentPackageModel::create([
            'shipment_order_id' => $order->id,
            'package_reference' => 'PAL-01',
            'quantity' => 2,
            'weight_kg' => 12.5,
            'length_mm' => 1200,
            'width_mm' => 800,
            'height_mm' => 1500,
            'truck_slot_units' => 1,
        ]);

        $response = $this->get(route('fulfillment-orders.show', ['order' => $order->id]));

        $response->assertOk();

        $content = $response->getContent();

        // Editor-Form vorhanden
        self::assertStringContainsString('data-package-editor-form', $content);
        self::assertStringContainsString('data-package-editor-table', $content);
        self::assertStringContainsString('data-package-editor-rows', $content);

        // Add-Button + Remove-Button vorhanden
        self::assertStringContainsString('data-package-editor-add', $content);
        self::assertStringContainsString('data-package-editor-remove', $content);

        // Submit-Button vorhanden
        self::assertStringContainsString('data-package-editor-submit', $content);

        // Pre-Filling: pieces[0][...] Feldnamen aus existierendem Paket
        self::assertStringContainsString('name="pieces[0][number_of_pieces]"', $content);
        self::assertStringContainsString('name="pieces[0][weight]"', $content);
        self::assertStringContainsString('name="pieces[0][package_type]"', $content);
        self::assertStringContainsString('name="pieces[0][length]"', $content);
        self::assertStringContainsString('name="pieces[0][width]"', $content);
        self::assertStringContainsString('name="pieces[0][height]"', $content);
        self::assertStringContainsString('name="pieces[0][marks_and_numbers]"', $content);

        // A11y: role=group + aria-label
        self::assertStringContainsString('role="group"', $content);
        self::assertStringContainsString('aria-label="Paket 1"', $content);

        // Pflichtfelder fuer Booking-Request
        self::assertStringContainsString('name="product_code"', $content);
        self::assertStringContainsString('name="payer_code"', $content);
        self::assertStringContainsString('name="default_package_type"', $content);

        // JS-Modul wird via app.js geladen — Marker im DOM (Hook fuer dhl-package-editor.js)
        self::assertStringContainsString('data-package-editor-form', $content);
    }

    public function test_it_renders_single_empty_row_when_order_has_no_packages(): void
    {
        $this->signInWithRole('operations', [
            'username' => 'ops-editor',
            'display_name' => 'Operations Editor',
            'email' => 'ops-editor@example.com',
        ]);

        $senderProfile = $this->makeEditorSenderProfile();

        $order = ShipmentOrderModel::create([
            'external_order_id' => 9002,
            'customer_number' => 2,
            'sender_code' => $senderProfile->sender_code,
            'sender_profile_id' => $senderProfile->id,
            'destination_country' => 'DE',
            'currency' => 'EUR',
            'total_amount' => 30.0,
            'processed_at' => CarbonImmutable::parse('2025-04-02 12:00:00'),
            'is_booked' => false,
        ]);

        $response = $this->get(route('fulfillment-orders.show', ['order' => $order->id]));

        $response->assertOk();

        $content = $response->getContent();

        // Mindestens 1 Row gerendert (Empty-Fallback im Partial)
        self::assertStringContainsString('name="pieces[0][number_of_pieces]"', $content);

        // Default-PackageType vorbelegt
        self::assertStringContainsString('value="PAL"', $content);

        // Empty-Hinweis-Element vorhanden (initial versteckt via d-none)
        self::assertStringContainsString('data-package-editor-empty', $content);
    }

    public function test_it_does_not_render_editor_for_booked_order(): void
    {
        $this->signInWithRole('operations', [
            'username' => 'ops-editor',
            'display_name' => 'Operations Editor',
            'email' => 'ops-editor@example.com',
        ]);

        $senderProfile = $this->makeEditorSenderProfile();

        $order = ShipmentOrderModel::create([
            'external_order_id' => 9003,
            'customer_number' => 3,
            'sender_code' => $senderProfile->sender_code,
            'sender_profile_id' => $senderProfile->id,
            'destination_country' => 'DE',
            'currency' => 'EUR',
            'total_amount' => 10.0,
            'processed_at' => CarbonImmutable::parse('2025-04-03 12:00:00'),
            'is_booked' => true,
            'booked_at' => CarbonImmutable::parse('2025-04-04 09:00:00'),
            'booked_by' => 'tester',
        ]);

        $response = $this->get(route('fulfillment-orders.show', ['order' => $order->id]));

        $response->assertOk();

        $content = $response->getContent();
        self::assertStringNotContainsString('data-package-editor-form', $content);
    }

    private function makeEditorSenderProfile(): FulfillmentSenderProfileModel
    {
        return FulfillmentSenderProfileModel::create([
            'sender_code' => 'SND-EDIT',
            'display_name' => 'Editor Sender',
            'company_name' => 'Test GmbH',
            'street_name' => 'Musterstr.',
            'street_number' => '1',
            'postal_code' => '12345',
            'city' => 'Musterstadt',
            'country_iso2' => 'DE',
        ]);
    }
}
