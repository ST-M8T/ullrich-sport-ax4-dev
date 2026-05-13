<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Fulfillment\Integrations\Dhl;

use PHPUnit\Framework\TestCase;

/**
 * Anti-Regression: PROJ-3 (GOAL-2026-05-12T124024-dhlcat / t20b).
 *
 * Sicherstellt, dass keine Datei unter
 * app/Application/Fulfillment/Integrations/Dhl/Services/* direkt
 * `$services->toArray()` auf einer DhlServiceOptionCollection aufruft.
 *
 * Begruendung (Engineering-Handbuch §61 / §75 DRY):
 * Der Mapping-Schritt von DhlServiceOptionCollection in das DHL-API-Payload
 * muss zentral durch DhlAdditionalServiceMapper::toApiPayload(...) erfolgen,
 * damit Validierung gegen den Produktkatalog konsistent bleibt.
 *
 * Erlaubt sind:
 * - `(new DhlBookingRequestDto(...))->toArray()`   (DTO-Serialisierung)
 * - `(new DhlPriceQuoteRequestDto(...))->toArray()`
 * - `(new DhlLabelRequestDto(...))->toArray()`
 *
 * Verboten ist jeglicher direkter Aufruf der Variable `$services` mit
 * `->toArray()` in einer Datei unter Services/.
 */
final class NoDirectServiceToArrayTest extends TestCase
{
    public function test_no_service_calls_services_to_array_directly(): void
    {
        $servicesDir = __DIR__.'/../../../../../../app/Application/Fulfillment/Integrations/Dhl/Services';
        self::assertDirectoryExists($servicesDir);

        $files = glob($servicesDir.'/*.php');
        self::assertNotFalse($files);

        $violations = [];
        foreach ($files as $file) {
            $contents = file_get_contents($file);
            if ($contents === false) {
                continue;
            }

            // Detect `$services->toArray()` (legacy direct mapping bypass).
            if (preg_match('/\$services\s*->\s*toArray\s*\(/', $contents) === 1) {
                $violations[] = basename($file);
            }
        }

        self::assertSame(
            [],
            $violations,
            'Direct $services->toArray() call detected in DHL Services/. '
            ."Use DhlAdditionalServiceMapper::toApiPayload(...) instead. Files: \n"
            .implode("\n", $violations),
        );
    }
}
