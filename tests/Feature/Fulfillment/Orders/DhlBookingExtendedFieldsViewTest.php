<?php

declare(strict_types=1);

namespace Tests\Feature\Fulfillment\Orders;

use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentFreightProfileModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Masterdata\FulfillmentSenderProfileModel;
use App\Infrastructure\Persistence\Fulfillment\Eloquent\Orders\ShipmentOrderModel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * View-Test fuer Task t15: Erweiterte DHL-Booking-Felder.
 *
 * Verifiziert das Rendering von:
 *   - Payer-Code-Radios (DAP/DDP/EXW/CIP, kein Default)
 *   - Pickup-Datum mit aria-describedby
 *   - Container fuer dynamisch geladene Zusatzleistungen
 *   - Senderprofil-Dropdown (Pflicht)
 *   - Frachtprofil-Override-Dropdown (optional)
 *
 * §51 A11y: fieldset/legend, aria-describedby, role=radiogroup.
 * §53 States: idle/loading-Status fuer Services-Container.
 */
final class DhlBookingExtendedFieldsViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_payer_radios_pickup_date_services_container_and_profile_selects(): void
    {
        $this->signInWithRole('operations', [
            'username' => 'ops-extended',
            'display_name' => 'Operations Extended',
            'email' => 'ops-extended@example.com',
        ]);

        $senderProfile = FulfillmentSenderProfileModel::create([
            'sender_code' => 'SND-EXT',
            'display_name' => 'Extended Sender',
            'company_name' => 'Test GmbH',
            'street_name' => 'Musterstr.',
            'street_number' => '1',
            'postal_code' => '12345',
            'city' => 'Musterstadt',
            'country_iso2' => 'DE',
        ]);

        $freightProfile = FulfillmentFreightProfileModel::create([
            'shipping_profile_id' => 4711,
            'label' => 'Standard-Fracht',
        ]);

        $order = ShipmentOrderModel::create([
            'external_order_id' => 9100,
            'customer_number' => 100,
            'sender_code' => $senderProfile->sender_code,
            'sender_profile_id' => $senderProfile->id,
            'destination_country' => 'DE',
            'currency' => 'EUR',
            'total_amount' => 75.0,
            'processed_at' => CarbonImmutable::parse('2025-04-10 09:00:00'),
            'is_booked' => false,
        ]);

        $response = $this->get(route('fulfillment-orders.show', ['order' => $order->id]));

        $response->assertOk();
        $content = $response->getContent();

        // 1) Payer-Code Radio-Gruppe — alle 4 Optionen, role=radiogroup, fieldset
        self::assertStringContainsString('data-dhl-payer-code', $content);
        self::assertStringContainsString('role="radiogroup"', $content);
        self::assertStringContainsString('aria-required="true"', $content);
        foreach (['DAP', 'DDP', 'EXW', 'CIP'] as $code) {
            self::assertStringContainsString(
                'value="'.$code.'"',
                $content,
                'Payer-Code-Option fehlt: '.$code,
            );
        }
        // Kein Default — KEIN "checked" Attribut bei Payer-Radios
        self::assertDoesNotMatchRegularExpression(
            '/name="payer_code"[^>]*\schecked/i',
            $content,
            'Payer-Code darf keinen Default haben.',
        );

        // 2) Pickup-Datum — aria-describedby, min-Attribut, Hilfetext
        self::assertStringContainsString('id="dhl-pkg-pickup-date"', $content);
        self::assertStringContainsString('name="pickup_date"', $content);
        self::assertStringContainsString('aria-describedby="dhl-pkg-pickup-date-help"', $content);
        self::assertStringContainsString('id="dhl-pkg-pickup-date-help"', $content);
        self::assertMatchesRegularExpression('/min="\d{4}-\d{2}-\d{2}"/', $content);

        // 3) Zusatzleistungen-Container (dynamisch geladen)
        self::assertStringContainsString('data-dhl-services-container', $content);
        self::assertStringContainsString('data-dhl-services-status', $content);
        self::assertStringContainsString('data-dhl-services-list', $content);
        self::assertStringContainsString('aria-live="polite"', $content);
        self::assertStringContainsString('data-dhl-services-url', $content);

        // 4) Senderprofil-Dropdown (Pflicht) — enthaelt das angelegte Profil
        self::assertStringContainsString('id="dhl-pkg-sender-profile"', $content);
        self::assertStringContainsString('name="sender_profile_id"', $content);
        self::assertStringContainsString('Extended Sender', $content);
        self::assertStringContainsString('SND-EXT', $content);

        // 5) Frachtprofil-Override-Dropdown (optional)
        self::assertStringContainsString('id="dhl-pkg-freight-profile"', $content);
        self::assertStringContainsString('name="freight_profile_id"', $content);
        self::assertStringContainsString('Standard-Fracht', $content);

        // 6) Produkt-Code-Input mit data-Hook fuer Service-Loader
        self::assertStringContainsString('data-dhl-product-code-input', $content);
    }
}
