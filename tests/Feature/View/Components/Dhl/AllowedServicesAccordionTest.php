<?php

declare(strict_types=1);

namespace Tests\Feature\View\Components\Dhl;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Smoke-Test fuer die wiederverwendbare DHL-Akkordeon-Component
 * (resources/views/components/dhl/allowed-services-accordion.blade.php).
 *
 * Engineering-Handbuch §58/§68: kritische UI-Bausteine erhalten Tests fuer
 * Render-Faehigkeit + zentrale Attribute. Die JS-Verhalten werden separat
 * im Browser/Vitest abgedeckt (Vitest noch nicht eingerichtet — siehe
 * task notes).
 */
final class AllowedServicesAccordionTest extends TestCase
{
    public function test_renders_minimal_booking_mode_without_context(): void
    {
        $html = Blade::render('<x-dhl.allowed-services-accordion />');

        self::assertStringContainsString('data-dhl-services-accordion', $html);
        self::assertStringContainsString('data-mode="booking"', $html);
        self::assertStringContainsString('data-input-name="additional_services"', $html);
        self::assertStringContainsString('data-dhl-services-state="idle"', $html);
        self::assertStringContainsString('data-dhl-services-state="loading"', $html);
        self::assertStringContainsString('data-dhl-services-state="empty"', $html);
        self::assertStringContainsString('data-dhl-services-state="error"', $html);
        self::assertStringContainsString('data-dhl-services-state="success"', $html);
        self::assertStringContainsString('Bitte zuerst Produkt und Routing', $html);
    }

    public function test_renders_profile_mode_with_full_context(): void
    {
        $html = Blade::render(
            '<x-dhl.allowed-services-accordion
                name="dhl_default_service_parameters"
                mode="profile"
                productCode="DHL_EUROPLUS"
                fromCountry="DE"
                toCountry="CH"
                payerCode="SENDER"
                :selectedServices="$pre"
            />',
            [
                'pre' => [
                    ['code' => 'COD', 'parameters' => ['amount' => '12.50']],
                ],
            ],
        );

        self::assertStringContainsString('data-mode="profile"', $html);
        self::assertStringContainsString('data-input-name="dhl_default_service_parameters"', $html);
        self::assertStringContainsString('data-product-code="DHL_EUROPLUS"', $html);
        self::assertStringContainsString('data-from-country="DE"', $html);
        self::assertStringContainsString('data-to-country="CH"', $html);
        self::assertStringContainsString('data-payer-code="SENDER"', $html);
        // Preselected map is JSON-encoded (HTML-escaped) and contains COD key.
        self::assertStringContainsString('data-preselected=', $html);
        self::assertStringContainsString('COD', $html);
        self::assertStringContainsString('Versandprofil', $html);
    }

    public function test_renders_routings_array_for_bulk_mode(): void
    {
        $routings = [
            ['product_code' => 'DHL_PAKET', 'from_country' => 'DE', 'to_country' => 'DE', 'payer_code' => 'SENDER'],
            ['product_code' => 'DHL_PAKET', 'from_country' => 'DE', 'to_country' => 'AT', 'payer_code' => 'SENDER'],
        ];

        $html = Blade::render(
            '<x-dhl.allowed-services-accordion :routings="$routings" />',
            ['routings' => $routings],
        );

        self::assertStringContainsString('data-routings=', $html);
        self::assertStringContainsString('DHL_PAKET', $html);
    }

    public function test_renders_read_only_attribute(): void
    {
        $html = Blade::render(
            '<x-dhl.allowed-services-accordion :readOnly="true" />',
        );

        self::assertStringContainsString('data-read-only="true"', $html);
    }

    public function test_uses_provided_container_id(): void
    {
        $html = Blade::render(
            '<x-dhl.allowed-services-accordion containerId="my-services-1" />',
        );

        self::assertStringContainsString('id="my-services-1"', $html);
    }
}
