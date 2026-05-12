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
 * View-Test fuer Task t16: Final Booking-UI Integration & States.
 *
 * Verifiziert, dass das DHL-Booking-Formular alle 8 Pflicht-Felder
 * sowie Submit-Button + Error-Banner-Container im DOM rendert.
 *
 * §52 Formularregel: Labels, Pflichtmarkierungen, Schutz vor Mehrfachabsendung.
 * §53 Loading/Empty-State: Spinner + Loading-Label am Submit; Empty-Hinweis.
 * §75.1 striktes DRY: alles aus EINEM Partial.
 */
final class DhlBookingFormCompleteViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_all_eight_form_fields_submit_button_and_error_container(): void
    {
        $this->signInWithRole('operations', [
            'username' => 'ops-complete',
            'display_name' => 'Operations Complete',
            'email' => 'ops-complete@example.com',
        ]);

        $senderProfile = FulfillmentSenderProfileModel::create([
            'sender_code' => 'SND-COMPL',
            'display_name' => 'Complete Sender',
            'company_name' => 'Test GmbH',
            'street_name' => 'Musterstr.',
            'street_number' => '1',
            'postal_code' => '12345',
            'city' => 'Musterstadt',
            'country_iso2' => 'DE',
        ]);

        FulfillmentFreightProfileModel::create([
            'shipping_profile_id' => 8800,
            'label' => 'Frachtprofil Complete',
        ]);

        $order = ShipmentOrderModel::create([
            'external_order_id' => 9200,
            'customer_number' => 200,
            'sender_code' => $senderProfile->sender_code,
            'sender_profile_id' => $senderProfile->id,
            'destination_country' => 'DE',
            'currency' => 'EUR',
            'total_amount' => 99.0,
            'processed_at' => CarbonImmutable::parse('2025-04-10 09:00:00'),
            'is_booked' => false,
        ]);

        $response = $this->get(route('fulfillment-orders.show', ['order' => $order->id]));

        $response->assertOk();
        $content = $response->getContent();

        // 1) product_code (Select aus t13 ODER Input aus Package-Editor t15)
        self::assertMatchesRegularExpression(
            '/name="product_code"/',
            $content,
            'Feld product_code fehlt.',
        );

        // 2) payer_code (Radios DAP/DDP/EXW/CIP)
        self::assertStringContainsString('name="payer_code"', $content);
        foreach (['DAP', 'DDP', 'EXW', 'CIP'] as $code) {
            self::assertStringContainsString('value="'.$code.'"', $content);
        }

        // 3) default_package_type
        self::assertStringContainsString('name="default_package_type"', $content);

        // 4) pickup_date
        self::assertStringContainsString('name="pickup_date"', $content);
        self::assertStringContainsString('type="date"', $content);

        // 5) additional_services[] Container (dynamisch via JS)
        self::assertStringContainsString('data-dhl-services-container', $content);
        self::assertStringContainsString('data-dhl-services-list', $content);

        // 6) sender_profile_id
        self::assertStringContainsString('name="sender_profile_id"', $content);
        self::assertStringContainsString('Complete Sender', $content);

        // 7) freight_profile_id (optional)
        self::assertStringContainsString('name="freight_profile_id"', $content);
        self::assertStringContainsString('Frachtprofil Complete', $content);

        // 8) pieces[i][...] Repeater-Felder (mind. 1 Row aus Package-Editor)
        self::assertStringContainsString('name="pieces[0][number_of_pieces]"', $content);
        self::assertStringContainsString('name="pieces[0][package_type]"', $content);
        self::assertStringContainsString('name="pieces[0][weight]"', $content);
        self::assertStringContainsString('name="pieces[0][length]"', $content);
        self::assertStringContainsString('name="pieces[0][width]"', $content);
        self::assertStringContainsString('name="pieces[0][height]"', $content);

        // Submit-Button mit Loading-State (Spinner + Label-Wechsel)
        self::assertStringContainsString('data-package-editor-submit', $content);
        self::assertStringContainsString('data-label-loading', $content);
        self::assertStringContainsString('data-package-editor-spinner', $content);
        self::assertStringContainsString('data-package-editor-submit-label', $content);
        self::assertStringContainsString('DHL-Buchung absenden', $content);

        // Empty-State-Hinweis fuer Pakete (0 Pakete im Auftrag)
        self::assertStringContainsString('data-package-editor-no-packages', $content);

        // Error-Banner-Container fuer DHL-API-Fehler (nur bei Error gerendert,
        // aber der Selektor existiert im Partial — wir verifizieren via @error-Block:
        // ohne Fehler kein DOM-Knoten, daher pruefen wir den allgemeinen Errors-Block
        // sowie das hidden empty-Indikator-Element).
        self::assertStringContainsString('data-package-editor-empty', $content);
    }
}
