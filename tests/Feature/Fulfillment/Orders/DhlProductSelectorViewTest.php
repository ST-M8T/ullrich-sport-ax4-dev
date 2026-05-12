<?php

declare(strict_types=1);

namespace Tests\Feature\Fulfillment\Orders;

use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentSenderProfileModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentOrderModel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifiziert, dass die Order-Detail-Ansicht den DHL-Produkt-Selector
 * (Select + JS-Hooks) ausliefert anstelle des frueheren Text-Inputs.
 *
 * Engineering-Handbuch §53 (Loading/Empty/Error State), §75.4 (Single Source
 * of Truth fuer Produkt-Liste).
 */
final class DhlProductSelectorViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_dhl_product_select_with_js_hook(): void
    {
        $this->signInWithRole('operations', [
            'username' => 'ops-user',
            'display_name' => 'Operations User',
            'email' => 'operations@example.com',
        ]);

        $senderProfile = FulfillmentSenderProfileModel::create([
            'sender_code' => 'SND-DHL-01',
            'display_name' => 'DHL Senderprofil',
            'company_name' => 'Ullrich Sport',
            'street_name' => 'Hauptstr.',
            'street_number' => '1',
            'postal_code' => '12345',
            'city' => 'Musterstadt',
            'country_iso2' => 'DE',
        ]);

        $order = ShipmentOrderModel::create([
            'external_order_id' => 9001,
            'customer_number' => 555,
            'sender_code' => 'SND-DHL-01',
            'destination_country' => 'DE',
            'currency' => 'EUR',
            'total_amount' => 19.9,
            'processed_at' => CarbonImmutable::parse('2025-05-01 09:00:00'),
            'is_booked' => false,
            'sender_profile_id' => $senderProfile->id,
        ]);

        $response = $this->get(route('fulfillment-orders.show', ['order' => $order->id]));

        $response->assertOk();

        $content = $response->getContent();

        // Select-Element vorhanden mit korrektem Form-Feldnamen.
        self::assertStringContainsString('id="dhl-product-select"', $content);
        self::assertStringContainsString('name="product_code"', $content);
        self::assertStringContainsString('required', $content);

        // JS-Modul-Hook (data-Attribute) und Status-Region fuer A11y.
        self::assertStringContainsString('data-dhl-product-selector', $content);
        self::assertStringContainsString('data-dhl-product-select', $content);
        self::assertStringContainsString('data-dhl-product-status', $content);
        self::assertStringContainsString('aria-live="polite"', $content);
        self::assertStringContainsString('aria-busy="true"', $content);

        // Loading-Fallback initial sichtbar.
        self::assertStringContainsString('Lade Produkte', $content);

        // Frueheres Text-Input mit name="product_id" darf NICHT mehr existieren.
        self::assertStringNotContainsString('name="product_id"', $content);
    }
}
